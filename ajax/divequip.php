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
	$cont = '';
	switch($_REQUEST['call']) {
		case 'init':
			$sel = "<div id='mdSelectors'>Филиал: <select id='eqDivision'>";
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
		case 'onoffEquip':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['servNum']) || ($servNum = $_REQUEST['servNum']) == '' ||
				!isset($_REQUEST['state']) || ($state = $_REQUEST['state']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `onService` = ? WHERE `serviceNumber` = ?");
			$req->bind_param('is', $state, $servNum);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		case 'addEquip':
		case 'updateEquip':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['servNum']) || ($servNum = $_REQUEST['servNum']) == '' ||
				!isset($_REQUEST['serial']) || !isset($_REQUEST['warrEnd']) ||
				!isset($_REQUEST['mfg']) || ($mfg = $_REQUEST['mfg']) == '' ||
				!isset($_REQUEST['remark']) ||
				!isset($_REQUEST['model']) || ($model = $_REQUEST['model']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("SELECT `emd`.`id` ".
									  "FROM `equipmentModels` AS `emd` ".
									  "JOIN `equipmentManufacturers` AS `emf` ON `emf`.`id` = `emd`.`equipmentManufacturers_id` ".
									  "WHERE `emd`.`name` LIKE ? AND `emf`.`name` LIKE ?");
  			$req->bind_param('ss', $model, $mfg);
			$req->bind_result($modelId);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (!($req->fetch())) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			if ($_REQUEST['call'] == 'addEquip') {
				$req = $mysqli->prepare("INSERT IGNORE INTO `equipment` (`serviceNumber`, `serialNumber`, `warrantyEnd`, `onService`, ".
												"`equipmentModels_id`, `contractDivisions_id`, `rem`) VALUES (?, ?, ?, 0, ?, ?, ?)");
				$req->bind_param('sssiis', $servNum, $_REQUEST['serial'], $warrEnd, $modelId, $dId, $_REQUEST['remark']);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `equipment` SET `serialNumber` = ?, `warrantyEnd` = ?, ".
												"`equipmentModels_id` = ?, `rem` = ? WHERE `serviceNumber` = ?");
				$req->bind_param('ssiss', $_REQUEST['serial'], $warrEnd, $modelId, $_REQUEST['remark'], $servNum);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		case 'delEquip':
			if (!isset($_REQUEST['divisionId']) || ($dId = $_REQUEST['divisionId']) <= 0 ||
				!isset($_REQUEST['servNum']) || ($servNum = $_REQUEST['servNum']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `equipment` WHERE `serviceNumber` = ?");
  			$req->bind_param('s', $servNum);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Оборудование вводилось в эксплуатацию или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;  						 
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `eq`.`serviceNumber`, `eq`.`serialNumber`, DATE(`eq`.`warrantyEnd`), `eq`.`onService`, ".
							  "`eqmd`.`id`, `eqmd`.`name`, `eqmf`.`id`, `eqmf`.`name`, `eq`.`rem` ".
							  "FROM `equipment` AS `eq` ".
							  "LEFT JOIN `equipmentModels` AS `eqmd` ON `eqmd`.`id` = `eq`.`equipmentModels_id` ".
							  "LEFT JOIN `equipmentManufacturers` AS `eqmf` ON `eqmf`.`id` = `eqmd`.`equipmentManufacturers_id` ".
							  "WHERE `eq`.`contractDivisions_id` = ? ".
							  "ORDER BY `eq`.`onService` DESC, `eq`.`serviceNumber` ");
	$req->bind_param('i', $dId);
	$req->bind_result($servNumber, $serial, $warrEnd, $onService, $modelId, $modelName, $mfgId, $mfgName, $remark);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='divEqTbl'>".
				"<thead><tr><th><th>Сервисный<br>номер<th>Серийный<br>номер<th>Наименование<th>Окончание<br>гарантии<th>Примечание<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$servNumber}' data-model='".htmlspecialchars($modelName)."' data-mfg='".htmlspecialchars($mfgName).
				($onService == 0 ? "' class='offService'><td><span class='ui-icon ui-icon-trash'> </span><span class='ui-icon ui-icon-gear'> </span>" : 
				"'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-cancel'> </span>").
				"<td>{$servNumber}<td>{$serial}<td>{$mfgName} {$modelName}<td>{$warrEnd}<td>{$remark}";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td></table>";
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>			