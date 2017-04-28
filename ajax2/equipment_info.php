<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

try {
	$req = $db->prepare("SELECT `eq`.`serialNumber`, `em`.`name`, `emfg`.`name`, `eqst`.`name`, `eqt`.`name`, `eq`.`serviceNumber` ".
							"FROM `equipment` AS `eq` ".
							"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ".
							"LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`guid` = `em`.`equipmentManufacturer_guid` ".
							"LEFT JOIN `equipmentSubTypes` AS `eqst` ON `eqst`.`guid` = `em`.`equipmentSubType_guid` ".
							"LEFT JOIN `equipmentTypes` AS `eqt` ON `eqt`.`guid` = `eqst`.`equipmentType_guid` ".
							"WHERE `eq`.`contractDivision_guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
								"AND `eq`.`onService` = 1 AND `eq`.`guid` = UNHEX(REPLACE(:equipmentGuid, '-', ''))");
	$req->execute(array('divisionGuid' => $paramValues['division'], 'equipmentGuid' => $paramValues['equipment']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
if (($row = $req->fetch(PDO::FETCH_NUM)))
	echo json_encode(array('_SN' => $row[0], '_eqType' => "{$row[4]} / {$row[3]}", '_manufacturer' => $row[2], '_model' => $row[1],
						   '_servNum' => $row[5]));
else 
    echo json_encode(array('error' => 'Ошибка в параметрах'));
exit;
?>