<?php

	include("{$_SERVER['DOCUMENT_ROOT']}/config/db.php");
	
	// Подключаемся к MySQL
	try {
		$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass,
					  array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	} catch (PDOException $e) {
		header("{$_SERVER['SERVER_PROTOCOL']} 500 Internal Server Error");
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL connection error".$e->getMessage()));
		exit;
	}
	$db->exec("SET NAMES utf8");
	
?>

	
