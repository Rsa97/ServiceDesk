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
	$lastId = 0;
	$setService = 0;
	$divId = $paramValues['divId'];
	switch($paramValues['call']) {
		case 'init':
			if (isset($paramValues['last']) && $paramValues['last'] > 0)
				$lastId = $paramValues['last'];   
			break;
		case 'getlists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) ||($id = $paramValues['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($paramValues['field']) {
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['serviceNum']) || ($serviceNum = $paramValues['serviceNum']) <= 0 ||
				!isset($paramValues['serial']) || !isset($paramValues['model']) || 
				!isset($paramValues['warrEnd']) || !isset($paramValues['comment']) || 
				!isset($paramValues['workplace']) || ($workplace = $paramValues['workplace']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$serial = trim($paramValues['serial']);
			$comment = trim($paramValues['comment']);
			$req = $mysqli->prepare("SELECT `t`.`mfg`, `t`.`mdl` ".
										"FROM (".
											"SELECT `mfg`.`id` AS `mfg`, `m`.`id` AS `mdl`, CONCAT(`mfg`.`name`, ' ', `m`.`name`) AS `model` ".
											"FROM `equipmentModels` AS `m` ". 
											"JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`id` = `m`.`equipmentManufacturers_id` ".
										") AS `t` ".
										"WHERE `t`.`model` = ?");
			$req->bind_param('s', $paramValues['model']);
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
			$did = $divId;
			if ($workplace == 0) {
				$workplace = null;
				$did = null;
			}
			if ($id == 0) {
				$contId = 0;
				$req = $mysqli->prepare("SELECT `contracts_id` FROM `contractDivisions` WHERE `id` = ?");
				$req->bind_param('i', $divId);
				$req->bind_result($contId);
				if (!$req->execute()) {
					returnJson(array('error' => 'Внутренняя ошибка сервера.'));
					exit;
				}
				$req->fetch();
				$req->close();
				$req = $mysqli->prepare("INSERT IGNORE INTO `equipment` (`serviceNumber`, `serialNumber`, `warrantyEnd`, ".
												"`equipmentModels_id`, `contractDivisions_id`, `rem`, `workplace_id`, `contracts_id`) ".
												"VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('issiisii', $serviceNum, $serial, $paramValues['warrEnd'], $model, $did, $comment, $workplace, $contId);
			} else {
				if ($workplace != 0) {
					$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `serviceNumber` = ?, `serialNumber` = ?, `warrantyEnd` = ?, ".
													"`equipmentModels_id` = ?, `rem` = ?, `workplace_id` = ?, `contractDivisions_id` = ? ".
													"WHERE `serviceNumber` = ?");
					$req->bind_param('issisiii', $serviceNum, $serial, $paramValues['warrEnd'], $model, $comment, $workplace, $divId, $id);
				} else {
					$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `serviceNumber` = ?, `serialNumber` = ?, `warrantyEnd` = ?, ".
													"`equipmentModels_id` = ?, `rem` = ?, `workplace_id` = ? ".
													"WHERE `serviceNumber` = ?");
					$req->bind_param('issisii', $serviceNum, $serial, $paramValues['warrEnd'], $model, $comment, $workplace, $id);
				}
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($setService == 1) {
				$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `onService` = 1, `contractDivisions_id` = ? WHERE `serviceNumber` = ?");
				$req->bind_param('ii', $divId, $id);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `onService` = 0, `contractDivisions_id` = NULL WHERE `serviceNumber` = ?");
				$req->bind_param('i', $id);
			} 
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
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
	$req = $mysqli->prepare("SELECT `eq`.`guid`, `eq`.`serviceNumber`, `eq`.`serialNumber`, IFNULL(DATE(`eq`.`warrantyEnd`), ''), ".
									"`eq`.`onService`, `m`.`name`, `mfg`.`name`, `eq`.`rem`, `t`.`guid`, `wp`.`name`, ".
									"`eq`.`contractDivision_guid` ".
								"FROM `equipment` AS `eq` ".
								"JOIN `equipmentModels` AS `m` ON `m`.`guid` = `eq`.`equipmentModel_guid` ".
								"JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`guid` = `m`.`equipmentManufacturer_guid` ".
								"LEFT JOIN `contractDivisions` AS `d` ON `d`.`guid` = `eq`.`contractDivision_guid` ".
								"LEFT JOIN ( ".
									"SELECT DISTINCT `equipment_guid` AS `guid` FROM `equipmentOnServiceLog` ".
//									"UNION SELECT `equipment_serviceNumber` AS `sn` FROM `replacement` ".
									"UNION SELECT `equipment_guid` AS `guid` FROM `requests` ".
									"UNION SELECT `equipment_guid` AS `guid` FROM `equipmentWorkplaceLog` ".
								") AS `t` ON `t`.`guid` = `eq`.`guid` ".
								"LEFT JOIN `divisionWorkplaces` AS `wp` ON `wp`.`guid` = `eq`.`workplace_guid` ".
								"WHERE `eq`.`contractDivision_guid` = UNHEX(REPLACE(?, '-', '')) ".
									"OR (`eq`.`contractDivision_guid` IS NULL AND `eq`.`contract_guid` = `d`.`contract_guid`) ".
								"ORDER BY `eq`.`contractDivision_guid` DESC, `eq`.`serviceNumber`");
	$req->bind_param('s', $divId);
	$req->bind_result($eqId, $servNum, $serial, $warrEnd, $onService, $model, $mfg, $rem, $inUse, $workplace, $inDiv);
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
		$eqId = formatGuid($eqId);
		$inDiv = ($inDiv == '' ? 0 : 1);
		$row = array('id' => $eqId, 
					 'fields' => array(htmlspecialchars($servNum), htmlspecialchars($serial), htmlspecialchars($mfg.' '.$model),
										$warrEnd, htmlspecialchars($workplace), htmlspecialchars($rem)),
					 'onService' => $onService,
					 'inDivision' => $inDiv);
		if ($inDiv == 0)
			$row['mark'] = 'blue';
		else if ($onService == 0)
			$row['mark'] = 'gray';
		else
			$serv++;
		$total++;
		if ($eqId == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		if ($inUse != '')
			$row['notDel'] = 1;
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last, 'count' => "")); // 'count' => "({$serv} / {$total})"));
?>	