<?php

include 'api_functions.php';
include 'common.php';
include '../config/db.php';

$format = (isset($paramValues['format']) ? $paramValues['format'] : 'json');  

if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
  	return_format($format, array('state' => 'error', 'text' => 'Доступ к api возможен только по протоколу HTTPS'));
	exit;
}

// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass,
				  array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (PDOException $e) {
	return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)', 
								 'orig' => 'MySQL connection error '.$e->getMessage()));
	exit;
}
$db->exec("SET NAMES utf8");

// Проверка токена
try {
	$req = $db->prepare('SELECT `guid`, `rights` FROM `users` WHERE `token` = :token');
	$req->execute(array('token' => $paramValues['token']));
} catch (PDOException $e) {
	return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)', 
								 'orig' => 'MySQL error '.$e->getMessage()));
	exit;
}
if ($row = $req->fetch(PDO::FETCH_NUM)) {
	$userGuid = formatGuid($row[0]);
	$rights = $row[1];
} else {
	return_format($format, array('state' => 'error', 'text' => 'Токен недействителен, для получения нового обратитесь к администратору'));
   	exit;
}

$byClient = ($rights == 'client' ? 1 : 0);
$byPartner = ($rights == 'partner' ? 1 : 0);
$byActive = ($rights == 'admin' ? 0 : 1);
$byEngineer = ($rights == 'engineer' ? 1 : 0);


?>