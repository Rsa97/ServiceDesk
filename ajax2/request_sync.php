<?php

include 'common.php';
include 'func_calcTime.php';
include 'init.php';
include 'init_soap.php';

if (false === $soap) {
	echo "Нет связи с 1С\n";
	exit;
}

// Ищем заявки в MySQL и передаём их в 1С 
try {
	$req = $db->prepare("SELECT `r`.`id`, `r`.`problem`, `r`.`createdAt`, `r`.`reactBefore`, `r`.`fixBefore`, `r`.`repairBefore`, ".
							   "`r`.`currentState`, `r`.`contactPerson_guid`, `r`.`contractDivision_guid`, `r`.`slaLevel`, ".
							   "`r`.`equipment_guid`, `r`.`service_guid`, `r`.`toReact`, `r`.`toFix`, `r`.`toRepair`, ".
							   "`cd`.`contract_guid`, `r`.`isPlanned` ".
							"FROM `requests` AS `r` ".
							"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = `r`.`contractDivision_guid` ".
							"WHERE `r`.`guid` IS NULL");
	$reqU = $db->prepare("UPDATE `requests` ".
							"SET `guid` = UNHEX(REPLACE(:requestGuid, '-', '')), `currentState` = 'received' ".
							"WHERE `id` = :requestId");
	$req->execute();
} catch (PDOException $e) {
	echo "MySQL error ".$e->getMessage()."\n";
	exit;
}

while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($id, $problem, $createdAt, $reactBefore, $fixBefore, $repairBefore, $currentState, $contactPerson_guid,
		 $contractDivision_guid, $slaLevel, $equipment_guid, $service_guid, $toReact, $toFix, $toRepair, $contract_guid, 
		 $isPlanned) = $row; 
	try {
		$createdAt = timeToSOAP($createdAt);
		$soapReq = array('sd_request_table' => array(array('CodeNodeSiteSD' => $node_1c,
														   'NumberSD' => $id,
														   'createdAt' => $createdAt,
														   'contractDivision_guid' => formatGuid($contractDivision_guid),
														   'contactPerson_guid' => formatGuid($contactPerson_guid), 
														   'contract_guid' => formatGuid($contract_guid), 
													 	   'slaLevel' => $slaLevel,  
													 	   'service_guid' => formatGuid($service_guid), 
													 	   'equipment_guid' => formatGuid($equipment_guid),
													 	   'Problem' => $problem,
													 	   'Plan' => (1 == $isPlanned),
													 	   'onWait' => 0)));
		$res = $soap->sd_Request_Open($soapReq);
	} catch (Exception $e) {
		echo "SOAP error ".$e->getMessage()."\n";
//		exit;
	}

	$answer = $res->return->sd_request_row;
	if (is_array($answer))
		$answer = $answer[0];
	if (1 != $answer->ResultSuccessful) {
		echo $answer->ErrorDescription."\n";
//		exit;
	}

	try {
		$reqU->execute(array('requestGuid' => $answer->GUID, 'requestId' => $id));
	} catch (Exception $e) {
		echo "MySQL error ".$e->getMessage()."\n";
		exit;
	}
}
echo "Ok\n"; 

?>