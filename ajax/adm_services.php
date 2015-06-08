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
		case 'update':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0 ||
				!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '' ||
				!isset($_REQUEST['shortName']) || ($shortname = $_REQUEST['shortName']) == '') {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `services` (`name`, `shortname`) VALUES (?, ?)");
				$req->bind_param('ss', $name, $shortname);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `services` SET `name` = ?, `shortname` = ? WHERE `id` = ?");
				$req->bind_param('ssi', $name, $shortname, $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				returnJson(array('error' => 'Сервис с таким именем уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;	
		case 'setCheck':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0 ||
				!isset($_REQUEST['field']) || ($field = $_REQUEST['field']) != 'utility' ||
				!isset($_REQUEST['value']) || (($val = $_REQUEST['value']) != 0 && $val != 1)) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE IGNORE `services` SET `utility` = ? WHERE `id` = ?");
			$req->bind_param('ii', $val, $id);
			if (!$req->execute() || $mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			returnJson(array('ok' => 1));
			exit;
			break; 		
		case 'del':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `services` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Сервис используется в договорах или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `s`.`id`, `s`.`name`, `s`.`shortname`, `s`.`utility`, ".
								"IFNULL(`cs`.`services_id`, IFNULL(`dss`.`service_id`, `r`.`service_id`)) ".
								"FROM `services` AS `s` ".
								"LEFT JOIN (SELECT DISTINCT `services_id` FROM `contractServices`) AS `cs` ON `cs`.`services_id` = `s`.`id` ".
								"LEFT JOIN (SELECT DISTINCT `service_id` FROM `divServicesSLA`) AS `dss` ON `dss`.`service_id` = `s`.`id` ".
								"LEFT JOIN (SELECT DISTINCT `service_id` FROM `request`) AS `r` ON `r`.`service_id` = `s`.`id` ".
								"ORDER BY `s`.`name`");
	$req->bind_result($id, $name, $shortname, $utility, $inUse);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	while ($req->fetch()) {
		$row = array('id' => $id, 'fields' => array(htmlspecialchars($name), htmlspecialchars($shortname), $utility));
		if ($id == $lastId) {
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