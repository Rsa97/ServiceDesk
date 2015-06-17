<?php
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
	if (!isset($_REQUEST['call'])) {
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
	switch($_REQUEST['call']) {
		case 'init':
			break;
		case 'getlists1':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) ||($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($_REQUEST['field']) {
				case 'users':
					$req =  $mysqli->prepare("SELECT `id`, `firstName`, `secondName`, `middleName`, `partner_id` ".
												"FROM `users` ".
												"WHERE `rights` = 'partner' AND (`partner_id` != ? OR ISNULL(`partner_id`)) ".
													"AND `isDisabled` = 0 AND `loginDB` = 'mysql' ".
												"ORDER BY `secondName`, `firstName`, `middleName`");
					$req->bind_param('i', $id);
					$req->bind_result($uid, $gn, $fn, $mn, $partnerId);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					while ($req->fetch()) {
						$user = array('id' => $uid, 'name' => htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : '')));
						if ($partnerId != '')
							$user['mark'] = 'red';
						$total[] = $user; 
					}
					$req->close();
					$req =  $mysqli->prepare("SELECT `id`, `firstName`, `secondName`, `middleName` ".
												"FROM `users` ".
												"WHERE `rights` = 'partner' AND `partner_id` = ? AND `isDisabled` = 0 ".
												"ORDER BY `secondName`, `firstName`, `middleName`");
					$req->bind_param('i', $_REQUEST['id']);
					$req->bind_result($uid, $gn, $fn, $mn);									
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$selected = array();
					while ($req->fetch()) {
						$selected[] = array('id' => $uid, 'name' => htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : '')));
					}
					$req->close();
					returnJson(array('total' => $total, 'selected' => $selected, 'mode' => 'flat'));
					exit;
					break;
				case 'contracts':
					$req =  $mysqli->prepare("SELECT `cd`.`id`, `cd`.`name`, `c`.`number`, `ca`.`name`, `ac`.`partner_id` ".
												"FROM `contractDivisions` AS `cd` ".
												"LEFT JOIN `allowedContracts` AS `ac` ON `ac`.`contractDivisions_id` = `cd`.`id` ".
												"JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
												"JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
												"WHERE (`ac`.`partner_id` != ? OR `ac`.`partner_id` IS NULL) ".
												"ORDER BY `c`.`number`, `cd`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($divId, $divName, $contractNumber, $contragentName, $partnerId);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					$lastContract = '';
					$group = array();
					while ($req->fetch()) {
						$contract = htmlspecialchars($contractNumber.' - '.$contragentName);
						if ($contract != $lastContract) {
							if (count($group) > 0)
								$total[] = array('group' => $lastContract, 'items' => $group);
							$lastContract = $contract;
							$group = array();
						}
						$item = array('id' => $divId, 'name' => htmlspecialchars($divName));
						if ($partnerId != '')
							$item['mark'] = 'red';
						$group[] = $item;
					}
					if (count($group) > 0)
						$total[] = array('group' => $lastContract, 'items' => $group);
					$req =  $mysqli->prepare("SELECT `cd`.`id`, `cd`.`name`, `c`.`number`, `ca`.`name` ".
												"FROM `allowedContracts` AS `ac` ".
												"JOIN `contractDivisions` AS `cd` ON `cd`.`id` = `ac`.`contractDivisions_id` ".
												"JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
												"JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
												"WHERE `ac`.`partner_id` = ? ".
												"ORDER BY `c`.`number`, `cd`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($divId, $divName, $contractNumber, $contragentName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$selected = array();
					$lastContract = '';
					$group = array();
					while ($req->fetch()) {
						$contract = htmlspecialchars($contractNumber.' - '.$contragentName);
						if ($contract != $lastContract) {
							if (count($group) > 0)
								$selected[] = array('group' => $lastContract, 'items' => $group);
							$lastContract = $contract;
							$group = array(); 							
						}
						$group[] = array('id' => $divId, 'name' => htmlspecialchars($divName));
					}
					if (count($group) > 0)
						$selected[] = array('group' => $lastContract, 'items' => $group);
					returnJson(array('total' => $total, 'selected' => $selected, 'mode' => 'tree'));
					exit;
					break; 
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			break;
		case 'updatelists1':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$list = array();
			if (isset($_REQUEST['list'])) {
				$list = array();
				foreach ($_REQUEST['list'] as $uid) {
					if (preg_match('/^(\d+)$/', $uid, $match))
						$list[] = $match[1];
				}
			}
			switch($_REQUEST['field']) {
				case 'users':
					$list = join(',', $list);
					$req =  $mysqli->prepare("UPDATE `users` ".
												"SET `partner_id` = NULL ".
												"WHERE `partner_id` = ? AND `rights` = 'partner' ".($list != '' ? ("AND `id` NOT IN ({$list})") : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($list != '') {
						$req =  $mysqli->prepare("UPDATE `users` ".
													"SET `partner_id` = ? ".
													"WHERE `id` IN ({$list})");
						$req->bind_param('i', $id);
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
						$req->close();
					}
					break;
				case 'contracts':
					$req = $mysqli->prepare("DELETE FROM `allowedContracts` ".
												"WHERE `partner_id` = ? ".
												(count($list) != 0 ? ("AND `contractDivisions_id` NOT IN (".join(',', $list).")") : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					$req = $mysqli->prepare("INSERT IGNORE INTO `allowedContracts` (`partner_id`, `contractDivisions_id`) VALUES (?, ?)");
					$req->bind_param('ii', $id, $divId);
					foreach ($list as $divId) {
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
					}
					$req->close(); 
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			$lastId = $id;
			break;
		case 'update':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0 ||
				!isset($_REQUEST['type'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$err = '';
			switch($_REQUEST['type']) {
				case 'type':
					if (!isset($_REQUEST['name']) || ($name = trim($_REQUEST['name'])) == '') {
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
					if (!isset($_REQUEST['name']) || ($name = trim($_REQUEST['name'])) == '') {
						returnJson(array('error' => 'Ошибка в параметрах.'));
						exit;
					}
					if ($id == 0) {
						if (!isset($_REQUEST['parent']) || ($parent = $_REQUEST['parent']) <= 0) {
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
					if (!isset($_REQUEST['mfg']) || ($mfg = trim($_REQUEST['mfg'])) == '' ||
						!isset($_REQUEST['model']) || ($model = trim($_REQUEST['model'])) == '') {
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
						if (!isset($_REQUEST['parent']) || ($parent = $_REQUEST['parent']) <= 0) {
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
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0 ||
				!isset($_REQUEST['type'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$err = '';
			switch($_REQUEST['type']) {
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
			if (!isset($_REQUEST['type']) || ($type = $_REQUEST['type']) <= 0 ||
				!isset($_REQUEST['sub']) || ($subType = $_REQUEST['sub']) <= 0) {
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
			if (!isset($_REQUEST['sub']) || ($subType = $_REQUEST['sub']) <= 0 ||
				!isset($_REQUEST['model']) || ($model = $_REQUEST['model']) <= 0) {
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
	$req = $mysqli->prepare("SELECT `t`.`id`, `t`.`name`, `st`.`id`, `st`.`description`, `mfg`.`name`, `m`.`id`, ".
							"`m`.`name`, `eq`.`equipmentModels_id` ".
							"FROM `equipmentTypes` AS `t` ".
							"LEFT JOIN `equipmentSubTypes` AS `st` ON `st`.`equipmentTypes_id` = `t`.`id` ".
							"LEFT JOIN `equipmentModels` AS `m` ON `m`.`equipmentSubTypes_id` = `st`.`id` ".
							"LEFT JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`id` = `m`.`equipmentManufacturers_id` ".
							"LEFT JOIN (".
								"SELECT DISTINCT `equipmentModels_id` FROM `equipment`".
							") AS `eq` ON `eq`.`equipmentModels_id` = `m`.`id` ". 
							"ORDER BY `t`.`name`, `st`.`description`, `mfg`.`name`, `m`.`name`");
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