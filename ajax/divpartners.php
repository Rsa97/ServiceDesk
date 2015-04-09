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
			$sel = "<div id='mdSelectors'>Филиал: <select id='partnerDivision'>";
			$prevContract = '';
			$req = $mysqli->prepare("SELECT `c`.`number`, `cca`.`name`, `cd`.`id`, `cd`.`name`, `cdca`.`name` ".
									  "FROM `contracts` AS `c` ".
									  "LEFT JOIN `contragents` AS `cca` ON `cca`.`id` = `c`.`contragents_id` ".
									  "LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`contracts_id` = `c`.`id` ".
									  "LEFT JOIN `contragents` AS `cdca` ON `cdca`.`id` = `cd`.`contragents_id` ".
									  "WHERE `c`.`id` > 0 AND `cd`.`id` > 0 ".
									  "ORDER BY `c`.`number`, `cd`.`name`, `cdca`.`name`");
			$req->bind_result($cNumber, $cContragent, $dId, $nName, $nContragent);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				if ($prevContract != $cNumber) {
					$sel .= "<optgroup label='".htmlspecialchars("{$cNumber} - {$cContragent}")."'>";
					$prevContract = $cNumber;
				}
				if ($id == 0) {
					$id = $dId;
					$cont = htmlspecialchars("{$cNumber} - {$cContragent}");
				}
				$sel .= "<option value='{$dId}'>".htmlspecialchars($nName == '' ? $nContragent : $nName);
			}
			$req->close();
			$dId = $id;
			$sel .= "</optgroup></select> Договор: <span id='contract'>{$cont}</span></div><div id='divs'> </div>";
			$ret['content'] = $sel;
			break;
		case 'selectDivision':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			break;
		case 'fillSelects':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['partnerId']) || ($partnerId = $_REQUEST['partnerId']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("SELECT `p`.`id`, `p`.`name`, `ap`.`partner_id` ".
									  "FROM `partner` AS `p` ".
									  "LEFT JOIN (SELECT DISTINCT `partner_id` FROM `allowedContracts` WHERE `contractDivisions_id` = ?) AS `ap` ".
									    "ON `ap`.`partner_id` = `p`.`id` ".
									  "WHERE `ap`.`partner_id` IS NULL AND `p`.`id` > 0 ".
									  "ORDER BY `p`.`name`");
			$req->bind_param('i', $dId);
			$req->bind_result($id, $name, $t);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$partners = '';
			while($req->fetch())
				$partners .= "<option value='{$id}'".($id == $partnerId ? ' selected' : '').">{$name}";
			$req->close();
			$ret['partner'] = $partners;
			echo json_encode($ret);
			exit;
			break;
		case 'addDivPartner':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['partnerId']) || ($partnerId = $_REQUEST['partnerId']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare('INSERT IGNORE INTO `allowedContracts` (`contractDivisions_id`, `partner_id`) VALUES (?, ?)');
			$req->bind_param('ii', $dId, $partnerId);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();			
			break; 
		case 'delDivPartner':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['partnerId']) || ($partnerId = $_REQUEST['partnerId']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare('DELETE IGNORE FROM `allowedContracts` WHERE `contractDivisions_id` = ? AND `partner_id` = ?');
			$req->bind_param('ii', $dId, $partnerId);
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
	$req = $mysqli->prepare("SELECT `p`.`id`, `p`.`name` ".
							  "FROM `partner` AS `p` ".
							  "JOIN `allowedContracts` AS `ac` ON `ac`.`partner_id` = `p`.`id` ".
							  "WHERE `ac`.`contractDivisions_id` = ? ".
							  "ORDER BY `p`.`name`");
	$req->bind_param('i', $dId);
	$req->bind_result($id, $name);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='divPartnerTbl'>".
				"<thead><tr><th><th>Партнёр<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить</table>";
	$req->close();	
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>			