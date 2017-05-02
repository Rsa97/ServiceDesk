<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'func_calcTime.php';
include 'init.php';

$allowedTo = array('admin');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$cause = trim($paramValues['cause']);

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT DISTINCT `rq`.`guid` AS `guid`, `rq`.`contractDivision_guid` AS `div`, `rq`.`service_guid` AS `srv`, ".
										"`rq`.`slaLevel` AS `sla` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`onWait` = 0 AND `rq`.`id` = :requestId ".
            					"AND 'canceled' = `rq`.`currentState` AND `div`.`guid` = `rq`.`contractDivision_guid` ".
            				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            					"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` OR `rq`.`currentState` NOT IN ('closed', 'canceled')) ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
          					"WHERE (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
          							"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
    $req->execute(array('byClient' => $byClient, 'userGuid' => $userGuid, 'byPartner' => $byPartner, 'partnerGuid' => $partnerGuid,
    					'requestId' => $paramValues['id']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

if (!($row = $req->fetch(PDO::FETCH_ASSOC))) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}	
if (null === $row['guid']) {
	echo json_encode(array('error' => "Статус заявки {$paramValues['id']} не может быть изменён до синхронизации с внутренней базой."));
	exit;
}

$guid = formatGuid($row['guid']);
$division = formatGuid($row['div']);
$service = formatGuid($row['srv']);
$sla = $row['sla'];

include 'init_soap.php';
if (false === $soap) {
	echo json_encode(array('error' => 'Выполнение операции невозможно без синхронизации с внутренним сервером. Попробуйте повторить действие позднее.'));
	exit;
}

$time = calcTime($db, $division, $service, $sla, 1);
$soapTime = timeToSOAP($time['createdAt']);

try {
	$soapReq = array('sd_requestevent_table' => array(array('CodeNodeSiteSD' => $node_1c,
															'GUID' 			 => $guid,
															'newState' 		 => 'received',
															'timestamp' 	 => $soapTime,
															'user_guid' 	 => $userGuid,
															'comment' 		 => $cause)));
	$res = $soap->sd_Request_unCancel($soapReq);
} catch (Exception $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера',
							'req' => $soapReq,  
							'orig' => "SOAP error".$e->getMessage()));
	exit;
}

$answer = $res->return->sd_requestevent_row;		
if (is_array($answer))
	$answer = $answer[0];
if (true != $answer->ResultSuccessful) {
	echo json_encode(array('error' => "Статус заявки {$paramValues['id']} не может быть изменён из-за ошибок связи с внутренней базой.",
							'err1C' => $answer->ErrorDescription));
	exit;
}

try {
	$db->query('START TRANSACTION');
	$req = $db->prepare("UPDATE `requests` SET `currentState` = 'received', `stateChangedAt` = :stateChangedAt, ".
												"`createdAt` = :stateChangedAt, `reactedAt` = NULL, `fixedAt` = NULL, ".
												"`repairedAt` = NULL, `engineer_guid` = NULL, `reactBefore` = :reactBefore, ".
												"`fixBefore` = :fixBefore, `repairBefore` = :repairBefore ".
							"WHERE `id` = :requestId");
	$req->execute(array('stateChangedAt' => $time['createdAt'], 'requestId' => $paramValues['id'], 'reactBefore' => $time['reactBefore'], 
						'fixBefore' => $time['fixBefore'], 'repairBefore' => $time['repairBefore']));
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, 'changeState', 'received', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :cause)");
	$req->execute(array('stateChangedAt' => $time['createdAt'], 'requestGuid' => $guid, 'userGuid' => $userGuid, 'cause' => $cause));
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('ok' => 'ok', 'answer' => $answer));

?>