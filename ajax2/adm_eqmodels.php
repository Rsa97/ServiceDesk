<?php

	include('common.php');
	
	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	include('../config/db.php');
	session_start();
	if (!isset($_SESSION['user'])) {
		returnJson(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
	if ($_SESSION['user']['rights'] != 'admin') {
		returnJson(array('error' => 'Недостаточно прав для администрирования.', 'redirect' => '/index.html'));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	if (!isset($paramValues['call'])) {
		returnJson(array('error' => 'Ошибка в параметрах.'));
		exit;
	}
	// Подключаемся к MySQL
	$mysqli = mysqli_init(); 
	$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, 3306, null, MYSQLI_CLIENT_FOUND_ROWS);
	if ($mysqli->connect_error) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$ret = array();
	$id = 0;
	$lastId = 0;
	switch($paramValues['call']) {
		case 'init':
			break;
		case 'update':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['type'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$err = '';
			switch($paramValues['type']) {
				case 'type':
					if (!isset($paramValues['name']) || ($name = trim($paramValues['name'])) == '') {
						returnJson(array('error' => 'Ошибка в параметрах.'));
						exit;
					}
					if ($id == 0) {
						$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentTypes` (`name`) VALUES (?)");
						$req->bind_param('s', $name);
					} else {
						$req = $mysqli->prepare("UPDATE IGNORE `equipmentTypes` SET `name`= ? WHERE `id` = ?");
						$req->bind_param('si', $name, $id);
					}
					$err = 'Такой тип уже есть или ошибка в параметрах';
					break;
				case 'subType':
					if (!isset($paramValues['name']) || ($name = trim($paramValues['name'])) == '') {
						returnJson(array('error' => 'Ошибка в параметрах.'));
						exit;
					}
					if ($id == 0) {
						if (!isset($paramValues['parent']) || ($parent = $paramValues['parent']) <= 0) {
							returnJson(array('error' => 'Ошибка в параметрах.'));
							exit;
						}
						$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentSubTypes` (`description`, `equipmentTypes_id`) VALUES (?, ?)");
						$req->bind_param('si', $name, $parent);
					} else {
						$req = $mysqli->prepare("UPDATE IGNORE `equipmentSubTypes` SET `description`= ? WHERE `id` = ?");
						$req->bind_param('si', $name, $id);
					}
					$err = 'Такой подтип уже есть для этого типа или ошибка в параметрах';
					break;
				case 'model':
					if (!isset($paramValues['mfg']) || ($mfg = trim($paramValues['mfg'])) == '' ||
						!isset($paramValues['model']) || ($model = trim($paramValues['model'])) == '') {
						returnJson(array('error' => 'Ошибка в параметрах.'));
						exit;
					}
					$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentManufacturers` (`name`) VALUES (?)");
					$req->bind_param('s', $mfg);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					$req = $mysqli->prepare("SELECT `id` FROM `equipmentManufacturers` WHERE `name` = ?");
					$req->bind_param('s', $mfg);
					$req->bind_result($mfgId);
					if (!$req->execute() || !$req->fetch()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($id == 0) {
						if (!isset($paramValues['parent']) || ($parent = $paramValues['parent']) <= 0) {
							returnJson(array('error' => 'Ошибка в параметрах.'));
							exit;
						}
						$req = $mysqli->prepare("INSERT IGNORE INTO `equipmentModels` (`name`, `equipmentSubTypes_id`, `equipmentManufacturers_id`) VALUES (?, ?, ?)");
						$req->bind_param('sii', $model, $parent, $mfgId);
					} else {
						$req = $mysqli->prepare("UPDATE IGNORE `equipmentModels` SET `name`= ?, `equipmentManufacturers_id` = ? WHERE `id` = ?");
						$req->bind_param('sii', $model, $mfgId, $id);
					}
					$err = 'Такое название оборудования уже есть в данном подтипе';
					break; 
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				returnJson(array('error' => $err, 'id' => $id, 'insert_id' => $mysqli->insert_id, 'affected_rows' => $mysqli->affected_rows));
				exit;
			}
			$req->close();
			returnJson(array('id' => $lastId));
			exit;
			break;			
		case 'del':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0 ||
				!isset($paramValues['type'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$err = '';
			switch($paramValues['type']) {
				case 'type':
					$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentTypes` WHERE `id` = ?");
					$err = 'В типе есть подтипы или ошибка в параметрах';
					break;
				case 'subType': 
					$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentSubTypes` WHERE `id` = ?");
					$err = 'В подтипе есть модели или ошибка в параметрах';
					break;
				case 'model': 
					$req = $mysqli->prepare("DELETE IGNORE FROM `equipmentModels` WHERE `id` = ?");
					$err = 'Модель используется у клиентов или ошибка в параметрах';
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => $err));
				exit;
			}
			$req->close();
			returnJson(array('ok' => 1));
			exit;
			break;
		case 'moveSubtype':
			if (!isset($paramValues['type']) || ($type = $paramValues['type']) <= 0 ||
				!isset($paramValues['sub']) || ($subType = $paramValues['sub']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE `equipmentSubTypes` SET `equipmentTypes_id` = ? WHERE `id` = ?");
			$req->bind_param('ii', $type, $subType);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			$req->close();
			returnJson(array('ok' => 1));
			exit;
			break;			 
		case 'moveModel':
			if (!isset($paramValues['sub']) || ($subType = $paramValues['sub']) <= 0 ||
				!isset($paramValues['model']) || ($model = $paramValues['model']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE `equipmentModels` SET `equipmentSubTypes_id` = ? WHERE `id` = ?");
			$req->bind_param('ii', $subType, $model);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			$req->close();
			returnJson(array('ok' => 1));
			exit;
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `t`.`guid`, `t`.`name`, `st`.`guid`, `st`.`name`, `mfg`.`name`, `m`.`guid`, ".
							"`m`.`name`, `eq`.`equipmentModel_guid` ".
							"FROM `equipmentTypes` AS `t` ".
							"LEFT JOIN `equipmentSubTypes` AS `st` ON `st`.`equipmentType_guid` = `t`.`guid` ".
							"LEFT JOIN `equipmentModels` AS `m` ON `m`.`equipmentSubType_guid` = `st`.`guid` ".
							"LEFT JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`guid` = `m`.`equipmentManufacturer_guid` ".
							"LEFT JOIN (".
								"SELECT DISTINCT `equipmentModel_guid` FROM `equipment` ".
							") AS `eq` ON `eq`.`equipmentModel_guid` = `m`.`guid` ". 
							"ORDER BY `t`.`name`, `st`.`name`, `mfg`.`name`, `m`.`name`");
	$req->bind_result($typeId, $typeName, $subId, $subName, $mfgName, $modelId, $modelName, $inUse);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tree = array();
	$last = 0;
	$i = 0;
	$req->store_result();
	$lastType = 0;
	$types = array();
	$numTypes = -1;
	while ($req->fetch()) {
		$typeId = formatGuid($typeId);
		$subId = formatGuid($subId);
		$modelId = formatGuid($modelId);
		if ($lastType != $typeId) {
			$types[] = array('id' => $typeId, 'name' => $typeName, 'subtypes' => array());
			$numTypes++;
			$numSubTypes = -1;
			$lastSub = 0;
			$lastType = $typeId;
		}
		if ($subName != '') {
			if ($lastSub != $subId) {
				$types[$numTypes]['subtypes'][] = array('id' => $subId, 'name' => $subName, 'models' => array());
				$numSubTypes++;
				$lastSub = $subId;
			}
			if ($modelName != '') {
				$model = array('id' => $modelId, 'mfg' => $mfgName, 'model' => $modelName);
				if ($inUse != '')
					$model['notDel'] = 1;
				$types[$numTypes]['subtypes'][$numSubTypes]['models'][] = $model; 
			}
		}
	}
	returnJson(array('types' => $types));
?>