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
			$sel = "<div id='mdSelectors'>Договор: <select id='divContract'>";
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
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$users = '';
			$req = $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, `t`.`contractDivisions_id` ".
									  "FROM `users` AS `u` ".
									  "LEFT JOIN (SELECT `users_id`, `contractDivisions_id` ".
									  				"FROM `userContractDivisions` ".
									  				"WHERE `contractDivisions_id` = ?) ".
									    "AS `t` ON `u`.`id` = `t`.`users_id` ".
									  "WHERE `u`.`rights` = 'client' ".
									  "ORDER BY `u`.`secondName`, `u`.`firstName`, `u`.`middleName`");
			$req->bind_param('i', $id);
			$req->bind_result($uId, $uGivenName, $uFamilyName, $uMiddleName, $contract);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				$users .= "<label><abbr title='{$uFamilyName}".
					($uGivenName == '' ? '' : (' '.$uGivenName.($uMiddleName == '' ? '' : (' '.$uMiddleName)))).
					"'><input type='checkbox' name='userId' value='{$uId}'".
					($contract == '' ? '' : ' checked').">&nbsp;{$uFamilyName}".
					($uGivenName == '' ? '' : ('&nbsp;'.mb_substr($uGivenName, 0, 1, 'utf-8').'.'.
					($uMiddleName == '' ? '' : '&nbsp;'.mb_substr($uMiddleName, 0, 1, 'utf-8').'.')))."</label><abbr><br>";
			}
			$req->close();
			$ret['users'] = $users;
			$cas = '';
			$req = $mysqli->prepare("SELECT `ca`.`id`, `ca`.`name`, `t`.`contragents_id` ".
									  "FROM `contragents` AS `ca` ".
									  "LEFT JOIN (SELECT `contragents_id` FROM `contractDivisions` WHERE `id` = ?) ".
									    "AS `t` ON `ca`.`id` = `t`.`contragents_id` ".
									  "WHERE `ca`.`id` > 0 ".
									  "ORDER BY `ca`.`name`");
			$req->bind_param('i', $id);
			$req->bind_result($caId, $caName, $ca);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				$cas .= "<option value='{$caId}'".($ca == '' ? '' : ' selected').">".$caName;
			}
			$req->close();
			$ret['contragent'] = $cas;
			$types = '';
			$req = $mysqli->prepare("SELECT `dt`.`id`, `dt`.`name`, `t`.`type_id` ".
										"FROM `divisionTypes` AS `dt` ".
										"LEFT JOIN (SELECT `type_id` FROM `contractDivisions` WHERE `id` = ?) ".
											"AS `t` ON `dt`.`id` = `t`.`type_id` ".
										"ORDER BY `dt`.`name`");
			$req->bind_param('i', $id);
			$req->bind_result($typeId, $typeName, $ca);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			while ($req->fetch()) {
				$types .= "<option value='{$typeId}'".($ca == '' ? '' : ' selected').">".$typeName;
			}
			$req->close();
			$ret['type'] = $types;
			echo json_encode($ret);
			exit;
			break;
		case 'delDivision':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0 || 
				!isset($_REQUEST['contractId']) || ($cId = $_REQUEST['contractId']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contractDivisions` WHERE `id` = ?");
			$req->bind_param('i', $id);
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
		case 'updateDivision':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addDivision':
			if (!isset($_REQUEST['name']) || ($name = $_REQUEST['name']) == '' ||
				!isset($_REQUEST['ca']) || ($ca = intval($_REQUEST['ca'])) <= 0 ||
				!isset($_REQUEST['email']) || !isset($_REQUEST['phone']) ||
				!isset($_REQUEST['address']) || !isset($_REQUEST['yurAddress']) ||
				!isset($_REQUEST['contractId']) || ($cId = intval($_REQUEST['contractId'])) <= 0 ||
				!isset($_REQUEST['type']) || ($type = intval($_REQUEST['type'])) <= 0 ||
				!isset($_REQUEST['users'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `contractDivisions` (`name`, `email`, `phone`, `address`, `yurAddress`, ".
											"`contracts_id`, `contragents_id`, `type_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('sssssiii', $name, $_REQUEST['email'], $_REQUEST['phone'], $_REQUEST['address'], $_REQUEST['yurAddress'], 
									$cId, $ca, $type);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contractDivisions` SET `name` = ?, `email` = ?, `phone` = ?, `address` = ?, ".
											"`yurAddress` = ?, `contracts_id` = ?, `contragents_id` = ?, `type_id` = ? WHERE `id` = ?");
				$req->bind_param('sssssiiii', $name, $_REQUEST['email'], $_REQUEST['phone'], $_REQUEST['address'], $_REQUEST['yurAddress'], 
									$cId, $ca, $type, $id);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if (($id == 0 && $mysqli->insert_id <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0)
				$id = $mysqli->insert_id;
			$req->close();
			$req = $mysqli->prepare('DELETE FROM `userContractDivisions` WHERE `contractDivisions_id` = ?');
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			if ($_REQUEST['users'] == '')
				break;
			$req = $mysqli->prepare('INSERT IGNORE INTO `userContractDivisions` (`users_id`, `contractDivisions_id`) VALUES (?, ?)');
			$req->bind_param('ii', $uId, $id);
			foreach(explode('|', $_REQUEST['users']) as $uId) {
				if (!$req->execute()) {
					echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
					exit;
				}
			}
			$req->close();
			break;	
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `cd`.`id`, `cd`.`name`, `cd`.`email`, `cd`.`phone`, `cd`.`address`, `cd`.`yurAddress`, ".
							  "`ca`.`id`, `ca`.`name`, `total`.`count`, `on`.`count`, `ty`.`name`, `ty`.`comment` ".
							  "FROM `contractDivisions` AS `cd` ".
							  "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `cd`.`contragents_id` ".
							  "LEFT JOIN (SELECT `contractDivisions_id`, COUNT(*) AS `count` FROM `equipment` GROUP BY `contractDivisions_id`) ".
							      "AS `total` ON `total`.`contractDivisions_id` = `cd`.`id` ".
							  "LEFT JOIN (SELECT `contractDivisions_id`, COUNT(*) AS `count` FROM `equipment` WHERE onService = 1 GROUP BY `contractDivisions_id`) ".
							      "AS `on` ON `on`.`contractDivisions_id` = `cd`.`id` ".
							  "LEFT JOIN `divisionTypes` as `ty` ON `ty`.`id` = `cd`.`type_id` ".
							  "WHERE `cd`.`contracts_id` = ? ".
							  "ORDER BY `cd`.`name`");
	$req->bind_param('i', $cId);
	$req->bind_result($id, $name, $email, $phone, $address, $yurAddress, $caId, $caName, $eqTotal, $eqOnService, $type, $typeComment);
	$req1 = $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName` ".
							   "FROM `userContractDivisions` AS `ucd` ".
							   "LEFT JOIN `users` AS `u` ON `u`.`id` = `ucd`.`users_id` ".
							   "WHERE `ucd`.`contractDivisions_id` = ? ".
							   "ORDER BY `u`.`secondName`, `u`.`firstName`, `u`.`middleName`");
	$req1->bind_param('i', $id);
	$req1->bind_result($uId, $uGivenName, $uFamilyName, $uMiddleName);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$req->store_result();
	$tbl =	"<table class='divsTbl'>".
				"<thead><tr><th><th>Филиал<th>Контрагент<th>E-mail<th>Телефон<th>Адрес<th>Юридический<br>адрес".
				"<th>Ответственные<th>Тип<br>филиала<th>Единиц техники<br>на обслуживании<tbody>";
	$eqTotalTotal = 0;
	$eqOnServiceTotal = 0;
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$name}<td data-id='{$caId}'>{$caName}<td>{$email}<td>{$phone}<td>{$address}<td>{$yurAddress}<td><ul>";
		if (!$req1->execute()) {
			echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		while ($req1->fetch()) {
			$tbl .= "<li data-id='{$uId}'><abbr title='{$uFamilyName}".
					($uGivenName == '' ? '' : (' '.$uGivenName.($uMiddleName == '' ? '' : (' '.$uMiddleName)))).
					"'>{$uFamilyName}".								
					($uGivenName == '' ? '' : ('&nbsp;'.mb_substr($uGivenName, 0, 1, 'utf-8').'.'.
					($uMiddleName == '' ? '' : '&nbsp;'.mb_substr($uMiddleName, 0, 1, 'utf-8').'.')))."</label><abbr><br>";
			($uGivenName == '' ? '' : (' '.$uGivenName.($uMiddleName == '' ? '' : ' '.$uMiddleName)));
		}
		$tbl .= "</ul><td><abbr title='".htmlspecialchars($typeComment)."'>{$type}</abbr><td>";
		if ($eqTotal == '')
			$eqTotal = 0;
		if ($eqOnService == '')
			$eqOnService = 0;
		$tbl .= "{$eqOnService}";
		if ($eqTotal != $eqOnService) 
			$tbl .= "/{$eqTotal}";
		$eqTotalTotal += $eqTotal;
		$eqOnServiceTotal += $eqOnService;
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td><td><td><td><td>{$eqOnServiceTotal}";
	if ($eqTotalTotal != $eqOnServiceTotal) 
		$tbl .= "/{$eqTotalTotal}";
	$tbl .= "</table>";
	$req1->close();
	$req->close();	
	$ret['divs'] = $tbl;
	echo json_encode($ret);
?>