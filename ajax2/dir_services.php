<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список услуг
try {
	$req = $db->prepare("SELECT DISTINCT `srv`.`guid`, `srv`.`name`, `srv`.`autoOnly` ".
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
$services = '';
$num = 0;
while (($row = $req->fetch(PDO::FETCH_NUM))) {
	list($servGuid, $servName, $autoOnly) = $row;
	$servGuid = formatGuid($servGuid);
	$services .= "<option value='{$servGuid}' data-autoonly={$autoOnly}>".htmlspecialchars($servName);
	$num++;
}
if ($num > 1)
	$services = "<option value='*' selected>-- Выберите услугу --".$services;

echo json_encode(array('service' => $services));
?>