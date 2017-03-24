<?php

include 'api_init.php';

// Получаем список доступных контрагентов
try {
	$req = $db->prepare("SELECT DISTINCT `div`.`guid` AS `guid`, `div`.`name` AS `name` ".
							"FROM `contractDivisions` AS `div` ".
							"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ".
							"WHERE `c`.`number` = :contractNumber AND `div`.`isDisabled` = 0 ". 
								"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
									"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
								"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
							"ORDER BY `div`.`name`");
	$req->execute(array('contractNumber' => $paramValues['contract'], 'byClient' => $byClient, 'userGuid' => $userGuid, 
						'byActive' => $byActive));
} catch (PDOException $e) {
	return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)', 
								 'orig' => 'MySQL error '.$e->getMessage()));
	exit;
}
$divs = array();
while ($row = ($req->fetch(PDO::FETCH_ASSOC)))
	$divs[] = array('guid' => formatGuid($row['guid']), 'name' => htmlspecialchars($row['name']));
		if (count($divs) == 0)
			return_format($format, array('state' => 'error', 'text' => "Договор '{$paramValues['contract']}' не найден"));
		else
			return_format($format, array('state' => 'ok', 'divisions' => $divs));
exit;
?>