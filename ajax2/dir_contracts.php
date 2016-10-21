<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

// Получаем список доступных контрагентов
try {
	$req = $db->prepare("SELECT DISTINCT `c`.`guid` AS `guid`, `c`.`number` AS `number`".
							"FROM `contracts` AS `c` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
							"LEFT JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` AND `div`.`isDisabled` = 0 ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ".
							"WHERE `c`.`contragent_guid` = UNHEX(REPLACE(:contragentGuid, '-', '')) ". 
								"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
									"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
								"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
							"ORDER BY `c`.`number`");
	$req->execute(array('contragentGuid' => $paramValues['contragent'], 'byClient' => $byClient, 'userGuid' => $userGuid, 
						'byActive' => $byActive));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$contracts = '';
$n = 0;
while ($row = ($req->fetch(PDO::FETCH_ASSOC))) {
	$contracts .= "<option value='".formatGuid($row['guid'])."'>".htmlspecialchars($row['number']);
	$n++;
}
if ($n > 1)
	$contracts = "<option value='*' selected>-- Выберите договор --".$contragents;
echo json_encode(array('contract' => $contracts));
exit;
?>