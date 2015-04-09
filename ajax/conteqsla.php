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
			$sel = "<div id='mdSelectors'>Договор: <select id='eqSlaContract'>";
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
		case 'onoffScl':
			if (!isset($_REQUEST['contractId']) || ($cId = $_REQUEST['contractId']) <= 0 ||
				!isset($_REQUEST['code']) || ($code = $_REQUEST['code']) == '' || 
				!isset($_REQUEST['value']) || ($val = $_REQUEST['value']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$scl = substr($code, 0, 1);
			$eqSubTypeId = substr($code, 1);
			if ($val == 1)
				$req = $mysqli->prepare("INSERT IGNORE INTO `contractEquipmentSla` (`contracts_id`, `equipmentSubType_id`, ".
											"`slaCriticalLevel_id`) VALUES (?, ?, ?)");
			else 
				$req = $mysqli->prepare("DELETE IGNORE FROM `contractEquipmentSla` WHERE `contracts_id` = ? AND `equipmentSubType_id` = ? AND ".
											"`slaCriticalLevel_id` = ?");
			$req->bind_param('iis', $cId, $eqSubTypeId, $scl);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close(); 
			$req = $mysqli->prepare("SELECT `slaCriticalLevel_id` FROM `contractEquipmentSla` WHERE `contracts_id` = ? AND `equipmentSubType_id` = ? AND ".
											"`slaCriticalLevel_id` = ?");
			$req->bind_param('iis', $cId, $eqSubTypeId, $scl);
			$req->bind_result($t);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$val = ($req->fetch() ? 1 : 0);
			$req->close();
			$ret[$code] = "<input class='contEqSlaCheck' type='checkbox'".($val == 1 ? ' checked' : '').">";
			echo json_encode($ret);
			exit;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `id`, `name` FROM `slaCriticalLevels` ORDER BY `id`");
	$req->bind_result($sclId, $sclName);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = "<table class='contEqSlaTbl'><thead><tr><th>";
	$numCols = 1;
	while($req->fetch()) {
		$tbl .= "<th>{$sclId}<br>{$sclName}";
		$numCols++;
	}
	$tbl .= '<tbody>';
	$req->close();
	$req = $mysqli->prepare("SELECT DISTINCT `eqt`.`name`, `eqst`.`id`, `eqst`.`description`, `scl`.`id`, `cs`.`slaCriticalLevels_id`, ".
							  	"`ces`.`slaCriticalLevel_id` ".
							  "FROM `equipmentTypes` AS `eqt` ".
							  "LEFT JOIN `equipmentSubTypes` AS `eqst` ON `eqst`.`equipmentTypes_id` = `eqt`.`id` ".
							  "LEFT JOIN `equipmentModels` AS `eqm` ON `eqm`.`equipmentSubTypes_id` = `eqst`.`id` ".
							  "LEFT JOIN `equipment` AS `eq` ON `eq`.`equipmentModels_id` = `eqm`.`id` ".
							  "LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`id` = `eq`.`contractDivisions_id` ".
							  "LEFT JOIN `slaCriticalLevels` AS `scl` ON TRUE ".
							  "LEFT JOIN `contractSla` AS `cs` ON `cs`.`contractDivisions_id` = `cd`.`id` ".
							  		"AND `cs`.`slaCriticalLevels_id` = `scl`.`id` ".
							  "LEFT JOIN `contractEquipmentSla` AS `ces` ON `ces`.`contracts_id` = `cd`.`contracts_id` ".
							  		"AND `ces`.`slaCriticalLevel_id` = `scl`.`id` AND `ces`.`equipmentSubType_id` = `eqst`.`id` ".
							  "WHERE `cd`.`contracts_id` = ? ".
							  "ORDER BY `eqt`.`name`, `eqst`.`description`, `scl`.`id`");
	$req->bind_param('i', $cId);
	$req->bind_result($eqType, $eqSubTypeId, $eqSubTypeName, $scl, $isSlaExists, $isSlaEnabled);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$prevEqType = '';
	$prevEqSubType = '';
	while ($req->fetch()) {
		if ($prevEqType != $eqType)
			$tbl .= "<tr class='eqTypeRow'><td colspan='${numCols}'>{$eqType}";
		if ($prevEqSubType != $eqSubTypeId)
			$tbl .= "<tr><td>{$eqSubTypeName}";
		$tbl .= "<td id='{$scl}{$eqSubTypeId}'><input class='contEqSlaCheck' type='checkbox'".($isSlaExists == '' ? ' disabled' : '').($isSlaEnabled == '' ? '' : ' checked').">";
		$prevEqType = $eqType;
		$prevEqSubType = $eqSubTypeId;
	}
	$tbl .= "</table>";
	$req->close();	
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>