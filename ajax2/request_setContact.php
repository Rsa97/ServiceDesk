<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$allowedTo = array('admin');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT `t`.`guid`, `u`.`lastName`, `u`.`firstName`, `u`.`middleName` ". 
							"FROM ( ".
								"SELECT `rq`.`guid` AS `guid`, `ucd`.`user_guid` AS `user_guid` ". 
									"FROM `requests` AS `rq` ". 
									"JOIN `contractDivisions` AS `cd` ON `rq`.`id` = :reqNum ". 
										"AND `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ". 
            						"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
										"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`) ".
									"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ". 
								"UNION SELECT `rq`.`guid`, `uc`.`user_guid` ". 
									"FROM `requests` AS `rq` ". 
									"JOIN `contractDivisions` AS `cd` ON `rq`.`id` = :reqNum ". 
										"AND `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ". 
            						"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
										"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`) ".
									"JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `cd`.`contract_guid` ".
							") AS `t` ".
							"JOIN `users` AS `u` ON `u`.`guid` = UNHEX(REPLACE(:contactGuid, '-', '')) ".
								"AND `u`.`guid` = `t`.`user_guid`");
	$req->execute(array('reqNum' => $paramValues['request'], 'contactGuid' => $paramValues['contact']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

$guid = null;
if ($row = $req->fetch(PDO::FETCH_NUM)) {
	$guid = formatGuid($row[0]);
	$contactName = nameFull($row[1], $row[2], $row[3]);
}

if (null === $guid) {
	echo json_encode(array('error' => "Контактное лицо по заявке {$paramValues['request']} не может быть изменено до синхронизации с внутренней базой."));
	exit;
}

include 'init_soap.php';
if (false === $soap) {
	echo json_encode(array('error' => 'Выполнение операции невозможно без синхронизации с внутренним сервером. Попробуйте повторить действие позднее.'));
	exit;
}

$time = date_format(new DateTime, 'Y-m-d H:i:s');
$soapTime = timeToSOAP($time);

try {
	$soapReq = array('sd_request_table' => array(array('CodeNodeSiteSD' => $node_1c,
													   'GUID' 			 => $guid,
													   'contactPerson_guid' => $paramValues['contact'])));
	$res = $soap->sd_Request_changeContactPerson($soapReq);
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
	echo json_encode(array('error' => "Контактное лицо по заявке {$paramValues['request']} не может быть изменено из-за ошибок связи с внутренней базой.",
							'err1C' => $res));
	exit;
}
	
try {
	$db->query('START TRANSACTION');
	
	$req = $db->prepare("UPDATE `requests` SET `contactPerson_guid` = UNHEX(REPLACE(:contactGuid, '-', '')) WHERE `id` = :requestId");
	$req->execute(array('requestId' => $paramValues['request'], 'contactGuid' => $paramValues['contact']));

	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, 'changeContact', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :text)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 
						'text' => $contactName));
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('Ok' => 'Ok'));
?>