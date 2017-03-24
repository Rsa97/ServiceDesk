<?php
	include('common.php');

	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	$slaLevels = array( 'critical' => 'Критический',
						'high' => 'Высокий',
						'medium' => 'Стандартный',
						'low' => 'Низкий');
	
	function interval_to_str($interval) {
		$t = array();
		if ($interval['years'] != 0)
			$t[] = $interval['years'].' y';
		if ($interval['months'] != 0)
			$t[] = $interval['months'].' m';
		if ($interval['weeks'] != 0)
			$t[] = $interval['weeks'].' w';
		if ($interval['days'] != 0)
			$t[] = $interval['days'].' d';
		return implode(' ', $t);
	}
	
	function str_to_interval($str) {
		if (!preg_match('/(?:(\d+)\s*y)?\s*(?:(\d+)\s*m)?\s*(?:(\d+)\s*w)?\s*(?:(\d+)\s*d)?/', $str, $matches))
			return -1;
		$interval = array('years' => 0, 'months' => 0, 'weeks' => 0, 'days' => 0);
		if (isset($matches[1]) && $matches[1] != '')
			$interval['years'] = $matches[1];
		if (isset($matches[2]) && $matches[2] != '')
			$interval['months'] = $matches[2];
		if (isset($matches[3]) && $matches[3] != '')
			$interval['weeks'] = $matches[3];
		if (isset($matches[4]) && $matches[4] != '')
			$interval['days'] = $matches[4];
		if ($interval['years'] == 0 && $interval['months'] == 0 && $interval['weeks'] == 0 && $interval['days'] == 0)
			return '';
		return $interval;
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
	$divId = $paramValues['divId'];
	switch($paramValues['call']) {
		case 'init':
			break;
		case 'getlist':
			if (!isset($paramValues['field'])) {
				header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
				exit;
			}
			switch($paramValues['field']) {
				case 'service':
					$req = $mysqli->prepare("SELECT DISTINCT `s`.`guid`, `s`.`name` ".
												"FROM `services` AS `s` ".
												"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = `s`.`guid` ".
												"JOIN `contractDivisions` AS `cd` ON `cd`.`type_guid` = `dss`.`divType_guid` ".
													"AND `dss`.`contract_guid` = `cd`.`contract_guid` ".
												"WHERE `cd`.`guid` = UNHEX(REPLACE(?, '-', '')) ".
												"ORDER BY `s`.`name`");
					$req->bind_param('s', $divId);
					$req->bind_result($servId, $servName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$list[] = array('id' => formatGuid($servId), 'name' => htmlspecialchars($servName));
					}
					returnJson(array('options' => $list));
					exit;
					break;
				case 'sla':
					if (!isset($paramValues['service'])) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						exit;
					}
					$req = $mysqli->prepare("SELECT `dss`.`slaLevel` ".
												"FROM `divServicesSLA` AS `dss` ".
												"JOIN `contractDivisions` AS `cd` ON `cd`.`type_guid` = `dss`.`divType_guid` ".
													"AND `cd`.`contract_guid` = `dss`.`contract_guid` ".
												"WHERE `cd`.`guid` = UNHEX(REPLACE(?, '-', '')) ".
													"AND `dss`.`service_guid` = UNHEX(REPLACE(?, '-', '')) ".
												"ORDER BY `dss`.`slaLevel`");
					$req->bind_param('ss', $divId, $paramValues['service']);
					$req->bind_result($sla);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$list[] = array('id' => $sla, 'name' => $slaLevels[$sla]);
					}
					returnJson(array('options' => $list));
					exit;
					break;
				default:
					header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
					exit;
			}
			break;
		case 'update':
			if (!isset($paramValues['id']) || !isset($paramValues['service']) || !isset($paramValues['sla']) ||
				!isset($paramValues['problem']) || !isset($paramValues['nextDate']) || !isset($paramValues['interval']) || 
				!isset($paramValues['preStart']) || $paramValues['preStart'] > 366) {
				header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
				exit;
			}
			$id = $paramValues['id'];
			$interval = str_to_interval($paramValues['interval']);
			$problem = trim($paramValues['problem']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `plannedRequests` (`contractDivision_guid`, `service_guid`, `slaLevel`, ".
												"`intervalYears`, `intervalMonths`, `intervalWeeks`, `intervalDays`, `nextDate`, ".
												"`preStart`, `problem`) ".
												"VALUES (UNHEX(REPLACE(?, '-', '')), UNHEX(REPLACE(?, '-', '')), ?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('sssiiiisis', $divId, $paramValues['service'], $paramValues['sla'], $interval['years'], $interval['months'], $interval['weeks'], 
											$interval['days'], $paramValues['nextDate'], $paramValues['preStart'], $problem);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `plannedRequests` SET `service_guid` = UNHEX(REPLACE(?, '-', '')), `slaLevel` = ?, ".
												"`intervalYears` = ?, `intervalMonths` = ?, `intervalWeeks` = ?, `intervalDays` = ?, ".
												"`nextDate` = ?, `preStart` = ?, `problem` = ? ".
												"WHERE `id` = ?");
				$req->bind_param('ssiiiisisi', $paramValues['service'], $paramValues['sla'], $interval['years'], $interval['months'], $interval['weeks'], 
											$interval['days'], $paramValues['nextDate'], $paramValues['preStart'], $problem, $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
				exit;
			}
			$req->close();
			break;	
		case 'del':
			if (!isset($paramValues['id'])) {
				header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
				exit;
			}
			$id = $paramValues['id'];
			$req = $mysqli->prepare("DELETE IGNORE FROM `plannedRequests` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
				exit;
			}
			$req->close();
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `pr`.`id`, `s`.`name`, `pr`.`slaLevel`, `pr`.`intervalYears`, `pr`.`intervalMonths`, ".
								"`pr`.`intervalWeeks`, `pr`.`intervalDays`, `pr`.`nextDate`, `pr`.`preStart`, `pr`.`problem` ".
								"FROM `plannedRequests` AS `pr` ".
								"JOIN `services` AS `s` ON `s`.`guid` = `pr`.`service_guid` ".
								"WHERE `pr`.`contractDivision_guid` = UNHEX(REPLACE(?, '-', '')) ".
								"ORDER BY `pr`.`nextDate`");
	$req->bind_param('s', $divId);
	$req->bind_result($id, $servName, $sla, $intYears, $intMonths, $intWeeks, $intDays, $nextDate, $preStart, $problem);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	while ($req->fetch()) {
		$interval = array('years' => $intYears, 'months' => $intMonths, 'weeks' => $intWeeks, 'days' => $intDays);
		$row = array('id' => $id, 'fields' => array(htmlspecialchars($servName), $slaLevels[$sla], htmlspecialchars($problem), 
													$nextDate, interval_to_str($interval), $preStart));
		if ($id == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last));
?>	