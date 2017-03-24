<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$card = isset($paramValues['card']) ? $paramValues['card'] : null;

try {
	$req = $db->prepare("SELECT `p`.`guid`, `p`.`name` ".
							"FROM `partners` AS `p` ".
							"JOIN `partnerDivisions` AS `pd` ON `pd`.`partner_guid` = `p`.`guid` ".
							"LEFT JOIN `requests` AS `rq` ON `rq`.`contractDivision_guid` = `pd`.`contractDivision_guid` ".
							"WHERE :card IS NULL OR `rq`.`id` = :card");
	$req->execute(array('card' => $card));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}

$list = "<ul>";
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($guid, $name) = $row;
	$guid = formatGuid($guid);
	$list .= "<li data-id='{$guid}'>".htmlspecialchars($name);
}
$list .= "<li data-id='0'>-- Отменить назначение --</ul>"; 
echo json_encode(array('selectPartnerList' => $list));
exit;
?>