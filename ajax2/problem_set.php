<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

if (!isset($_SESSION['user']) || ($_SESSION['user']['rights'] != 'admin' && 
	$_SESSION['user']['rights'] != 'operator' && $_SESSION['user']['rights'] != 'engeneer')) {
	echo json_encode(array('error' => 'Недостаточно прав'));
	exit;
}

try {
	$problem = trim($paramValues['problem']);
	if ('*' == $paramValues['division']) {
		$req = $db->prepare("UPDATE IGNORE `contractDivisions` ".
								"SET `addProblem` = CONCAT(`addProblem`, IF(`addProblem` = '', '', '\n'), :problem) ".
								"WHERE `contract_guid` = UNHEX(REPLACE(:contractGuid, '-', '')) AND `isDisabled` = 0");
		$req->execute(array('contractGuid' => $paramValues['contract'], 'problem' => $problem));
	} else {
		$req = $db->prepare("UPDATE IGNORE `contractDivisions` ".
								"SET `addProblem` = :problem ".
								"WHERE `guid` = UNHEX(REPLACE(:divisionGuid, '-', ''))");
		$req->execute(array('divisionGuid' => $paramValues['division'], 'problem' => $problem));
	}
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
echo json_encode(array('ok' => 'ok'));
exit;
?>