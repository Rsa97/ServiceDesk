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
	$lastId = '';
	switch($paramValues['call']) {
		case 'init':
			break;
		case 'update':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['name']) || ($name = $paramValues['name']) == '') {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `contragents` (`name`) VALUES (?)");
				$req->bind_param('s', $name);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contragents` SET `name` = ? WHERE `id` = ?");
				$req->bind_param('si', $name, $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				returnJson(array('error' => 'Партнёр с таким именем уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			
		case 'del':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contragents` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'С контрагентом есть договоры или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `ca`.`guid`, `ca`.`inn`, `ca`.`kpp`, `ca`.`name`, IFNULL(`c`.`contragent_guid`, `cd`.`contragent_guid`) ".
								"FROM `contragents` AS `ca` ".
								"LEFT JOIN (SELECT DISTINCT `contragent_guid` FROM `contracts`) AS `c` ON `c`.`contragent_guid` = `ca`.`guid` ".
								"LEFT JOIN (SELECT DISTINCT `contragent_guid` FROM `contractDivisions`) AS `cd` ON `cd`.`contragent_guid` = `ca`.`guid` ".
								"ORDER BY `ca`.`name`");
	$req->bind_result($caId, $inn, $kpp, $name, $inUse);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	$req->store_result();
	while ($req->fetch()) {
		$caId = formatGuid($caId);
		$row = array('id' => $caId, 'fields' => array(htmlspecialchars($inn), htmlspecialchars($kpp), htmlspecialchars($name)));
/*		if ($caId == $lastId) {
			$row['last'] = 1;
			$last = $i;
		} */
		if ($inUse != '')
			$row['notDel'] = 1;
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last));
?>