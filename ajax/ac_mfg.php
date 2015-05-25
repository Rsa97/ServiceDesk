<?php
	header('Content-Type: application/json; charset=UTF-8');
	include('../config/db.php');
	session_start();
	if (!isset($_SESSION['user']) || $_SESSION['user']['rights'] != 'admin' || !isset($_REQUEST['term'])) {
		echo json_encode(array('select' => array()));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	// Подключаемся к MySQL
	$mysqli = mysqli_init(); 
	$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, 3306, null, MYSQLI_CLIENT_FOUND_ROWS);
	if ($mysqli->connect_error) {
		echo json_encode(array('select' => array()));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$select = array();	
	$term = '%'.$_REQUEST['term'].'%';
	$req = $mysqli->prepare("SELECT `name` FROM `equipmentManufacturers` WHERE `name` LIKE ? ORDER BY `name`");
	$req->bind_param('s', $term);
	$req->bind_result($val);
	if (!$req->execute()) {
		echo json_encode(array('select' => array()));
		exit;
	}
	while($req->fetch())
		$select[] = $val;
	$req->close(); 
	echo json_encode(array('select' => $select));
?>
