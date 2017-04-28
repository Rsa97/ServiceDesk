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
	$lastId = '';
	$setService = 0;
	$divId = $paramValues['divId']; 
	switch($paramValues['call']) {
		case 'init':
			break;
		case 'getlists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) ||($id = $paramValues['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($paramValues['field']) {
				case 'equipment':
					$req =  $mysqli->prepare("SELECT `eq`.`serviceNumber`, `m`.`name`, `mfg`.`name`, `eq`.`onService`, `eq`.`workplace_id` ".
												"FROM `equipment` AS `eq` ".
												"JOIN `equipmentModels` AS `m` ON `m`.`id` = `eq`.`equipmentModels_id` ".
												"JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`id` = `m`.`equipmentManufacturers_id` ".
												"WHERE `eq`.`contractDivisions_id` = ? ".
												"ORDER BY `eq`.`serviceNumber`");
					$req->bind_param('i', $divId);
					$req->bind_result($servNum, $model, $mfg, $onService, $wpId);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					$selected = array();
					while ($req->fetch()) {
						$eq = array('id' => $servNum, 'name' => htmlspecialchars($servNum.' - '.$mfg.' '.$model));
						if ($onService == 0)
							$eq['mark'] = 'red';
						else if ($wpId == '')
							$eq['mark'] = 'green';
						if ($wpId == $id)
							$selected[] = $eq;
						else 
							$total[] = $eq; 
					}
					$req->close();
					returnJson(array('total' => $total, 'selected' => $selected, 'mode' => 'flat'));
					exit;
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			break;
		case 'updatelists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$list = array();
			if (isset($paramValues['list'])) {
				$list = array();
				foreach ($paramValues['list'] as $uid) {
					if (preg_match('/^(\d+)$/', $uid, $match))
						$list[] = $match[1];
				}
			}
			switch($paramValues['field']) {
				case 'equipment':
					$list = join(',', $list);
					$req =  $mysqli->prepare("UPDATE `equipment` ".
												"SET `workplace_id` = NULL ".
												"WHERE `workplace_id` = ? ".($list != '' ? ("AND `serviceNumber` NOT IN ({$list})") : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($list != '') {
						$req =  $mysqli->prepare("UPDATE `equipment` ".
													"SET `workplace_id` = ? ".
													"WHERE `serviceNumber` IN ({$list})");
						$req->bind_param('i', $id);
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
						$req->close();
					}
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			$lastId = $id;
			break;
		case 'update':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['name']) || ($name = trim($paramValues['name'])) == '' ||
				!isset($paramValues['description'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$desc = trim($paramValues['description']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `divisionWorkplaces` (`divisions_id`, `name`, `description`) ".
												"VALUES (?, ?, ?)");
				$req->bind_param('iss', $divId, $name, $desc);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `divisionWorkplaces` SET `name` = ?, `description` = ? ".
												"WHERE `id` = ?");
				$req->bind_param('ssi', $name, $desc, $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = $serviceNum;
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'del':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `divisionWorkplaces` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `wp`.`guid`, `wp`.`name`, `wp`.`description`, `u`.`workplace_guid` ".
							"FROM `divisionWorkplaces` AS `wp` ".
							"LEFT JOIN ( ".
								"SELECT DISTINCT `workplace_guid` FROM `equipmentWorkplaceLog` ". 
								") AS `u` ON `u`.`workplace_guid` = `wp`.`guid` ".
							"WHERE `wp`.`division_guid` = UNHEX(REPLACE(?, '-', '')) ".
							"ORDER BY `wp`.`name`");
	$req->bind_param('s', $divId);
	$req->bind_result($wpId, $wpName, $wpDesc, $inUse);
	$req1 = $mysqli->prepare("SELECT `eq`.`serviceNumber`, `m`.`name`, `mfg`.`name` ".
								"FROM `equipment` AS `eq` ".
								"JOIN `equipmentModels` AS `m` ON `m`.`guid` = `eq`.`equipmentModel_guid` ".
								"JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`guid` = `m`.`equipmentManufacturer_guid` ".
								"WHERE `eq`.`workplace_guid` = UNHEX(REPLACE(?, '-', '')) ".
								"ORDER BY `eq`.`serviceNumber`");
	$req1->bind_param('s', $wpId);
	$req1->bind_result($servNum, $model, $mfg);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = '';
	$i = 0;
	$serv = 0;
	$total = 0;
	$req->store_result();
	while ($req->fetch()) {
		$wpId = formatGuid($wpId);
		if (!$req1->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		$eqNum = 0;
		$eqList = array();
		while ((++$eqNum) < 4 && $req1->fetch()) {
			$eqList[] = htmlspecialchars($servNum." - ".$mfg." ".$model);
		}
		if ($req1->fetch())
			$eqList[] = '...';
		$eqList = implode('<br>', $eqList);
		$row = array('id' => $wpId, 
					 'fields' => array(htmlspecialchars($wpName), htmlspecialchars($wpDesc), $eqList));
		$total++;
		if ($wpId == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		if ($inUse != '')
			$row['notDel'] = 1;
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last));
?>	