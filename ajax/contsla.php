<?php
	header('Content-Type: application/json; charset=UTF-8');
	include('../config/db.php');
	
	$slaLevels = array('critical' => 'Критический', 'high' => 'Высокий', 'medium' => 'Стандартный', 'low' => 'Низкий'); 
	
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
		if (preg_match('/^(:?\d+\s*d)?\s*(:?\d+\s*h)?\s*(:?\d+\s*m)?$/', $str) != 1)
			return -1;
		$min = 0;
		if (preg_match('/(\d+)\s*d/',$str,$match))
			$min += $match[1]*1440;
		if (preg_match('/(\d+)\s*h/',$str,$match))
			$min += $match[1]*60;
		if (preg_match('/(\d+)\s*m/',$str,$match))
			$min += $match[1];
		return($min);
	}
	
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
			$sel = "<div id='mdSelectors'>Договор: <select id='divSlaContract'>";
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
		case 'fillSelects':
			if (!isset($_REQUEST['srvId']) || ($srv = intval($_REQUEST['srvId'])) < 0 ||
				!isset($_REQUEST['typeId']) || ($type = intval($_REQUEST['typeId'])) < 0 ||
				!isset($_REQUEST['slaLevel'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$services = '';
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `services` ORDER BY `name`");
			$req->bind_result($srvId, $srvName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				$services .= "<option value='{$srvId}'".($srvId == $srv ? " selected" : "").">{$srvName}";
			}
			$req->close();
			$ret['service'] = $services;
			$types = '';
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `divisionTypes`  ORDER BY `name`");
			$req->bind_result($typeId, $typeName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				$types .= "<option value='{$typeId}'".($typeId == $type ? " selected" : "").">{$typeName}";
			}
			$req->close();
			$ret['divType'] = $types;
			$slas = '';
			foreach ($slaLevels as $slaLevel => $slaName) {
				$slas .= "<option value='{$slaLevel}'".($_REQUEST['slaLevel'] == $slaLevel ? " selected" : "").">{$slaName}";
			}
			$ret['slaLevel'] = $slas;
			echo json_encode($ret);
			exit;
			break;
		case 'delSla':
			if (!isset($_REQUEST['contractId']) || ($cId = intval($_REQUEST['contractId'])) <= 0 ||
				!isset($_REQUEST['srvId']) || ($srv = intval($_REQUEST['srvId'])) < 0 ||
				!isset($_REQUEST['typeId']) || ($type = intval($_REQUEST['typeId'])) < 0 ||
				!isset($_REQUEST['slaLevel']) ||  !isset($_REQUEST['workDays']) ||  !isset($_REQUEST['weekends'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$dayTypes = array();
			if ($_REQUEST['workDays'] == 1)
				$dayTypes[] = 'work';
			if ($_REQUEST['weekends'] == 1)
				$dayTypes[] = 'weekend';
			$dayTypes = join(',', $dayTypes);
			$req = $mysqli->prepare("DELETE IGNORE FROM `divServicesSLA` WHERE `contract_id` = ? AND `service_id` = ? AND ".
									"`divType_id` = ? AND `dayType` = ?");
			$req->bind_param('iiss', $cId, $srv, $type, $dayTypes);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Невозможно удалить подразделение или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break; 
		case 'changeSla':
			if (!isset($_REQUEST['contractId']) || ($cId = intval($_REQUEST['contractId'])) <= 0 ||
				!isset($_REQUEST['srvId']) || ($srv = intval($_REQUEST['srvId'])) < 0 ||
				!isset($_REQUEST['typeId']) || ($type = intval($_REQUEST['typeId'])) < 0 ||
				!isset($_REQUEST['slaLevel']) ||  !isset($_REQUEST['startDay']) || !isset($_REQUEST['endDay']) ||
				!isset($_REQUEST['toReact']) || ($toReact = str_to_minutes($_REQUEST['toReact'])) == -1 || 
				!isset($_REQUEST['toFix']) || ($toFix = str_to_minutes($_REQUEST['toFix'])) == -1 || 
				!isset($_REQUEST['toRepair']) || ($toRepair = str_to_minutes($_REQUEST['toRepair'])) == -1 ||
				!isset($_REQUEST['quality']) || ($quality = intval($_REQUEST['quality'])) < 0 || $quality > 100 ||
				!isset($_REQUEST['workDays']) ||  !isset($_REQUEST['weekends']) || ($_REQUEST['workDays'] != 1 && $_REQUEST['weekends'] != 1) ||
				!isset($_REQUEST['isDefault'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$dayTypes = array();
			if ($_REQUEST['workDays'] == 1)
				$dayTypes[] = 'work';
			if ($_REQUEST['weekends'] == 1)
				$dayTypes[] = 'weekend';
			$dayTypes = join(',', $dayTypes);
			$isDefault = ($_REQUEST['isDefault'] == 1 ? 1 : 0);
			$req = $mysqli->prepare("INSERT INTO `divServicesSLA` (`contract_id`, `service_id`, `divType_id`, `slaLevel`, `dayType`, ".
										"`toReact`, `toFix`, `toRepair`, `quality`, `startDayTime`, `endDayTime`, `isDefault`) ".
										"VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `toReact` = VALUES(`toReact`), ".
										"`toFix` = VALUES(`toFix`), `toRepair` = VALUES(`toRepair`), `quality` = VALUES(`quality`), ".
										"`startDayTime` = VALUES(`startDayTime`), `endDayTime` = VALUES(`endDayTime`), `isDefault` = VALUES(`isDefault`)");
			$req->bind_param('iiissiiiissi', $cId, $srv, $type, $_REQUEST['slaLevel'], $dayTypes, $toReact, $toFix, $toRepair, 
							$quality, $_REQUEST['startDay'], $_REQUEST['endDay'], $isDefault);
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
	$req = $mysqli->prepare("SELECT `sla`.`id`, `srv`.`id`, `srv`.`name`, `srv`.`shortname`, `dt`.`id`, `dt`.`name`, `dt`.`comment`, `sla`.`slaLevel`, ".
								"`sla`.`toReact`, `sla`.`toFix`, `sla`.`toRepair`, `sla`.`quality`, `sla`.`startDayTime`, `sla`.`endDayTime`, ".
								"FIND_IN_SET('work', `sla`.`dayType`), FIND_IN_SET('weekend', `sla`.`dayType`), `sla`.`isDefault` ".
							  "FROM `divServicesSLA` AS `sla` ".
							  "LEFT JOIN `services` AS `srv` ON `srv`.`id` = `sla`.`service_id` ".
							  "LEFT JOIN `divisionTypes` AS `dt` ON `dt`.`id` = `sla`.`divType_id` ".
							  "WHERE `sla`.`contract_id` = ? ".
							  "ORDER BY `srv`.`name`, `dt`.`name`, `sla`.`slaLevel`");
	$req->bind_param('i', $cId);
	$req->bind_result($slaId, $srvId, $srvName, $srvShort, $typeId, $typeName, $typeComment, $slaLevel, $toReact, $toFix, $toRepair, $quality, $startTime, 
						$endTime, $workDays, $weekends, $isDefault);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$req->store_result();
	$tbl =	"<table class='contSlaTbl'>".
				"<thead><tr><th><th>Сервис<th>Тип<br>филиала<th>Уровень<br>SLA<th>Время<br>реакции<th>Время до<br>восстановления<th>".
				"Время до<br>завершения<th>Качество<th>Начало дня<th>Конец дня<th>Рабочие<br>дни<th>Выходные<th>По<br>умолчанию<th><tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr id={$slaId}><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td data-id='{$srvId}'><abbr title='{$srvName}'>{$srvShort}</abbr>".
				"<td data-id='{$typeId}'><abbr title='{$typeComment}'>{$typeName}</abbr><td data-id='{$slaLevel}'>{$slaLevels[$slaLevel]}".
				"<td>".minutes_to_str($toReact)."<td>".minutes_to_str($toFix)."<td>".minutes_to_str($toRepair)."<td>{$quality}%".
				"<td>{$startTime}<td>{$endTime}<td><input type='checkbox' value='1' disabled".($workDays == 0 ? "" : " checked").">".
				"<td><input type='checkbox' value='1' disabled".($weekends == 0 ? "" : " checked").">".
				"<td><input type='checkbox' value='1' disabled".($isDefault == 0 ? "" : " checked")."><td><span class='ui-icon ui-icon-clipboard'> </span>";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td><td><td><td><td><td><td><td></table>";
	$req->close();	
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>