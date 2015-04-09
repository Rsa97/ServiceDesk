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
	$openType = -1;
	switch ($_REQUEST['call']) {
		case 'init':
			break;
		case 'addType':
			if (!isset($_REQUEST['name']) || ($_REQUEST['name'] == '')) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit; 
			}
			$name = $_REQUEST['name'];
			$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentTypes` (`name`) VALUES(?)");
			$req->bind_param('s', $name);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$openType = $mysqli->insert_id;
			if ($openType == 0) {
				echo json_encode(array('error' => 'Такой тип уже задан.'));
				exit;
			}
			$req->close();
			break;
		case 'addSubType':
			if (!isset($_REQUEST['name']) || ($_REQUEST['name'] == '') || !isset($_REQUEST['toType']) || ($_REQUEST['toType'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$name = $_REQUEST['name'];
			$openType = $_REQUEST['toType'];
			$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentSubTypes` (`description`, `equipmentTypes_id`) VALUES(?, ?)");
			$req->bind_param('si', $name, $openType);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->insert_id == 0) {
				echo json_encode(array('error' => 'Такой подтип уже задан в этом типе.'));
				exit;
			}
			$req->close();
			break;
		case 'delType':
			if (!isset($_REQUEST['type']) || ($_REQUEST['type'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$type = $_REQUEST['type'];
			$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentTypes` WHERE `id` =  ?");
			$req->bind_param('i', $type);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows == 0) {
				echo json_encode(array('error' => 'Тип содержит подтипы или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'delSubType':
			if (!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) || !isset($_REQUEST['type']) || ($_REQUEST['type'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$subType = $_REQUEST['subType'];
			$openType = $_REQUEST['type'];
			$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentSubTypes` WHERE `id` = ?");
			$req->bind_param('i', $subType);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows == 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'moveSubType':
			if (!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) || 
				!isset($_REQUEST['toType']) || ($_REQUEST['toType'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$subType = $_REQUEST['subType'];
			$openType = $_REQUEST['toType'];
			$req = $mysqli->prepare("UPDATE IGNORE `equipmentSubTypes` SET `equipmentTypes_id` = ? WHERE `id` = ?");
			$req->bind_param('ii', $openType, $subType);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows == 0) {
				echo json_encode(array('error' => 'Тип уже содержит такой подтип или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'renType':
			if (!isset($_REQUEST['type']) || ($_REQUEST['type'] <= 0) || 
				!isset($_REQUEST['newName']) || ($_REQUEST['newName'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$name = $_REQUEST['newName'];
			$openType = $_REQUEST['type'];
			$req = $mysqli->prepare("UPDATE IGNORE `equipmentTypes` SET `name` = ? WHERE `id` = ?");
			$req->bind_param('si', $name, $openType);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows == 0) {
				echo json_encode(array('error' => 'Тип с таким названием уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'renSubType':
			if (!isset($_REQUEST['type']) || ($_REQUEST['type'] <= 0) || 
				!isset($_REQUEST['newName']) || ($_REQUEST['newName'] <= 0) ||
				!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$name = $_REQUEST['newName'];
			$openType = $_REQUEST['type'];
			$subType = $_REQUEST['subType'];
			$req = $mysqli->prepare("UPDATE IGNORE `equipmentSubTypes` SET `description` = ? WHERE `id` = ?");
			$req->bind_param('si', $name, $subType);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows == 0) {
				echo json_encode(array('error' => 'Подтип с таким названием уже существует в этом типе или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	} 
	$req = $mysqli->prepare("SELECT `st`.`id`, `st`.`description`, `t`.`id`, `t`.`name` ". 
	   	                      "FROM `equipmentTypes` AS `t` ".
							  "LEFT JOIN `equipmentSubTypes` AS `st` ON `t`.`id` = `st`.`equipmentTypes_id` ".
							  "ORDER BY `t`.`name`, `st`.`description`");
	$req->bind_result($subTypeId, $subTypeName, $typeId, $typeName);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$prevType = -1;
	$list = '';
	$sub = '';
	while ($req->fetch()) {
		if ($prevType != $typeId) {
			if ($prevType != -1)
				$list .= "<ul>{$sub}<li class='eqSubType'><span class='ui-icon ui-icon-plusthick'> </span>Добавить</ul>";
			$sub = '';
			$list .= "<li class='eqType".($typeId == $openType ? '' : ' collapsed')."' data-id='{$typeId}'><span class='collapse ui-icon ui-icon-folder-".($typeId == $openType ? 'open' : 'collapsed')."'> </span>".
					 "<span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span><span class='desc'>{$typeName}</span>";
			$prevType = $typeId;
		}
		if ($subTypeName != '')
			$sub .= "<li data-id='{$subTypeId}' class='eqSubType'><span class='ui-icon ui-icon-transferthick-e-w'> </span>".
					"<span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span><span class='desc'>{$subTypeName}</span>";
	}
	$req->close();
	$list .= "<ul>{$sub}<li class='eqSubType'><span class='ui-icon ui-icon-plusthick'> </span>Добавить</ul>";
	$list = "<ul>{$list}<li class='eqType'><span class='ui-icon ui-icon-plusthick'> </span>Добавить</ul>";
	echo json_encode(array('content' => $list));
?>