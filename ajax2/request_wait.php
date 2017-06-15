<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';
include 'func_calcTime.php';

$allowedTo = array('engineer', 'admin', 'partner');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$cause = trim($paramValues['cause']);

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT DISTINCT `rq`.`guid` AS `guid`, `rq`.`onWait` AS `onWait`, `rq`.`currentState` AS `state`, ".
										"`rq`.`createdAt`, `rq`.`toReact`, `rq`.`toFix`, `rq`.`toRepair`, `rq`.`contractDivision_guid`, ".
										"`rq`.`service_guid`, `rq`.`slaLevel` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`id` = :requestId ".
            					"AND `rq`.`currentState` IN ('received','accepted','fixed') AND `div`.`guid` = `rq`.`contractDivision_guid` ".
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

if (!($row = $req->fetch(PDO::FETCH_NUM))) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}	
if (null === $row[0]) {
	echo json_encode(array('error' => "Статус заявки {$paramValues['id']} не может быть изменён до синхронизации с внутренней базой."));
	exit;
}

list($guid, $onWait, $state, $createdAt, $toReact, $toFix, $toRepair, $divGuid, $servGuid, $sla) = $row;
$guid = formatGuid($guid);
$divGuid = formatGuid($divGuid);
$servGuid = formatGuid($servGuid);
$onWait = (1 == $onWait ? 0 : 1);

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
	$tsDelta = 0;
	if (0 == $onWait) {
// Пробуем получить время последней приостановки заявки  
		$req = $db->prepare("SELECT MAX(`timestamp`) ".
								"FROM `requestEvents` ".
								"WHERE `request_guid` = UNHEX(REPLACE(:requestGuid, '-', '')) AND 'onWait' = `event`");
		$req->execute(array('requestGuid' => $guid));
		if ($row = $req->fetch(PDO::FETCH_NUM)) {
			$req = $db->prepare("SELECT calcTime_v4(:requestId, :startTime, NOW())");
			$req->execute(array('requestId' => $paramValues['id'], 'startTime' => $row[0]));
			if ($row = $req->fetch(PDO::FETCH_NUM))
				$tsDelta = $row[0];
			switch ($state) {
				case 'received':
					$toReact += $tsDelta;
					$reactBefore = calcTime2($db, $divGuid, $servGuid, $sla, $createdAt, $toReact);
				case 'accepted':
					$toFix += $tsDelta;
					$fixBefore = calcTime2($db, $divGuid, $servGuid, $sla, $createdAt, $toFix);
				case 'fixed':
					$toRepair += $tsDelta;
					$repairBefore = calcTime2($db, $divGuid, $servGuid, $sla, $createdAt, $toRepair);
					break;
			}
			$req = $db->prepare("UPDATE `requests` SET `toReact` = :toReact, `reactBefore` = :reactBefore, `toFix` = :toFix, ".
														"`fixBefore` = :fixBefore, `toRepair` = :toRepair, `repairBefore` = :repairBefore, ".
														"`totalWait` = `totalWait`+:delta ".
									"WHERE `id` = :requestId");
			$req->execute(array('toFix' => $toFix, 'toReact' => $toReact, 'reactBefore' => $reactBefore, 'fixBefore' => $fixBefore, 
								'toRepair' => $toRepair, 'repairBefore' => $repairBefore, 'requestId' => $paramValues['id'], 
								'delta' => $tsDelta));
		}
	}
	// Изменяем состояние запроса
	$req = $db->prepare("UPDATE `requests` SET `onWait` = :onWait WHERE `id` = :requestId");
	$req->execute(array('onWait' => $onWait, 'requestId' => $paramValues['id']));
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, IF(:onWait = 1, 'onWait', 'offWait'), :state, UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :cause)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 'cause' => $cause, 
						'state' => $state, 'onWait' => $onWait)); 
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	$db->query('ROLLBACK');
	exit;
}

echo json_encode(array('ok' => 'ok', 'answer' => $answer));

?>