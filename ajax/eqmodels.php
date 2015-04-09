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
			$typesList = '';
			$subType = -1;
			while ($req->fetch()) {
				if ($prevType != $typeId) {
					$typesList .= "<optgroup label='{$typeName}'>";
					$prevType = $typeId;
				}
				if ($subTypeName != '') {
					$typesList .= "<option value='{$subTypeId}'>&nbsp;&nbsp;&nbsp;{$subTypeName}";
					if ($subType == -1)
						$subType = $subTypeId;
				}
			}
			$req->close();
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `equipmentManufacturers` ORDER BY `name`");
			$req->bind_result($mfgId, $mfgName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$mfgList = '';
			$mfg = -1;
			while ($req->fetch()) {
				$mfgList .= "<option value='{$mfgId}'>&nbsp;&nbsp;&nbsp;{$mfgName}";
				if ($mfg == -1)
					$mfg = $mfgId;
			}
			$req->close();
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `equipmentModels` ".
									  "WHERE `equipmentSubTypes_id` = ? AND `equipmentManufacturers_id` = ? ".
									  "ORDER BY `name`");
			$req->bind_param('ii', $subType, $mfg);
			$req->bind_result($modelId, $modelName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$list = '';
			while ($req->fetch())
				$list .= "<li data-id='{$modelId}' class='model'><span class='ui-icon ui-icon-pencil'> </span>".
						 "<span class='ui-icon ui-icon-trash'> </span><span class='desc'>{$modelName}</span>";
			$res = "<div id='mdSelectors'>Тип оборудования: <select id='mdSubType'>{$typesList}</select>".
				   "&nbsp;&nbsp;&nbsp;Производитель: <select id='mdMfg'>{$mfgList}</select>".
				   "<span class='ui-icon ui-icon-plusthick'> </span><span class='ui-icon ui-icon-pencil'> </span>".
				   "<span class='ui-icon ui-icon-trash'> </span></div>".
				   "<ul id='models'>{$list}<li class='model'><span class='ui-icon ui-icon-plusthick'> </span>Добавить</ul>";
			echo json_encode(array('content' => $res));
			exit;
			break;
		case 'change':
			if (!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) || 
				!isset($_REQUEST['mfg']) || ($_REQUEST['mfg'] <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$subType = $_REQUEST['subType'];
			$mfg = $_REQUEST['mfg'];
			break;
		case 'addMfg':
			if (!isset($_REQUEST['name']) || ($_REQUEST['name'] == '') || 
				!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0)) { 
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$name = $_REQUEST['name'];
			$subType = $_REQUEST['subType'];
			$req = $mysqli->prepare("INSERT IGNORE INTO  `equipmentManufacturers` (`name`) VALUES (?)");
			$req->bind_param('s', $name);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->insert_id <= 0) {
				echo json_encode(array('error' => 'Такой производитель уже существует или ошибка в параметрах.'));
				exit;
			}
			$mfg = $mysqli->insert_id;
			break;
		case 'renMfg':
			if (!isset($_REQUEST['name']) || ($_REQUEST['name'] == '') || 
				!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) ||
				!isset($_REQUEST['mfg']) || ($_REQUEST['mfg'] <= 0)) { 
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$name = $_REQUEST['name'];
			$subType = $_REQUEST['subType'];
			$mfg = $_REQUEST['mfg'];
			$req = $mysqli->prepare("UPDATE IGNORE `equipmentManufacturers` SET `name` = ? WHERE `id` = ?");
			$req->bind_param('si', $name, $mfg);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Такой производитель уже существует или ошибка в параметрах.'));
				exit;
			}
			break;
		case 'delMfg':
			if (!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) ||
				!isset($_REQUEST['mfg']) || ($_REQUEST['mfg'] <= 0)) { 
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$subType = $_REQUEST['subType'];
			$mfg = $_REQUEST['mfg'];
			$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentManufacturers` WHERE `id` = ?");
			$req->bind_param('i', $mfg);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Есть устройства этого производителя или ошибка в параметрах.'));
				exit;
			}
			$mfg = -1;
			break;
		case 'addModel':
			if (!isset($_REQUEST['name']) || ($_REQUEST['name'] == '') || 
				!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) ||
				!isset($_REQUEST['mfg']) || ($_REQUEST['mfg'] <= 0)) { 
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$name = $_REQUEST['name'];
			$subType = $_REQUEST['subType'];
			$mfg = $_REQUEST['mfg'];
			$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentModels` (`name`, `equipmentSubTypes_id`, `equipmentManufacturers_id`) VALUES (?, ?, ?)");
			$req->bind_param('sii', $name, $subType, $mfg);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->insert_id <= 0) {
				echo json_encode(array('error' => 'Уже есть такая модель этого производителя и типа или ошибка в параметрах.'));
				exit;
			}
			break;
		case 'renModel':
			if (!isset($_REQUEST['model']) || ($_REQUEST['model'] == '') ||
				!isset($_REQUEST['name']) || ($_REQUEST['name'] == '') || 
				!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) ||
				!isset($_REQUEST['mfg']) || ($_REQUEST['mfg'] <= 0)) { 
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$model = $_REQUEST['model'];
			$name = $_REQUEST['name'];
			$subType = $_REQUEST['subType'];
			$mfg = $_REQUEST['mfg'];
			$req = $mysqli->prepare("UPDATE IGNORE `equipmentModels` SET `name` = ? WHERE `id` = ?");
			$req->bind_param('si', $name, $model);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Уже есть такая модель этого производителя и типа или ошибка в параметрах.'));
				exit;
			}
			break;
		case 'delModel':
			if (!isset($_REQUEST['model']) || ($_REQUEST['model'] == '') ||
				!isset($_REQUEST['subType']) || ($_REQUEST['subType'] <= 0) ||
				!isset($_REQUEST['mfg']) || ($_REQUEST['mfg'] <= 0)) { 
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$model = $_REQUEST['model'];
			$subType = $_REQUEST['subType'];
			$mfg = $_REQUEST['mfg'];
			$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentModels` WHERE `id` = ?");
			$req->bind_param('i', $model);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Есть оборудование такой модели или ошибка в параметрах.'));
				exit;
			}
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	switch ($_REQUEST['call']) {
		case 'addMfg':
		case 'renMfg':
		case 'delMfg':
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `equipmentManufacturers` ORDER BY `name`");
			$req->bind_result($mfgId, $mfgName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$mfgList = '';
			while ($req->fetch()) {
				if ($mfg == -1)
					$mfg = $mfgId;
				$mfgList .= "<option value='{$mfgId}'".($mfgId == $mfg ? ' selected' : '').">&nbsp;&nbsp;&nbsp;{$mfgName}";
			}
			$ret['mdMfg'] = $mfgList;
			$req->close();
				default:
			break;
	}
	$req = $mysqli->prepare("SELECT `id`, `name` FROM `equipmentModels` ".
							  "WHERE `equipmentSubTypes_id` = ? AND `equipmentManufacturers_id` = ? ".
							  "ORDER BY `name`");
	$req->bind_param('ii', $subType, $mfg);
	$req->bind_result($modelId, $modelName);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$list = '';
	while ($req->fetch())
		$list .= "<li data-id='{$modelId}' class='model'><span class='ui-icon ui-icon-pencil'> </span>".
				 "<span class='ui-icon ui-icon-trash'> </span><span class='desc'>{$modelName}</span>";
	$req->close();
	$list .= "<li class='model'><span class='ui-icon ui-icon-plusthick'> </span>Добавить";
	$ret['models'] = $list;
	echo json_encode($ret);
?>