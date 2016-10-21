<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список доступных контрагентов
try {
	$req = $db->prepare("SELECT DISTINCT `div`.`guid` AS `guid`, `div`.`name` AS `name` ".
							"FROM `contractDivisions` AS `div` ".
							"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ".
							"WHERE `div`.`contract_guid` = UNHEX(REPLACE(:contractGuid, '-', '')) AND `div`.`isDisabled` = 0 ". 
								"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
									"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
								"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
							"ORDER BY `div`.`name`");
	$req->execute(array('contractGuid' => $paramValues['contract'], 'byClient' => $byClient, 'userGuid' => $userGuid, 
						'byActive' => $byActive));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$divisions = '';
$n = 0;
while ($row = ($req->fetch(PDO::FETCH_ASSOC))) {
	$divisions .= "<option value='".formatGuid($row['guid'])."'>".htmlspecialchars($row['name']);
	$n++;
}
if ($n > 1)
	$divisions = "<option value='*' selected>-- Выберите подразделение --".$divisions;
echo json_encode(array('division' => $divisions));
exit;
?>