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
	$id = 0;
	switch($_REQUEST['call']) {
		case 'init':
			break;
		case 'updatePartner':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addPartner':
			if (!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `partner` (`name`) VALUES (?)");
				$req->bind_param('s', $name);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `partner` SET `name` = ? WHERE `id` = ?");
				$req->bind_param('si', $name, $id);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				echo json_encode(array('error' => 'Партнёр с таким именем уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		case 'delPartner':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `partner` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Партнёр закреплён за договором, есть сотрудники партнёра или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `id`, `name` FROM `partner` WHERE `id` > 0 ORDER BY `name`");
	$req->bind_result($id, $name);
	$req1 = $mysqli->prepare("SELECT `firstName`, `secondName`, `middleName` ".
							   "FROM `users` ".
							   "WHERE `rights` = 'partner' AND `partner_id` = ? ".
							   "ORDER BY `secondName`, `firstName`, `middleName`");
	$req1->bind_param('i', $id);
	$req1->bind_result($givenName, $familyName, $middleName);
	$req2 = $mysqli->prepare("SELECT `ca`.`name`, `cd`.`name`, `cdca`.`name` ". 
							  "FROM `allowedContracts` AS `ac` ".
							    "LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`id` = `ac`.`contractDivisions_id` ".
							    "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
							    "LEFT JOIN `contragents` AS `cdca` ON `cdca`.`id` = `cd`.`contragents_id` ".
							    "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
							  "WHERE `ac`.`partner_id` = ? ".
							  "ORDER BY `ca`.`name`, `cd`.`name`, `cdca`.`name`");
	$req2->bind_param('i', $id);
	$req2->bind_result($contragent, $division, $divisionContragent);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$req->store_result();
	$tbl =	"<table class='partnerTbl'>".
				"<thead><tr><th><th>Партнёр<th>Работники<th>Обслуживаемые клиенты".
				"<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}<td>";
		if (!$req1->execute()) {
			echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		while ($req1->fetch()) {
			$tbl .= $familyName.($givenName == '' ? '' : ' '.$givenName.($middleName == '' ? '' : ' '.$middleName)).'<br>';
		}
		$tbl .= "<td>";
		if (!$req2->execute()) {
			echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		while ($req2->fetch()) {
			$tbl .= $contragent.'<br><span class="lt">'.$division.(($division != '' && $divisionContragent != '') ? ' - ' : '').$divisionContragent.'<br>';
		}
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td></table>";
	$req1->close();
	$req2->close();
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>	