<?php
	header('Content-Type: application/json; charset=UTF-8');
	
	function minutes_to_str($mins) {
		$t = '';
		$str = '';
		if (($mins%60) != 0) {
			$str = ($mins%60).'m';
			$t = ' ';
		}
		if ($mins >= 60 && ($mins%1440) != 0) {
			$str = (floor($mins/60)%24).'h'.$t.$str;
			$t = '';
		}
		if ($mins >= 1440) {
			$str = (floor($mins/1440)).'d'.$t.$str;
		}
		return $str;
	}
	
	function str_to_minutes($str) {
		if (preg_match('/^(:?\d+\s*d)?\s*(:?\d+\s*h)?\s*(:?\d+\s*m)?$/', $str) != 1)
			return -1;
		$min = 0;
		if (preg_match('/(\d+)\s*d/',$str,$match))
			$min += $match[1]*1440;
		if (preg_match('/(\d+)\s*h/',$str,$match))
			$min += $match[1]*60;
		if (preg_match('/(\d+)\s*m/',$str,$match))
			$min += $match[1];
		return($min);
	}

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
		case 'updateSLA':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addSLA':				 
			if (!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '' ||
				!isset($_REQUEST['toReact']) || ($toReact = str_to_minutes($_REQUEST['toReact'])) == -1 ||
				!isset($_REQUEST['toFix']) || ($toFix = str_to_minutes($_REQUEST['toFix'])) == -1 ||
				!isset($_REQUEST['toRestore']) || ($toRestore = str_to_minutes($_REQUEST['toRestore'])) == -1 ||
				!isset($_REQUEST['quality']) || preg_match('/(\d+)\s*%?/',$_REQUEST['quality'],$match) != 1) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$quality = $match[1];
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `sla` (`name`, `reactionTime`, `fixTime`, ".
										"`fullRestoreTime`, `quality`) VALUES (?, ?, ?, ?, ?)");
				$req->bind_param('siiii', $name, $toReact, $toFix, $toRestore, $quality);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `sla` SET `name` = ?, `reactionTime` = ?, `fixTime` = ?, ".
										"`fullRestoreTime` = ?, `quality` = ? WHERE `id` = ?");
				$req->bind_param('siiiii', $name, $toReact, $toFix, $toRestore, $quality, $id);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				echo json_encode(array('error' => 'SLA с таким именем уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'delSLA':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `sla` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'SLA используется в договорах или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `id`, `name`, `reactionTime`, `fixTime`, `fullRestoreTime`, `quality` ". 
							  "FROM `sla` ".
							  "ORDER BY `reactionTime`, `fixTime`, `fullRestoreTime`, `quality`");
	$req->bind_result($id, $name, $reactTime, $fixTime, $fullRestoreTime, $quality);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='slaTbl'>".
				"<thead><tr><th><th>Название<th>Время реакции<th>Время до <br>восстановления<th>Время до <br>завершения<th>Качество".
				"<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}<td>".minutes_to_str($reactTime)."<td>".minutes_to_str($fixTime)."<td>".
				minutes_to_str($fullRestoreTime)."<td>".$quality."%";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td></table>";
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>	