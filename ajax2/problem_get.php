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
	$req = $db->prepare("SELECT `addProblem` FROM `contractDivisions` WHERE `guid` = UNHEX(REPLACE(:divisionGuid, '-', ''))");
	$req->execute(array('divisionGuid' => $paramValues['division']));
	if ($row = $req->fetch(PDO::FETCH_NUM))  
		$problem = $row[0];
	else 
		$problem = '';
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
echo json_encode(array('_apProblem' => $problem));
exit;
?>