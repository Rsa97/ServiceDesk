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
		case 'fillSelects':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$users = '';
			$req = $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, `t`.`contracts_id` ".
									  "FROM `users` AS `u` ".
									  "LEFT JOIN (SELECT `users_id`, `contracts_id` FROM `userContracts` WHERE `contracts_id` = ?) ".
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
									  "LEFT JOIN (SELECT `contragents_id` FROM `contracts` WHERE `id` = ?) ".
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
			echo json_encode($ret);
			exit;
			break;
		case 'delContract':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contracts` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Невозможно удалить договор или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break; 
		case 'updateContract':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addContract':
			if (!isset($_REQUEST['number']) || ($number = $_REQUEST['number']) == '' ||
				!isset($_REQUEST['ca']) || ($ca = $_REQUEST['ca']) <= 0 ||
				!isset($_REQUEST['email']) || !isset($_REQUEST['phone']) ||
				!isset($_REQUEST['address']) || !isset($_REQUEST['yurAddress']) ||
				!isset($_REQUEST['start']) || ($start = $_REQUEST['start']) == '' ||
				!isset($_REQUEST['end']) || ($end = $_REQUEST['end']) == '' ||
				!isset($_REQUEST['users'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `contracts` (`number`, `email`, `phone`, `address`, `yurAddress`, ".
											"`contractStart`, `contractEnd`, `contragents_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('sssssssi', $number, $_REQUEST['email'], $_REQUEST['phone'], $_REQUEST['address'], $_REQUEST['yurAddress'], 
									$start, $end, $ca);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contracts` SET `number` = ?, `email` = ?, `phone` = ?, `address` = ?, `yurAddress` = ?, ".
											"`contractStart` = ?, `contractEnd` = ?, `contragents_id` = ? WHERE `id` = ?");
				$req->bind_param('sssssssii', $number, $_REQUEST['email'], $_REQUEST['phone'], $_REQUEST['address'], $_REQUEST['yurAddress'], 
									$start, $end, $ca, $id);
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
			$req = $mysqli->prepare('DELETE FROM `userContracts` WHERE `contracts_id` = ?');
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			if ($_REQUEST['users'] == '')
				break;
			$req = $mysqli->prepare('INSERT IGNORE INTO `userContracts` (`users_id`, `contracts_id`) VALUES (?, ?)');
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
	$req = $mysqli->prepare("SELECT `c`.`id`, `c`.`number`, `c`.`email`, `c`.`phone`, `c`.`address`, `c`.`yurAddress`, ".
							  "DATE(`c`.`contractStart`), DATE(`c`.`contractEnd`), `ca`.`id`, `ca`.`name` ".
							  "FROM `contracts` AS `c` ".
							  "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
							  "WHERE `c`.`id` > 0 ".
							  "ORDER BY `c`.`number`");
	$req->bind_result($id, $number, $email, $phone, $address, $yurAddress, $start, $end, $caId, $caName);
	$req1 = $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName` ".
							   "FROM `userContracts` AS `uc` ".
							   "LEFT JOIN `users` AS `u` ON `u`.`id` = `uc`.`users_id` ".
							   "WHERE `uc`.`contracts_id` = ? ".
							   "ORDER BY `u`.`secondName`, `u`.`firstName`, `u`.`middleName`");
	$req1->bind_param('i', $id);
	$req1->bind_result($uId, $uGivenName, $uFamilyName, $uMiddleName);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$req->store_result();
	$tbl =	"<table class='contractsTbl'>".
				"<thead><tr><th><th>Номер<br>договора<th>Контрагент<th>E-mail<th>Телефон<th>Адрес<th>Юридический<br>адрес".
				"<th>Начало<br>действия<th>Конец<br>действия<th>Ответственные".
				"<tbody>";
	while ($req->fetch()) {
		$tbl .= "<tr data-id='{$id}'><td><span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span>".
				"<td>{$number}<td data-id='{$caId}'>{$caName}<td>{$email}<td>{$phone}<td>{$address}<td>{$yurAddress}<td>{$start}<td>${end}<td><ul>";
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
		$tbl .= "</ul>";
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td><td><td><td><td></table>";
	$req1->close();
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>