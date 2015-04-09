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
		case 'addLevel':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) == '' || 
				!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '' ||
				!isset($_REQUEST['desc'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$desc = $_REQUEST['desc'];
			$req = $mysqli->prepare("INSERT IGNORE INTO `slaCriticalLevels` (`id`, `name`, `description`) VALUES (?, ?, ?)");
			$req->bind_param('sss', $id, $name, $desc);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'updateLevel':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) == '' ||
				!isset($_REQUEST['newId']) || ($newId = $_REQUEST['newId']) == '' || 
				!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '' ||
				!isset($_REQUEST['desc'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$desc = $_REQUEST['desc'];
			$req = $mysqli->prepare("UPDATE IGNORE `slaCriticalLevels` SET `id` = ?, `name` = ?, `description` = ? ".
									" WHERE `id` = ?");
			$req->bind_param('ssss', $newId, $name, $desc, $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'delLevel':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `slaCriticalLevels` WHERE `id` = ?");
			$req->bind_param('s', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Уровень критичности используется в договорах или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$letters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$req = $mysqli->prepare("SELECT `id`, `name`, `description` ".
							  "FROM `slaCriticalLevels` ".
							  "ORDER BY `id`");
	$req->bind_result($id, $name, $desc);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='slaLvlTbl'>".
				"<thead><tr><th><th>Идентфикатор<th>Название<th>Описание<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td class='tdBtns'><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td class='tdId'>{$id}<td class='tdName'>{$name}<td class='tdDesc'>${desc}";
		$letters = str_replace($id, '', $letters);
	}
	$tbl .= "<tr data-id='.'><td class='tdBtns'><span class='ui-icon ui-icon-plusthick'> </span><td class='tdId'>".
			"<td class='tdName'>Добавить<td class='tdDesc'></table>".
			"<input type='hidden' value='{$letters}' id='letters'>";
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>