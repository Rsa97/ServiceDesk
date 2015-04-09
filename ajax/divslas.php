<?php
	header('Content-Type: application/json; charset=UTF-8');

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
	$cont = '';
	switch($_REQUEST['call']) {
		case 'init':
			$sel = "<div id='mdSelectors'>Филиал: <select id='slaDivision'>";
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
				!isset($_REQUEST['levelId']) || ($levelId = $_REQUEST['levelId']) == '' ||
				!isset($_REQUEST['slaId']) || ($slaId = $_REQUEST['slaId']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($levelId == 0) {
				$req = $mysqli->prepare("SELECT `scl`.`id`, `scl`.`name`, `cs`.`slaCriticalLevels_id` ".
										  "FROM `slaCriticalLevels` AS `scl` ".
										  "LEFT JOIN (SELECT DISTINCT `slaCriticalLevels_id` FROM `contractSla` WHERE `contractDivisions_id` = ?) AS `cs` ".
										    "ON `cs`.`slaCriticalLevels_id` = `scl`.`id` ".
										  "WHERE `cs`.`slaCriticalLevels_id` IS NULL ".
										  "ORDER BY `scl`.`id`");
				$req->bind_param('i', $dId);
				$req->bind_result($id, $name, $t);
				if (!$req->execute()) {
					echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
					exit;
				}
				$levels = '';
				while($req->fetch())
					$levels .= "<option value='{$id}'>{$id} - {$name}";
				$req->close();
				$ret['level'] = $levels;
			}
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `sla` ORDER BY `name`");
			$req->bind_result($id, $name);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$slas = '';
			while($req->fetch())
				$slas .= "<option value='{$id}'".($id == $slaId ? ' selected' : '').">{$name}";
			$req->close();
			$ret['sla'] = $slas;
			echo json_encode($ret);
			exit;
			break;
		case 'addDivSla':
		case 'updateDivSla':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['levelId']) || ($levelId = $_REQUEST['levelId']) == '' ||
				!isset($_REQUEST['slaId']) || ($slaId = $_REQUEST['slaId']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($_REQUEST['call'] == 'addDivSla')
 				$req = $mysqli->prepare("INSERT IGNORE INTO `contractSla` (`sla_id`, `contractDivisions_id`, `slaCriticalLevels_id`) VALUES (?, ?, ?)");
			else
				$req = $mysqli->prepare("UPDATE IGNORE `contractSla` SET `sla_id` = ? WHERE `contractDivisions_id` = ? AND `slaCriticalLevels_id` = ?");
			$req->bind_param('iis', $slaId, $dId, $levelId);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		case 'delDivSla':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['levelId']) || ($levelId = $_REQUEST['levelId']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contractSla` WHERE `contractDivisions_id` = ? AND `slaCriticalLevels_id` = ?");
			$req->bind_param('is', $dId, $levelId);
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
	$req = $mysqli->prepare("SELECT `scl`.`id`, `scl`.`name`, `sla`.`id`, `sla`.`name`, `sla`.`reactionTime`, `sla`.`fixTime`, ".
							  "`sla`.`fullRestoreTime`, `sla`.`quality` ".
							  "FROM `contractSla` AS `cs` ".
							  "LEFT JOIN `sla` AS `sla` ON `sla`.`id` = `cs`.`sla_id` ".
							  "LEFT JOIN `slaCriticalLevels` AS `scl` ON `scl`.`id` = `cs`.`slaCriticalLevels_id` ".
							  "WHERE `cs`.`contractDivisions_id` = ? ".
							  "ORDER BY `scl`.`id` ");
	$req->bind_param('i', $dId);
	$req->bind_result($critId, $critName, $slaId, $slaName, $slaReactTime, $slaFixTime, $slaRestoreTime, $slaQuality);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='divSlaTbl'>".
				"<thead><tr><th><th>Уровень<br>Критичности<th>SLA<th>Время реакции<th>Время до<br>восстановления".
				"<th>Время до<br>завершения<th>Качество<th>По умолчанию<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$critId}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$critId} - {$critName}<td data-id='{$slaId}'>{$slaName}<td>".minutes_to_str($slaReactTime)."<td>".
				minutes_to_str($slaFixTime)."<td>".minutes_to_str($slaRestoreTime)."<td>{$slaQuality}%";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td><td></table>";
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>						