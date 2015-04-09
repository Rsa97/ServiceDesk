<?php
	header('Content-Type: application/json; charset=UTF-8');
	include('../config/db.php');
	session_start();
	if (!isset($_SESSION['user'])) {
		echo json_encode(array('select' => array()));
		exit;
	}
	if ($_SESSION['user']['rights'] != 'admin') {
		echo json_encode(array('select' => array()));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	if (!isset($_REQUEST['call']) || !isset($_REQUEST['term'])) {
		echo json_encode(array('select' => array()));
		exit;
	}
	// Подключаемся к MySQL
	$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	if ($mysqli->connect_error) {
		echo json_encode(array('select' => array()));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$select = array();	
	$term = '%'.$_REQUEST['term'].'%';
	switch($_REQUEST['call']) {
		case 'modelMfg':
			$req = $mysqli->prepare("SELECT `emf`.`name` ".
									  "FROM `equipmentModels` AS `emd` ".
									  "JOIN `equipmentManufacturers` AS `emf` ON `emd`.`equipmentManufacturers_id` = `emf`.`id` ".
									  "WHERE `emd`.`name` = ? LIMIT 1");
			$req->bind_param('s', $_REQUEST['term']);
			$req->bind_result($mfg);
			$mfg = "";
			if ($req->execute())
				$req->fetch();
			$req->close();
			echo json_encode(array('_mfg' => $mfg, 'ask' => $_REQUEST['term']));
			exit;
			break;
		case 'mfg':
			$req = $mysqli->prepare("SELECT `name` FROM `equipmentManufacturers` WHERE `name` LIKE ? ORDER BY `name`");
			$req->bind_param('s', $term);
			break;
		case 'model':
			if (!isset($_REQUEST['mfg'])) {
				echo json_encode(array('select' => array()));
				exit;
			}
			if (($mfg = $_REQUEST['mfg']) == '')
				$mfg = '%';
			$req = $mysqli->prepare("SELECT `emd`.`name` ".
									  "FROM `equipmentModels` AS `emd` ".
									  "JOIN `equipmentManufacturers` AS `emf` ON `emd`.`equipmentManufacturers_id` = `emf`.`id` ".
									  "WHERE `emd`.`name` LIKE ? AND `emf`.`name` LIKE ? ".
									  "ORDER BY `emd`.`name`");
			$req->bind_param('ss', $term, $mfg);
			break;
		default:
			echo json_encode(array('select' => array()));
			exit;
	}
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
