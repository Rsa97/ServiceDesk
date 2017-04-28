<?php

include 'api_init.php';

// Получаем список ответственных лиц
try {	// Получаем список услуг
	$req = $db->prepare("SELECT `srv`.`guid`, `srv`.`name`, `srv`.`shortname`, ".
							   "GROUP_CONCAT(DISTINCT `dss`.`slaLevel` SEPARATOR ',') AS `sla` ".
							"FROM `contractDivisions` AS `cd` ".
							"JOIN `divServicesSLA` AS `dss` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) AND `cd`.`isDisabled` = 0 ".
								"AND `dss`.`contract_guid` = `cd`.`contract_guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
							"JOIN `services` AS `srv` ON `srv`.`utility` = 0 AND `srv`.`guid` = `dss`.`service_guid` ".
							"GROUP BY `srv`.`guid` ".
							"ORDER BY `srv`.`name`");
	$req->execute(array('divisionGuid' => $paramValues['division'])); 
} catch (PDOException $e) {
	return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)', 
								 'orig' => 'MySQL error '.$e->getMessage()));
	exit;
}
$services = array();
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($servGuid, $servName, $servSName, $servSLAs) = $row;
	$services[] = array('guid' => formatGuid($servGuid), 'name' => $servName, 'code' => $servSName, 'sla' => $servSLAs);
}
if (count($services) == 0)
	return_format($format, array('state' => 'error', 'text' => "Филиал не найден"));
else
	return_format($format, array('state' => 'ok', 'services' => $services));
?>