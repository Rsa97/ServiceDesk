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
	if (!isset($_REQUEST['call']) || !isset($_REQUEST['divId']) || ($divId = $_REQUEST['divId']) <= 0) {
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
	$setService = 0;
	switch($_REQUEST['call']) {
		case 'init':
			if (isset($_REQUEST['last']) && $_REQUEST['last'] > 0)
				$lastId = $_REQUEST['last'];   
			break;
		case 'getlists':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) ||($id = $_REQUEST['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($_REQUEST['field']) {
				case 'service':
					$req = $mysqli->prepare("SELECT `id`, `shortname` FROM `services` ORDER BY `shortname`");
					$req->bind_result($servId, $servName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$list[] = array('id' => $servId, 'name' => $servName);
					}
					returnJson(array('options' => $list));
					exit;
					break;
				case 'workplace':
					$req = $mysqli->prepare("SELECT `id`, `name` FROM `divisionWorkplaces` WHERE `divisions_id` = ? ORDER BY `name`");
					$req->bind_param('i', $divId);
					$req->bind_result($wpId, $wpName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					$list[] = array('id' => 0, 'name' => '-- Нет --');
					while ($req->fetch()) {
						$list[] = array('id' => $wpId, 'name' => $wpName);
					}
					returnJson(array('options' => $list));
					exit;
					break;
				case 'divType':
					$req = $mysqli->prepare("SELECT `id`, `name` FROM `divisionTypes` ORDER BY `name`");
					$req->bind_result($dtId, $dtName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$list[] = array('id' => $dtId, 'name' => $dtName);
					}
					returnJson(array('options' => $list));
					exit;
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			break;
		case 'update':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0 ||
				!isset($_REQUEST['serviceNum']) || ($serviceNum = $_REQUEST['serviceNum']) <= 0 ||
				!isset($_REQUEST['serial']) || !isset($_REQUEST['model']) || 
				!isset($_REQUEST['warrEnd']) || !isset($_REQUEST['comment']) || 
				!isset($_REQUEST['workplace']) || ($workplace = $_REQUEST['workplace']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$serial = trim($_REQUEST['serial']);
			$comment = trim($_REQUEST['comment']);
			$req = $mysqli->prepare("SELECT `t`.`mfg`, `t`.`mdl` ".
										"FROM (".
											"SELECT `mfg`.`id` AS `mfg`, `m`.`id` AS `mdl`, CONCAT(`mfg`.`name`, ' ', `m`.`name`) AS `model` ".
											"FROM `equipmentModels` AS `m` ". 
											"JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`id` = `m`.`equipmentManufacturers_id` ".
										") AS `t` ".
										"WHERE `t`.`model` = ?");
			$req->bind_param('s', $_REQUEST['model']);
			$req->bind_result($mfg, $model);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (!$req->fetch()) {
				returnJson(array('error' => 'Неверно указана модель.'));
				exit;
			}
			$req->close();
			if ($workplace == 0)
				$workplace = null;
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `equipment` (`serviceNumber`, `serialNumber`, `warrantyEnd`, ".
												"`equipmentModels_id`, `contractDivisions_id`, `rem`, `workplace_id`) ".
												"VALUES (?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('issiisi', $serviceNum, $serial, $_REQUEST['warrEnd'], $model, $divId, $comment, $workplace);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `serviceNumber` = ?, `serialNumber` = ?, `warrantyEnd` = ?, ".
												"`equipmentModels_id` = ?, `contractDivisions_id` = ?, `rem` = ?, `workplace_id` = ? ".
												"WHERE `serviceNumber` = ?");
				$req->bind_param('issiisii', $serviceNum, $serial, $_REQUEST['warrEnd'], $model, $divId, $comment, $workplace, $id);
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
		case 'serviceOn':
			$setService = 1;
		case 'serviceOff':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `onService` = ? WHERE `serviceNumber` = ?");
			$req->bind_param('ii', $setService, $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$lastId = $id;
			break;			 
		case 'del':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `equipment` WHERE `serviceNumber` = ?");
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
	$req = $mysqli->prepare("SELECT `eq`.`serviceNumber`, `eq`.`serialNumber`, IFNULL(DATE(`eq`.`warrantyEnd`), ''), `eq`.`onService`, `m`.`name`, ".
								"`mfg`.`name`, `eq`.`rem`, `t`.`sn`, `wp`.`name` ".
								"FROM `equipment` AS `eq` ".
								"JOIN `equipmentModels` AS `m` ON `m`.`id` = `eq`.`equipmentModels_id` ".
								"JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`id` = `m`.`equipmentManufacturers_id` ".
								"LEFT JOIN ( ".
									"SELECT DISTINCT `equipment_serviceNumber` AS `sn` FROM `equipmentOnServiceLog` ".
									"UNION SELECT DISTINCT `equipment_serviceNumber` AS `sn` FROM `replacement` ".
									"UNION SELECT DISTINCT `equipment_id` AS `sn` FROM `request` ".
								") AS `t` ON `t`.`sn` = `eq`.`serviceNumber` ".
								"LEFT JOIN `divisionWorkplaces` AS `wp` ON `wp`.`id` = `eq`.`workplace_id` ".
								"WHERE `eq`.`contractDivisions_id` = ? ".
								"ORDER BY `eq`.`serviceNumber`");
	$req->bind_param('i', $divId);
	$req->bind_result($servNum, $serial, $warrEnd, $onService, $model, $mfg, $rem, $inUse, $workplace);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	$serv = 0;
	$total = 0;
	while ($req->fetch()) {
		$row = array('id' => $servNum, 
					 'fields' => array(htmlspecialchars($servNum), htmlspecialchars($serial), htmlspecialchars($mfg.' '.$model),
										$warrEnd, htmlspecialchars($workplace), htmlspecialchars($rem)),
					  'onService' => $onService);
		if ($onService == 0)
			$row['mark'] = 'gray';
		else
			$serv++;
		$total++;
		if ($servNum == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		if ($inUse != '')
			$row['notDel'] = 1;
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last, 'count' => "({$serv} / {$total})"));
?>	