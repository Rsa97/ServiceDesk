<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';
include 'func_calcTime.php';
include 'init_soap.php';

$allowedTo = array('operator', 'engineer', 'admin');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$err = array();

$list = implode(',', array_filter(explode(',', $paramValues['ids']), function($num) { return preg_match('/^\d+$/', $num); }));

if ('' == $list) {
	header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
	exit;
}



try {
	$req = $db->prepare("SELECT DISTINCT `pr`.`id`, `pr`.`contractDivision_guid`, `pr`.`service_guid`, `pr`.`slaLevel`, ".
										"`pr`.`problem`, IFNULL(`u`.`user_guid`, IFNULL(`cu`.`user_guid`, `du`.`guid`)), ".
										"`div`.`addProblem`, `div`.`contract_guid` ".
        					"FROM `plannedRequests` AS `pr` ".
            				"JOIN `contractDivisions` AS `div` ON FIND_IN_SET(`pr`.`id`, :list) ".
            					"AND `pr`.`contractDivision_guid` = `div`.`guid` ".
            					"AND `pr`.`nextDate` <= DATE_ADD(NOW(), INTERVAL `pr`.`preStart` DAY)".
            				"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            				"LEFT JOIN ( ".
            					"SELECT `contractDivision_guid`, MIN(`user_guid`) AS `user_guid` ".
            						"FROM `userContractDivisions` ".
            						"GROUP BY `contractDivision_guid` ".
            				") AS `u` ON `u`.`contractDivision_guid` = `div`.`guid` ".
            				"LEFT JOIN ( ".
            					"SELECT `contract_guid`, MIN(`user_guid`) AS `user_guid` ".
            						"FROM `userContracts` ".
            						"GROUP BY `contract_guid` ".
            				") AS `cu` ON `cu`.`contract_guid` = `c`.`guid` ".
            				"LEFT JOIN (".
            					"SELECT `guid` FROM `users` WHERE `login` = 'robot' ".
            				") AS `du` ".
          					"WHERE  NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`");
	$req1 = $db->prepare("INSERT INTO `requests` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
												"`currentState`, `contactPerson_guid`, `contractDivision_guid`, `slaLevel`, ".
												"`equipment_guid`, `service_guid`, `toReact`, `toFix`, `toRepair`) ".
							"VALUES (:problem, :createdAt, :reactBefore, :fixBefore, :repairBefore, 'preReceived', ".
									"UNHEX(REPLACE(:contactGuid, '-', '')), UNHEX(REPLACE(:divisionGuid, '-', '')), :slaLevel, ".
									"NULL, UNHEX(REPLACE(:serviceGuid, '-', '')), ".
									":toReact, :toFix, :toRepair)");
	$req2 = $db->prepare("UPDATE `requests` ".
							"SET `guid` = UNHEX(REPLACE(:requestGuid, '-', '')), `currentState` = 'received' ".
							"WHERE `id` = :requestId");
	$req3 = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`) ".
								"VALUES (:createdAt, 'open', 'received', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:contactGuid, '-', '')))");
    $req4 = $mysqli->prepare("UPDATE `plannedRequests` SET `nextDate` = `nextDate` + INTERVAL `intervalYears` YEAR + ".
    								"INTERVAL `intervalMonths` MONTH + INTERVAL `intervalWeeks` WEEK + INTERVAL `intervalDays` DAY ".
									"WHERE `id` = :id");
	$req5 = $mysqli->prepare("UPDATE `contractDivisions` SET `addProblem` = '' WHERE `guid` = UNHEX(REPLACE(:divisionGuid, '-', ''))"); 

	$req->execute(array('list' => $list));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}


foreach ($req->fetchAll(PDO::FETCH_NUM) as $row) {
	list($id, $divId, $srvId, $slaLevel, $problem, $clientId, $divProblem, $contId) = $row;
	$clientId = formatGuid($clientId);
	$divId = formaiGuid($divId);
	$srvId = formaiGuid($srvId);
	$contId = formaiGuid($contId);
	$time = calcTime($divId, $srvId, $slaLevel, 1);
	$soapTime = timeToSOAP($time['createdAt']);
	
	$problem .= "\n".$divProblem;
	try {
		$db->query('START TRANSACTION');
		$req1->execute(array('problem' => $problem, 'createdAt' => $time['createdAt'], 'reactBefore' => $time['reactBefore'], 
							 'fixBefore' => $time['fixBefore'], 'repairBefore' => $time['repairBefore'], 'contactGuid' => $clientId, 
							 'divisionGuid' => $divId, 'slaLevel' => $slaLevel, 'serviceGuid' => $srvId, 'toReact' => $time['toReact'], 
							 'toFix' => $time['toFix'], 'toRepair' => $time['toRepair']));
		$req4->execute(array('id' => $id));
		$req5->execute(array('divisionGuid' => $divId)); 
		$db->query('COMMIT');
	} catch (PDOException $e) {
		$err[] = "MySQL error".$e->getMessage();
		$db->query('ROLLBACK'); 
	}
	if ($req1->rowCount() == 0)
		next;
	$reqId = $db->lastInsertId();

	$soapErr = false;
	if ($soap !== false) {
		try {
			$soapReq = array('sd_request_table' => array(array('CodeNodeSiteSD' => 'SDTEST2',
															   'NumberSD' => $reqId,
															   'createdAt' => $createdAt,
															   'contractDivision_guid' => $divId,
															   'contactPerson_guid' => $clientId, 
															   'contract_guid' => $contId, 
														 	   'slaLevel' => $slaLevel,  
														 	   'service_guid' => $servId, 
														 	   'equipment_guid' => null,
														 	   'Problem' => $problem,
														 	   'onWait' => 0)));
			$res = $soap->sd_Request_Open($soapReq);
			$answer = $res->return->sd_request_row;
			if (is_array($answer))
				$answer = $answer[0];
			if (1 != $answer->ResultSuccessful) {
				$err[] = $answer->ErrorDescription;
				$soapErr = true;
			}
		} catch (Exception $e) {
			$err[] = "SOAP error".$e->getMessage();
			$soapErr = true;
		}
	}
	if (!$soapErr) {
		try {
			$db->query('START TRANSACTION');
			$req2->execute(array('requestGuid' => $answer->GUID, 'requestId' => $reqId));
			$req3->execute(array('createdAt' => $time['createdAt'], 'requestGuid' => $answer->GUID, 
								 'contactGuid' => $clientId));
			$db->query('COMMIT');
		} catch (Exception $e) {
			$err[] = "MySQL error".$e->getMessage(); 
			$db->query('ROLLBACK');
		}
	}
}

echo json_encode(array('ok' => 'ok', 'errs' => $err));

?>