<?php
	header('Content-Type: application/json; charset=UTF-8');
	include('../config/db.php');
	session_start();
	if (!isset($_SESSION['user'])) {
		echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
	if ($_SESSION['user']['rights'] != 'admin') {
		echo json_encode(array('error' => 'Недостаточно прав для администрирования.', 'redirect' => '/index.html'));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	if (!isset($_REQUEST['call'])) {
		echo json_encode(array('error' => 'Ошибка в параметрах.'));
		exit;
	}
	// Подключаемся к MySQL
	$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	if ($mysqli->connect_error) {
		trigger_error("Ошибка подключения к серверу MySQL ({$mysqli->connect_errno})  {$mysqli->connect_error}");
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$ret = array();
	$id = 0;
	switch($_REQUEST['call']) {
		case 'init':
			break;
		case 'updateService':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addService':
			if (!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '' ||
				!isset($_REQUEST['shortName']) || ($shortname = $_REQUEST['shortName']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `services` (`name`, `shortname`) VALUES (?, ?)");
				$req->bind_param('ss', $name, $shortname);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `services` SET `name` = ?, `shortname` = ? WHERE `id` = ?");
				$req->bind_param('ssi', $name, $shortname, $id);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				echo json_encode(array('error' => 'Сервис с таким именем уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			
		case 'delService':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `services` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Сервис используется в договорах или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `id`, `name`, `shortname` FROM `services` ORDER BY `name`");
	$req->bind_result($id, $name, $shortname);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='servTbl'>".
				"<thead><tr><th><th>Название<th>Сокращение".
				"<tbody>";
	while ($req->fetch())
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}<td>{$shortname}";
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td></table>";
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>	