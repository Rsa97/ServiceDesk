<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$allowedTo = array('engineer', 'admin', 'partner');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$cause = trim($paramValues['cause']);

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT DISTINCT `rq`.`guid` AS `guid`, `rq`.`onWait` AS `onWait`, `rq`.`currentState` AS `state` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`id` = :requestId ".
            					"AND `rq`.`currentState` IN ('accepted','fixed') AND `div`.`guid` = `rq`.`contractDivision_guid` ".
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
$onWait = (1 == $row['onWait'] ? 0 : 1);
$state = $row['currentState'];

include 'init_soap.php';
if (false === $soap) {
	echo json_encode(array('error' => 'Выполнение операции невозможно без синхронизации с внутренним сервером. Попробуйте повторить действие позднее.'));
	exit;
}

$time = date_format(new DateTime, 'Y-m-d H:i:s');
$soapTime = timeToSOAP($time);

try {
	$soapReq = array('sd_requestevent_table' => array(array('CodeNodeSiteSD' => $node_1c,
															'GUID' 			 => $guid,
															'newState' 		 => $state,
															'timestamp' 	 => $soapTime,
															'user_guid' 	 => $userGuid,
															'comment' 		 => $cause)));
	if (1 == $onWait) {
		$res = $soap->sd_Request_onWait($soapReq);
		$ans = 'sd_SLA_row';
	} else { 
		$res = $soap->sd_Request_offWait($soapReq);
		$ans = 'sd_requestevent_row';
	}
} catch (Exception $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера',
							'req' => $soapReq,  
							'orig' => "SOAP error".$e->getMessage()));
	exit;
}

$answer = $res->return->{$ans};		
if (is_array($answer))
	$answer = $answer[0];
if (true != $answer->ResultSuccessful) {
	echo json_encode(array('error' => "Статус заявки {$paramValues['id']} не может быть изменён из-за ошибок связи с внутренней базой.",
							'err1C' => $answer->ErrorDescription));
	exit;
}

try {
	$db->query('START TRANSACTION');
	if (1 == $onWait) {
		$req = $db->prepare("UPDATE `requests` SET `onWait` = :onWait WHERE `id` = :requestId");
		$req->execute(array('onWait' => $onWait, 'requestId' => $paramValues['id']));
	} else {
		$req = $db->prepare("UPDATE `requests` AS `r` ".
								"LEFT JOIN (".
									"SELECT MAX(`timestamp`) AS `ts`, `request_guid` ".
										"FROM `requestEvents` ".
										"WHERE `request_guid` = UNHEX(REPLACE(:requestGuid, '-', '')) AND `event` = 'onWait' ".
								") AS `re` ON `re`.`request_guid` = `r`.`guid` ".
								"SET `r`.`onWait` = :onWait, ".
									"`r`.`totalWait` = `r`.`totalWait`+TIME_TO_SEC(TIMEDIFF(:stateChangedAt, IFNULL(`re`.`ts`, :stateChangedAt)))/60 ".
								"WHERE `id` = :requestId");
		$req->execute(array('onWait' => $onWait, 'requestId' => $paramValues['id'], 'requestGuid' => $guid, 'stateChangedAt' => $time));
	}
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, IF(:onWait = 1, 'onWait', 'offWait'), :state, UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :cause)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 'cause' => $cause, 
						'state' => $state, 'onWait' => $onWait));
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('ok' => 'ok', 'answer' => $answer));

?>