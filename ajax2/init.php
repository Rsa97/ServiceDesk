<?php

include '../config/db.php';

if ('cli' != php_sapi_name()) {
	session_start();
	if (!isset($_SESSION['user']) || !isset($_SESSION['filter'])) {
   		echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}

	// Строим фильтр разрешения доступа
	$rights = $_SESSION['user']['rights'];
	$userGuid =  $_SESSION['user']['myID'];
	$partnerGuid = $_SESSION['user']['partner'];

	$_SESSION['time'] = time();
	session_commit();

	$byClient = ($rights == 'client' ? 1 : 0);
	$byPartner = ($rights == 'partner' ? 1 : 0);
	$byActive = ($rights == 'admin' ? 0 : 1);
	$byEngineer = ($rights == 'engineer' ? 1 : 0);
}
// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass,
				  array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
$db->exec("SET NAMES utf8");

?>