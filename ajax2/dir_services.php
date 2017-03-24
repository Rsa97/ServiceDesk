<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список ответственных лиц
try {	// Получаем список услуг
	$req = $db->prepare("SELECT DISTINCT `srv`.`guid`, `srv`.`name` ".
							"FROM `contractDivisions` AS `cd` ".
							"JOIN `divServicesSLA` AS `dss` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) AND `cd`.`isDisabled` = 0 ".
								"AND `dss`.`contract_guid` = `cd`.`contract_guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
							"JOIN `services` AS `srv` ON `srv`.`utility` = 0 AND `srv`.`guid` = `dss`.`service_guid` ".
							"ORDER BY `srv`.`name`");
	$req->execute(array('divisionGuid' => $paramValues['division'])); 
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$services = '<ul>';
$num = 0;
while (($row = $req->fetch(PDO::FETCH_NUM))) {
	list($servGuid, $servName) = $row;
	$servGuid = formatGuid($servGuid);
	$services .= "<li data-id='{$servGuid}'>".htmlspecialchars($servName);
	$num++;
}
$services .= '</ul>';
$ret = array('!lookService' => ($num > 1 ? 1 : 0), '_service' => '', 'service_guid' => null);
if (1 == $num) {
	$ret['_service'] = $servName;
	$ret['service_guid'] = $servGuid;
}

if (0 == $paramValues['edit'])
	echo json_encode($ret);
else 
	echo json_encode(array('selectServiceList' => $services));
?>