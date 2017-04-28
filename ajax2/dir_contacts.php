<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список ответственных лиц
try {
	$req = $db->prepare("SELECT `u`.`guid`, `u`.`firstName`, `u`.`lastName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, `u`.`address` ".
						"FROM `users` AS `u` ".
						"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
							"AND `u`.`rights` = 'client' AND `ucd`.`user_guid` = `u`.`guid` ".
						"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = `ucd`.`contractDivision_guid` ".
							"AND `cd`.`isDisabled` = 0 ".
					"UNION SELECT `u`.`guid`, `u`.`firstName`, `u`.`lastName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, `u`.`address` ".
						"FROM `users` AS `u` ".
						"JOIN `userContracts` AS `uc` ON `u`.`rights` = 'client' AND `uc`.`user_guid` = `u`.`guid` ".
						"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
							"AND `cd`.`contract_guid` = `uc`.`contract_guid`");
	$req->execute(array('divisionGuid' => $paramValues['division'])); 
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$contacts = '';
$num = 0;
$have = 0;
$ret = array('_contact' => '', '_email' => '', '_phone' => '', '_address' => '', 'contact_guid' => null);
while (($row = $req->fetch(PDO::FETCH_NUM))) {
	list($contGuid, $contGN, $contLN, $contMN, $contEmail, $contPhone, $contAddress) = $row;
	$contGuid = formatGuid($contGuid);
	$contacts .= "<option value='{$contGuid}' data-email='".htmlspecialchars($contEmail).
				 "' data-phone='".htmlspecialchars($contPhone)."' data-address='".htmlspecialchars($contAddress)."'".
				 ($userGuid == $contGuid ? ' selected' : '').">".htmlspecialchars(nameFull($contLN, $contGN, $contMN));
	if ($userGuid == $contGuid)
		$have = 1;
	$num++;
}
$contacts .= '';

if ($num > 1 && 0 == $have)
	$contacts = "<option value='*' selected>-- Выберите контактное лицо --".$contacts;
$ret['contact'] = $contacts; 

echo json_encode($ret);
?>