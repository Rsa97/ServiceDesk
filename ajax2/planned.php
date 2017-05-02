<?php

include 'common.php';
include 'func_calcTime.php';
include "init.php";
include 'init_soap.php';

try {
	$req = $db->prepare("SELECT DISTINCT `pr`.`id`, `pr`.`contractDivision_guid`, `pr`.`service_guid`, `pr`.`slaLevel`, ". 
										"`pr`.`problem`, `u`.`user_guid`, `div`.`addProblem`, `c`.`guid` ". 
							"FROM `plannedRequests` AS `pr` ". 
							"JOIN `contractDivisions` AS `div` ON `pr`.`contractDivision_guid` = `div`.`guid` AND `div`.`isDisabled` = 0 ". 
    						"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` AND `c`.`isActive` = 1 AND `c`.`isStopped` = 0 ".
    						"LEFT JOIN ( ". 
								"SELECT `contractDivision_guid`, `user_guid` FROM `userContractDivisions` GROUP BY `contractDivision_guid` ". 
							") AS `u` ON `u`.`contractDivision_guid` = `div`.`guid` ". 
    						"WHERE `pr`.`nextDate` <= NOW() ". 
    							"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)");
	$req1 = $db->prepare("INSERT INTO `requests` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
												"`currentState`, `contactPerson_guid`, `contractDivision_guid`, `slaLevel`, ".
												"`equipment_guid`, `service_guid`, `toReact`, `toFix`, `toRepair`, `isPlanned`) ".
							"VALUES (:problem, :createdAt, :reactBefore, :fixBefore, :repairBefore, 'preReceived', ".
									"UNHEX(REPLACE(:contactGuid, '-', '')), UNHEX(REPLACE(:divisionGuid, '-', '')), :slaLevel, ".
									"UNHEX(REPLACE(:equipmentGuid, '-', '')), UNHEX(REPLACE(:serviceGuid, '-', '')), ".
									":toReact, :toFix, :toRepair, 1)");
	$req2 = $db->prepare("UPDATE `requests` ".
							"SET `guid` = UNHEX(REPLACE(:requestGuid, '-', '')), `currentState` = 'received' ".
							"WHERE `id` = :requestId");
	$req3 = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`) ".
								"VALUES (:createdAt, 'open', 'received', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:contactGuid, '-', '')))");
    $req4 = $db->prepare("UPDATE `plannedRequests` SET `nextDate` = `nextDate` + INTERVAL `intervalYears` YEAR + ".
    								"INTERVAL `intervalMonths` MONTH + INTERVAL `intervalWeeks` WEEK + INTERVAL `intervalDays` DAY ".
							"WHERE `id` = :id");
	$req5 = $db->prepare("UPDATE `contractDivisions` SET `addProblem` = '' WHERE `guid` = UNHEX(REPLACE(:divisionGuid, '-', ''))"); 
	$req->execute();
} catch (PDOException $e) {
	print "MySQL error".$e->getMessage();
	exit;
}
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($plannedId, $divisionGuid, $serviceGuid, $slaLevel, $problem, $contactGuid, $addProblem, $contractGuid) = $row;
	$divisionGuid = formatGuid($divisionGuid);
	$serviceGuid = formatGuid($serviceGuid);
	$contactGuid = formatGuid($contactGuid);
	$contractGuid = formatGuid($contractGuid);
	if ($contactGuid === null)
		$contactGuid = '2C2DD584-73F7-11E6-95F3-002590839A1D'; // Робот
	$problem .= "\n".$addProblem;
	$time = calcTime($db, $divisionGuid, $serviceGuid, $slaLevel, 1);
	try {
		$req1->execute(array('problem' => $problem, 'createdAt' => $time['createdAt'], 'reactBefore' => $time['reactBefore'], 
						'fixBefore' => $time['fixBefore'], 'repairBefore' => $time['repairBefore'], 'contactGuid' => $contactGuid, 
						'divisionGuid' => $divisionGuid, 'slaLevel' => $slaLevel, 'equipmentGuid' => null,
						'serviceGuid' => $serviceGuid, 'toReact' => $time['toReact'], 'toFix' => $time['toFix'], 
						'toRepair' => $time['toRepair']));
		$id = $db->lastInsertId();
	} catch (PDOException $e) {
		print "MySQL error".$e->getMessage();
		$id = null;
	}
	if (null !== $id && false !== $soap) {
		try {
			$req4->execute(array('id' => $plannedId));
			$req5->execute(array('divisionGuid' => $divisionGuid)); 
		} catch (PDOException $e) {
			print "MySQL error".$e->getMessage();
		}
		try {
			$createdAt = timeToSOAP($time['createdAt']);
			$soapReq = array('sd_request_table' => array(array('CodeNodeSiteSD' => $node_1c,
															   'NumberSD' => $id,
															   'createdAt' => $createdAt,
															   'contractDivision_guid' => $divisionGuid,
															   'contactPerson_guid' => $contactGuid, 
															   'contract_guid' => $contractGuid, 
													 		   'slaLevel' => $slaLevel,  
														 	   'service_guid' => $serviceGuid, 
														 	   'equipment_guid' => null,
														 	   'Problem' => $problem,
														 	   'Plan' => true,
														 	   'onWait' => 0)));
			$res = $soap->sd_Request_Open($soapReq);
			$answer = $res->return->sd_request_row;
			$guid = null;
			if (is_array($answer))
				$answer = $answer[0];
			if (1 != $answer->ResultSuccessful)
				print $answer->ErrorDescription."\n";
			else
				$guid = $answer->GUID;
		} catch (Exception $e) {
			print "SOAP error".$e->getMessage()."\n";
			$guid = null;
		}
	}
	if (null !== $guid) {
		try {
			$db->query('START TRANSACTION');
			$req2->execute(array('requestGuid' => $guid, 'requestId' => $id));
			$req3->execute(array('createdAt' => $time['createdAt'], 'requestGuid' => $guid, 'contactGuid' => $contactGuid));
			$db->query('COMMIT');
		} catch (PDOException $e) {
			print "MySQL error".$e->getMessage();
			$db->query('ROLLBACK');
		}	
	}
	
}

?>
