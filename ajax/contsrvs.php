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
			$sel = "<div id='mdSelectors'>Договор: <select id='srvContract'>";
			$req = $mysqli->prepare("SELECT `c`.`id`, `c`.`number`, `ca`.`name` ".
									  "FROM `contracts` AS `c` ".
									  "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
									  "WHERE `c`.`id` > 0 ".
									  "ORDER BY `c`.`number`");
			$req->bind_result($cId, $number, $ca);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				if ($id == 0)
					$id = $cId;
				$sel .= "<option value='{$cId}'>{$number} - {$ca}";
			}
			$req->close();
			$cId = $id;
			$sel .= "</select></div><div id='divs'> </div>";
			$ret['content'] = $sel;
			break;
		case 'selectContract':
			if (!isset($_REQUEST['contractId']) || ($cId = $_REQUEST['contractId']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			break;
		case 'delService':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0 || 
				!isset($_REQUEST['contractId']) || ($cId = $_REQUEST['contractId']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
					
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contractServices` WHERE `contract_id` = ? AND `services_id` = ?");
			$req->bind_param('ii', $cId, $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		case 'addService':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0 || 
				!isset($_REQUEST['contractId']) || ($cId = $_REQUEST['contractId']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("INSERT IGNORE INTO `contractServices` (`contract_id`, `services_id`) VALUES (?, ?)");
			$req->bind_param('ii', $cId, $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `s`.`id`, `s`.`name`, `s`.`shortname` ".
							  "FROM `services` AS `s` ".
							  "LEFT JOIN `contractServices` AS `cs` ON `cs`.`services_id` = `s`.`id` ".
							  "WHERE `cs`.`contract_id` = ? ".
							  "ORDER BY `s`.`name`");
	$req->bind_param('i', $cId);
	$req->bind_result($id, $name, $shortName);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='contSrvTbl'>".
				"<thead><tr><th><th>Название<th>Сокращение<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}<td>{$shortName}";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td></table>";
	$req->close();	
	$req = $mysqli->prepare("SELECT DISTINCT `s`.`id`, `s`.`name`, `s`.`shortname`, `cs`.`services_id` ".
							  "FROM `services` AS `s` ".
							  "LEFT JOIN (SELECT `services_id` FROM `contractServices` WHERE `contract_id` = ?) AS `cs` ".
							    "ON `cs`.`services_id` = `s`.`id` ".
							  "WHERE `cs`.`services_id` IS NULL ".
							  "ORDER BY `s`.`name`");
	$req->bind_param('i', $cId);
	$req->bind_result($id, $name, $shortName, $t);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl .= "<input id='addList' type='hidden' value=\"<select id='addServ'>";	
	while ($req->fetch())
		$tbl .= "<option value='{$id}'>({$shortName}) {$name}";
	$req->close();	
	$tbl .= "</select>\">";	
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>