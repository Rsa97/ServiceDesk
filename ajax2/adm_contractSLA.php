<?php
	include('common.php');

	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
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
		if (!preg_match('/(?:(\d+)d)?\s*(?:(\d+)h)?\s*(?:(\d+)m)?/', $str, $matches))
			return -1;
		$days = (isset($matches[1]) ? $matches[1] : 0);
		$hours = (isset($matches[2]) ? $matches[2] : 0);
		$mins = (isset($matches[3]) ? $matches[3] : 0);
		return ($days*24+$hours)*60+$mins;
	}
	
	function asTime($str) {
		if (!preg_match('/(\d{1,2})(?::(\d{1,2}))?/', $str, $matches))
			return '';
		$hours = (isset($matches[1]) ? $matches[1] : 0);
		$mins = (isset($matches[2]) ? $matches[2] : 0);
		if ($hours > 24 || $mins > 59)
			return '';
		if ($hours == 24)
			return '23:59:59';
		return "{$hours}:{$mins}:00"; 
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
	$contId = $paramValues['contId'];
	switch($paramValues['call']) {
		case 'init':
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
				!isset($paramValues['service']) || ($servId = $paramValues['service']) <= 0 ||
				!isset($paramValues['divType']) || ($divType = $paramValues['divType']) <= 0 ||
				!isset($paramValues['SLA']) || 
				!isset($paramValues['toReact']) || ($toReact = str_to_minutes($paramValues['toReact'])) < 0 ||
				!isset($paramValues['toFix']) || ($toFix = str_to_minutes($paramValues['toFix'])) < 0 ||
				!isset($paramValues['toRepair']) || ($toRepair = str_to_minutes($paramValues['toRepair'])) < 0 || 
				!isset($paramValues['quality']) || ($quality = $paramValues['quality']) < 0 || $quality > 100 ||
				!isset($paramValues['dayStart']) || ($dayStart = asTime($paramValues['dayStart'])) == '' || 
				!isset($paramValues['dayEnd']) || ($dayEnd = asTime($paramValues['dayEnd'])) == '') {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `divServicesSLA` (`contract_id`, `service_id`, `divType_id`, `slaLevel`, ".
												"`toReact`, `toFix`, `toRepair`, `quality`, `startDayTime`, `endDayTime`) ".
												"VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('iiisiiiiss', $contId, $servId, $divType, $paramValues['SLA'], $toReact, $toFix, $toRepair, $quality, 
												$dayStart, $dayEnd);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `divServicesSLA` SET `service_id` = ?, `divType_id` = ?, `slaLevel` = ?, ".
												"`toReact` = ?, `toFix` = ?, `toRepair` = ?, `quality` = ?, `startDayTime` = ?, ".
												"`endDayTime` = ? ".
												"WHERE `id` = ?");
				$req->bind_param('iisiiiissi', $servId, $divType, $paramValues['SLA'], $toReact, $toFix, $toRepair, $quality, 
												$dayStart, $dayEnd, $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;	
		case 'setCheck':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 || 
			!isset($paramValues['value']) || (($value = $paramValues['value']) != 0 && $value != 1) ||
			!isset($paramValues['field'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($paramValues['field']) {
				case 'workdays':
					$req = $mysqli->prepare("SELECT `dss`.`id` FROM `divServicesSLA` AS `dss` ".
											"JOIN `divServicesSLA` AS `t` ON `t`.`contract_id` = `dss`.`contract_id` ". 
												"AND `t`.`service_id` = `dss`.`service_id` AND `t`.`divType_id` = `dss`.`divType_id` ". 
												"AND `t`.`slaLevel` = `dss`.`slaLevel` AND `t`.`id` != `dss`.`id` ".
												"WHERE `dss`.`id` = ? AND FIND_IN_SET('work', `t`.`dayType`) != 0");
					$req->bind_param('i', $id);
					if ($value == 1)
						$req1 = $mysqli->prepare("UPDATE `divServicesSLA` SET `dayType` = (`dayType` | 1) WHERE `id` = ?");
					else 
						$req1 = $mysqli->prepare("UPDATE `divServicesSLA` SET `dayType` = (`dayType` & 254) WHERE `id` = ?");
					$req1->bind_param('i', $id);
					break;
				case 'weekdays':
					$req = $mysqli->prepare("SELECT `dss`.`id` FROM `divServicesSLA` AS `dss` ".
											"JOIN `divServicesSLA` AS `t` ON `t`.`contract_id` = `dss`.`contract_id` ". 
												"AND `t`.`service_id` = `dss`.`service_id` AND `t`.`divType_id` = `dss`.`divType_id` ". 
												"AND `t`.`slaLevel` = `dss`.`slaLevel` AND `t`.`id` != `dss`.`id` ".
												"WHERE `dss`.`id` = ? AND FIND_IN_SET('weekend', `t`.`dayType`) != 0");
					$req->bind_param('i', $id);
					if ($value == 1)
						$req1 = $mysqli->prepare("UPDATE `divServicesSLA` SET `dayType` = (`dayType` | 2) WHERE `id` = ?");
					else 
						$req1 = $mysqli->prepare("UPDATE `divServicesSLA` SET `dayType` = (`dayType` & 253) WHERE `id` = ?");
					$req1->bind_param('i', $id);
					break;
				case 'default':
					$req = $mysqli->prepare("SELECT `dss`.`id` FROM `divServicesSLA` AS `dss` ".
											"JOIN `divServicesSLA` AS `t` ON `t`.`contract_id` = `dss`.`contract_id` ". 
												"AND `t`.`service_id` = `dss`.`service_id` AND `t`.`divType_id` = `dss`.`divType_id` ". 
												"AND `t`.`id` != `dss`.`id` ".
												"WHERE `dss`.`id` = ? AND `t`.`isDefault` = 1");
					$req->bind_param('i', $id);
					$req1 = $mysqli->prepare("UPDATE `divServicesSLA` SET `isDefault` = ? WHERE `id` = ?");
					$req1->bind_param('ii', $value, $id);
					break;   
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			if ($value == 1) {
				if (!$req->execute()) {
					returnJson(array('error' => 'Внутренняя ошибка сервера.'));
					exit;
				}
				if ($req->fetch()) {
					returnJson(array('fail' => 1));
					exit;
				}
			}
			$req->close();
			if (!$req1->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req1->close();
			returnJson(array('ok' => 1));
			exit;
			break; 		
		case 'del':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `divServicesSLA` WHERE `id` = ?");
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
	$req = $mysqli->prepare("SELECT `dss`.`guid`, `s`.`shortname`, `dt`.`name`, `dss`.`slaLevel`, FIND_IN_SET('work', `dayType`) != 0, ".
								"FIND_IN_SET('weekend', `dayType`) != 0, `dss`.`toReact`, `dss`.`toFix`, `dss`.`toRepair`, `dss`.`quality`, ".
								"TIME_FORMAT(`dss`.`startDayTime`, '%k:%i'), TIME_FORMAT(`dss`.`endDayTime`, '%k:%i'), `dss`.`isDefault` ".
								"FROM `divServicesSLA` AS `dss` ".
								"JOIN `services` AS `s` ON `s`.`guid` = `dss`.`service_guid` ".
								"JOIN `divisionTypes` AS `dt` ON `dt`.`guid` = `dss`.`divType_guid` ".
								"WHERE `dss`.`contract_guid` = UNHEX(REPLACE(?, '-', '')) ".
								"ORDER BY `s`.`shortname`, `dt`.`name`, `dss`.`slaLevel`");
	$req->bind_param('s', $contId);
	$req->bind_result($id, $servName, $divType, $sla, $workdays, $weekends, $toReact, $toFix, $toRepair, $quality, 
						$dayStart, $dayEnd, $isDefault);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	while ($req->fetch()) {
		$id = formatGuid($id);
		if ($dayEnd == '23:59')
			$dayEnd = '24:00'; 
		$row = array('id' => $id, 'fields' => array(htmlspecialchars($servName), htmlspecialchars($divType), $sla, minutes_to_str($toReact), 
													minutes_to_str($toFix), minutes_to_str($toRepair), $quality.'%', $dayStart, $dayEnd, 
													$workdays, $weekends, $isDefault));
		if ($id == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last));
?>	