<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$servNum = (isset($paramValues['servNum']) ? $paramValues['servNum'].'%' : '%');
try {
	$req = $db->prepare("SELECT `eq`.`guid`, `eq`.`serviceNumber`, `eq`.`rem`, `em`.`name`, `emfg`.`name`, `eqst`.`name`, ".
								"`eq`.`serialNumber`, `et`.`name` ".
							"FROM `equipment` AS `eq` ".
							"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ".
							"LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`guid` = `em`.`equipmentManufacturer_guid` ".
							"LEFT JOIN `equipmentSubTypes` AS `eqst` ON `eqst`.`guid` = `em`.`equipmentSubType_guid` ".
							"LEFT JOIN `equipmentTypes` AS `et` ON `et`.`guid` = `eqst`.`equipmentType_guid` ".
							"WHERE `eq`.`contractDivision_guid` =  UNHEX(REPLACE(:divisionGuid, '-', '')) ".
								"AND `eq`.`onService` = 1 AND `eq`.`serviceNumber` LIKE :servNum ".
							"ORDER BY `eqst`.`name`, `emfg`.`name`, `em`.`name`, `eq`.`serviceNumber`");
	$req->execute(array('divisionGuid' => $paramValues['division'], 'servNum' => $servNum));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$prevSubType = '';
$list = "<ul id='snList'>";
$count = 0;
while ($row = ($req->fetch())) {
	list($eqGuid, $eqServNum, $eqRem, $eqModel, $eqMfg, $eqSubType, $serial, $eqType) = $row;
	$eqType = (('' == $eqType || '' == $eqSubType) ? $eqType.$eqSubType : $eqType.' / '.$eqSubType);
	$eqGuid = formatGuid($eqGuid);
	if ($eqRem != '')
		$eqRem = "(".htmlspecialchars($eqRem).")";
	if ($prevSubType != $eqSubType) {
		if ($prevSubType != '')
			$list .= "</ul>";				
		$list .= "<li class='collapsed'><span class='ui-icon ui-icon-folder-collapsed'></span>{$eqSubType}<ul>";
	}
	$prevSubType = $eqSubType;
	$list .= "<li data-id='{$eqGuid}' data-servnum='{$eqServNum}' data-sn='{$serial}' data-eqtype='{$eqType}' ".
			 	"data-mfg='{$eqMfg}' data-model='{$eqModel}'>{$eqServNum} - {$eqMfg} {$eqModel} {$eqRem}";
	$count++; 
}
$list .= "</ul></ul>";
if (1 == $count)
	$list = "<ul id='snList'>".
			"<li class='collapsed'><span class='ui-icon ui-icon-folder-collapsed'></span>{$eqSubType}<ul class='single'>".
			"<li data-id='{$eqGuid}' data-servnum='{$eqServNum}' data-sn='{$serial}' data-eqtype='{$eqType}' ".
			 	"data-mfg='{$eqMfg}' data-model='{$eqModel}'>{$eqServNum} - {$eqMfg} {$eqModel} {$eqRem}".
			"</ul></ul>";
echo json_encode(array('selectEqList' => $list));
exit;
?>