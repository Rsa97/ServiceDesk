<?php

header('Content-Type: application/json; charset=UTF-8');

include 'init.php';
include 'func_calcTime.php';

$date = null;
if (isset($paramValues['id'])) {
	try {
		$req = $db->prepare("SELECT `createdAt` FROM `requests` WHERE `id` = :id");
		$req->execute(array('id' => $paramValues['id']));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	if ($row = $req->fetch(PDO::FETCH_NUM))
		$date = date_create_from_format('Y-m-d H:i:s', $row[0]);
}
$ret = calcTime($db, $paramValues['division'], $paramValues['service'], $paramValues['slaLevel'], 0, $date);
echo json_encode(array('_createdAt' => $ret['createdAt'], '_repairBefore' => $ret['repairBefore'], 'date' => $date));
?>