<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список уровней критичности для услуги
try {
	$req = $db->prepare("SELECT DISTINCT `dss`.`slaLevel`, `dss`.`isDefault` ".
							"FROM `contractDivisions` AS `cd` ".
							"JOIN `contracts` AS `c` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) AND `cd`.`isDisabled` = 0 ".
								"AND `c`.`guid` = `cd`.`contract_guid` ".
							"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')) ".
								"AND `dss`.`contract_guid` = `c`.`guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
							"ORDER BY `dss`.`slaLevel` ");
	$req->execute(array('divisionGuid' => $paramValues['division'], 'serviceGuid' => $paramValues['service']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$levels = "";
$level = "";
$isSel = 0;
while (($row = $req->fetch())) {
	list($slaLevel, $isDefault) = $row;
	$levels .= $level;
	if ($isDefault == 1)
		$isSel = 1;
	$level = "<option value='{$slaLevel}'".($isDefault == 1 ? " selected" : "").">{$slaLevels[$slaLevel]}";
}
$levels .= "<option value='{$slaLevel}'".($isDefault != 1 && $isSel == 1 ? "" : " selected").">{$slaLevels[$slaLevel]}";
echo json_encode(array('level' => $levels));
exit;
?>