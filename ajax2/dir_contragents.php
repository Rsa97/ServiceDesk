<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список доступных контрагентов
try {
	$req = $db->prepare("SELECT DISTINCT `ca`.`guid` AS `guid`, `ca`.`name` AS `name`".
							"FROM `contragents` AS `ca` ".
							"JOIN `contracts` AS `c` ON `ca`.`guid` = `c`.`contragent_guid` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
							"LEFT JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` AND `div`.`isDisabled` = 0 ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ".
							"WHERE (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
									"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
								"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
							"ORDER BY `ca`.`name`");
	$req->execute(array('byClient' => $byClient, 'userGuid' => $userGuid, 'byActive' => $byActive));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$contragents = '';
$n = 0;
while ($row = ($req->fetch(PDO::FETCH_ASSOC))) {
	$contragents .= "<option value='".formatGuid($row['guid'])."'>".htmlspecialchars($row['name']);
	$n++;
}
if ($n > 1)
	$contragents = "<option value='*' selected>-- Выберите контрагента --".$contragents;
echo json_encode(array('contragent' => $contragents));
exit;
?>