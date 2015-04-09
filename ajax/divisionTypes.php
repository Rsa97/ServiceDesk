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
	switch($_REQUEST['call']) {
		case 'init':
			break;
		case 'delType':
			if (!isset($_REQUEST['id']) || ($id = intval($_REQUEST['id'])) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `divisionTypes` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Тип филиала используется в договорах или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'updateType':
			if (!isset($_REQUEST['id']) || ($id = intval($_REQUEST['id'])) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addType':
			if (!isset($_REQUEST['name']) || ($name = trim($_REQUEST['name'])) == '' || !isset($_REQUEST['comment'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$comment = trim($_REQUEST['comment']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `divisionTypes` (`name`, `comment`) VALUES (?, ?)");
				$req->bind_param('ss', $name, $comment);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `divisionTypes` SET `name` = ?, `comment` = ? WHERE `id` = ?");
				$req->bind_param('ssi', $name, $comment, $id);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				echo json_encode(array('error' => 'Тип филиала с таким именем уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `id`, `name`, `comment` ". 
							  "FROM `divisionTypes` ".
							  "ORDER BY `name`");
	$req->bind_result($id, $name, $comment);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='divTypesTbl'><thead><tr><th><th>Название<th>Примечание<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}<td>{$comment}";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td><td></table>";
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>	