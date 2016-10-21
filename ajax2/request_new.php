<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'func_calcTime.php';
include 'init.php';

$equipment = null;
$contact = null;
if (isset($paramValues['equipment']) && preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/', $paramValues['equipment']))
	$equipment = $paramValues['equipment'];
if (isset($paramValues['contact']) &&  preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/', $paramValues['contact']))
	$contact = $paramValues['contact'];

try {
// Проверка прав
	$req = $db->prepare("SELECT COUNT(*) ".
							"FROM `contractDivisions` AS `cd` ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `cd`.`guid` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `cd`.`contract_guid` ".
							"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')) ".
								"AND `dss`.`contract_guid` = `cd`.`contract_guid` ".
								"AND `dss`.`divType_guid` = `cd`.`type_guid` AND `dss`.`slaLevel` = :slaLevel ".
							"LEFT JOIN `equipment` AS `eq` ON `eq`.`contractDivision_guid` = `cd`.`guid` ".
							"WHERE (:contactGuid IS NULL OR `ucd`.`user_guid` = UNHEX(REPLACE(:contactGuid, '-', '')) ".
									"OR `uc`.`user_guid` = UNHEX(REPLACE(:contactGuid, '-', ''))) ".
								"AND `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) AND `cd`.`isDisabled` = 0 ".
								"AND (:equipmentGuid IS NULL OR (`eq`.`guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) ".
									"AND `eq`.`onService` = 1))");
	$req->execute(array('divisionGuid' => $paramValues['division'], 'serviceGuid' => $paramValues['service'],
						'slaLevel' => $paramValues['slaLevel'], 'contactGuid' => $contact, 'equipmentGuid' => $equipment));
	$ok = 0;
	if (($row = $req->fetch(PDO::FETCH_NUM)))
		$ok = $row[0];
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
if ($ok == 0) {
  echo json_encode(array('error' => 'Ошибка в параметрах'));
  exit;
}

// Считаем время
$time = calcTime($db, $paramValues['division'], $paramValues['service'], $paramValues['slaLevel'], 1);

// Записываем заявку в MySQL
try {
	$req = $db->prepare("INSERT INTO `requests` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
												"`currentState`, `contactPerson_guid`, `contractDivision_guid`, `slaLevel`, ".
												"`equipment_guid`, `service_guid`, `toReact`, `toFix`, `toRepair`) ".
							"VALUES (:problem, :createdAt, :reactBefore, :fixBefore, :repairBefore, 'preReceived', ".
									"UNHEX(REPLACE(:contactGuid, '-', '')), UNHEX(REPLACE(:divisionGuid, '-', '')), :slaLevel, ".
									"UNHEX(REPLACE(:equipmentGuid, '-', '')), UNHEX(REPLACE(:serviceGuid, '-', '')), ".
									":toReact, :toFix, :toRepair)");
	$req->execute(array('problem' => $paramValues['problem'], 'createdAt' => $time['createdAt'], 'reactBefore' => $time['reactBefore'], 
						'fixBefore' => $time['fixBefore'], 'repairBefore' => $time['repairBefore'], 'contactGuid' => $contact, 
						'divisionGuid' => $paramValues['division'], 'slaLevel' => $paramValues['slaLevel'], 'equipmentGuid' => $equipment,
						'serviceGuid' => $paramValues['service'], 'toReact' => $time['toReact'], 'toFix' => $time['toFix'], 
						'toRepair' => $time['toRepair']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
if ($req->rowCount() == 0) {
  echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
  exit;
}

$id = $db->lastInsertId();

include 'init_soap.php';

if (false === $soap) {
	echo json_encode(array('error' => 'Нет связи с 1С'));
	exit;
}
try {
	$soapReq = array('sd_request_table' => array('NumberSD' => $id, 'contactPerson_guid' => $contact, 
												 'contractDivision_guid' => $paramValues['division'], 'createdAt' => $time['createdAt'],
												 'slaLevel' => $paramValues['slaLevel'], 'equipment_guid' => $equipment, 
												 'service_guid' => $paramValues['service'], 'Problem' => $paramValues['problem'],
												 'onWait' => 0));
	$res = $soap->sd_Request_Open($soapReq);
} catch (Exception $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "SOAP error".$e->getMessage()));
	exit;
}

echo json_encode(array('result' => print_r($res, true))); 
exit;
?>