<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'func_calcTime.php';
include 'init.php';

$allowedTo = array('engineer', 'admin');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT `rq`.`guid`, `rq`.`createdAt`, `rq`.`contractDivision_guid`, `cd`.`type_guid`, `c`.`guid`, ".
								"`s`.`name` ".
          					"FROM `requests` AS `rq` ".
          					"JOIN `contractDivisions` AS `cd` ON `rq`.`id` = :requestId ".
          						"AND `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ".
          					"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
          						"AND NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` ".
          					"JOIN `divServicesSLA` AS `sla` ON `sla`.`contract_guid` = `cd`.`contract_guid` ". 
          						"AND `sla`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')) ".
          						"AND `sla`.`divType_guid` = `cd`.`type_guid` AND `sla`.`slaLevel` = :level ".
							"JOIN `services` AS `s` ON `s`.`guid` = `sla`.`service_guid`");
    $req->execute(array('requestId' => $paramValues['id'], 'serviceGuid' => $paramValues['service'], 
    				    'level' => $paramValues['slaLevel']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

$guid = null;
if ($row = $req->fetch(PDO::FETCH_NUM)) {
	$guid = formatGuid($row[0]);
	$soapTime = timeToSOAP($row[1]);
	$created = date_create_from_format('Y-m-d H:i:s', $row[1]);
	$divisionGuid = formatGuid($row[2]);
	$divTypeGuid = formatGuid($row[3]);
	$contractGuid = formatGuid($row[4]);
	$serviceName = $row[5];
} else {
	echo json_encode(array('error' => 'Ошибка в параметрах запроса'));
	exit;
}
$times = calcTime($db, $divisionGuid, $paramValues['service'], $paramValues['slaLevel'], 1, $created);

if (null === $guid) {
	echo json_encode(array('error' => "Услуга в заявке {$paramValues['id']} не может быть изменена до синхронизации с внутренней базой."));
	exit;
}

include 'init_soap.php';
if (false === $soap) {
	echo json_encode(array('error' => 'Выполнение операции невозможно без синхронизации с внутренним сервером. Попробуйте повторить действие позднее.'));
	exit;
}

$time = date_format(new DateTime, 'Y-m-d H:i:s');

try {
	$soapReq = array('sd_SLA_table' => array(array('createdAt' => $soapTime,
												   'service_guid' => $paramValues['service'],
												   'slaLevel' => $paramValues['slaLevel'],
												   'divtype_guid' => $divTypeGuid,
												   'contract_guid' => $contractGuid)));
	$res = $soap->sd_SLA_getdata($soapReq);												   
	$answer = $res->return->sd_SLA_row;		
	if (is_array($answer))
		$answer = $answer[0];
	if (true != $answer->ResultSuccessful) {
		echo json_encode(array('error' => "Услуга в заявке {$paramValues['id']} не может быть изменена из-за ошибок связи с внутренней базой.",
							   'err1C' => $res));
		exit;
	}

	$soapReq = array('sd_request_table' => array(array('CodeNodeSiteSD' => 'SDTEST2',
													   'GUID' 			 => $guid,
												 	   'slaLevel' => $paramValues['slaLevel'],  
												 	   'service_guid' => $paramValues['service'],
													   'createdAt' => $answer->createdAt,
													   'reactBefore' => $answer->reactBefore,
													   'fixBefore' => $answer->fixBefore,
													   'repairBefore' => $answer->repairBefore,
													   'toReact' => $answer->toReact,
													   'toFix' => $answer->toFix,
													   'toRepair' => $answer->toRepair,
													   'dayType_work' =>  $answer->dayType_work,
													   'dayType_weekend' => $answer->dayType_weekend,
													   'startDayTime' =>  $answer->startDayTime,
													   'endDayTime' => $answer->endDayTime)));
	$res = $soap->sd_Request_changeServiceSlaLevel($soapReq);
} catch (Exception $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера',
							'req' => $soapReq,  
							'orig' => "SOAP error".$e->getMessage()));
	exit;
}

$answer = $res->return->sd_request_row;		
if (is_array($answer))
	$answer = $answer[0];
if (true != $answer->ResultSuccessful) {
	echo json_encode(array('error' => "Услуга в заявке {$paramValues['id']} не может быть изменена из-за ошибок связи с внутренней базой.",
							'err1C' => $res));
	exit;
}
	
try {
	$db->query('START TRANSACTION');
	
	$req = $db->prepare("UPDATE `requests` ".
							"SET `service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')), `slaLevel` = :level, 
								 `reactBefore` = :reactBefore, `fixBefore` = :fixBefore, `repairBefore` = :repairBefore, ".
								 "`toReact` = :toReact, `toFix` = :toFix, `toRepair` = :toRepair ".
						"WHERE `id` = :requestId");
	$req->execute(array('requestId' => $paramValues['id'], 'serviceGuid' => $paramValues['service'], 
						'level' => $paramValues['slaLevel'], 'reactBefore' => $times['reactBefore'], 'fixBefore' => $times['fixBefore'], 
						'repairBefore' => $times['repairBefore'], 'toReact' => $times['toReact'], 'toFix' => $times['toFix'], 
						'toRepair' => $times['toRepair']));
	$text = "{$serviceName}\nУровень: {$slaLevels[$paramValues['slaLevel']]}"; 
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, 'changeService', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :text)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 'text' => $text));
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('Ok' => 'Ok'));
?>