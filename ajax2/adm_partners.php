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
	$lastId = 0;
	switch($paramValues['call']) {
		case 'init':
			break;
		case 'getlists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) ||($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($paramValues['field']) {
				case 'users':
					$req =  $mysqli->prepare("SELECT `id`, `firstName`, `secondName`, `middleName`, `partner_id` ".
												"FROM `users` ".
												"WHERE `rights` = 'partner' AND `isDisabled` = 0 AND `loginDB` = 'mysql' ".
													"AND (`partner_id` != ? OR ISNULL(`partner_id`)) ".
												"ORDER BY `secondName`, `firstName`, `middleName`");
					$req->bind_param('i', $id);
					$req->bind_result($uid, $gn, $fn, $mn, $partnerId);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					while ($req->fetch()) {
						$user = array('id' => $uid, 'name' => htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : '')));
						if ($partnerId != '')
							$user['mark'] = 'red';
						$total[] = $user; 
					}
					$req->close();
					$req =  $mysqli->prepare("SELECT `id`, `firstName`, `secondName`, `middleName` ".
												"FROM `users` ".
												"WHERE `rights` = 'partner' AND `isDisabled` = 0 AND `partner_id` = ? ".
												"ORDER BY `secondName`, `firstName`, `middleName`");
					$req->bind_param('i', $paramValues['id']);
					$req->bind_result($uid, $gn, $fn, $mn);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$selected = array();
					while ($req->fetch()) {
						$selected[] = array('id' => $uid, 'name' => htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : '')));
					}
					$req->close();
					returnJson(array('total' => $total, 'selected' => $selected, 'mode' => 'flat'));
					exit;
					break;
				case 'contracts':
					$req =  $mysqli->prepare("SELECT `cd`.`id`, `cd`.`name`, `c`.`number`, `ca`.`name`, `ac`.`partner_id` ".
												"FROM `contractDivisions` AS `cd` ".
												"LEFT JOIN `allowedContracts` AS `ac` ON `ac`.`contractDivisions_id` = `cd`.`id` ".
												"JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
												"JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
												"WHERE (`ac`.`partner_id` != ? OR `ac`.`partner_id` IS NULL) ".
													"AND `cd`.`isDisabled` = 0 ".
												"ORDER BY `c`.`number`, `cd`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($divId, $divName, $contractNumber, $contragentName, $partnerId);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					$lastContract = '';
					$group = array();
					while ($req->fetch()) {
						$contract = htmlspecialchars($contractNumber.' - '.$contragentName);
						if ($contract != $lastContract) {
							if (count($group) > 0)
								$total[] = array('group' => $lastContract, 'items' => $group);
							$lastContract = $contract;
							$group = array();
						}
						$item = array('id' => $divId, 'name' => htmlspecialchars($divName));
						if ($partnerId != '')
							$item['mark'] = 'red';
						$group[] = $item;
					}
					if (count($group) > 0)
						$total[] = array('group' => $lastContract, 'items' => $group);
					$req =  $mysqli->prepare("SELECT `cd`.`id`, `cd`.`name`, `c`.`number`, `ca`.`name` ".
												"FROM `allowedContracts` AS `ac` ".
												"JOIN `contractDivisions` AS `cd` ON `cd`.`id` = `ac`.`contractDivisions_id` ".
												"JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
												"JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
												"WHERE `ac`.`partner_id` = ? ".
													"AND `cd`.`isDisabled` = 0 ".
												"ORDER BY `c`.`number`, `cd`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($divId, $divName, $contractNumber, $contragentName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$selected = array();
					$lastContract = '';
					$group = array();
					while ($req->fetch()) {
						$contract = htmlspecialchars($contractNumber.' - '.$contragentName);
						if ($contract != $lastContract) {
							if (count($group) > 0)
								$selected[] = array('group' => $lastContract, 'items' => $group);
							$lastContract = $contract;
							$group = array(); 							
						}
						$group[] = array('id' => $divId, 'name' => htmlspecialchars($divName));
					}
					if (count($group) > 0)
						$selected[] = array('group' => $lastContract, 'items' => $group);
					returnJson(array('total' => $total, 'selected' => $selected, 'mode' => 'tree'));
					exit;
					break; 
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			break;
		case 'updatelists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$list = array();
			if (isset($paramValues['list'])) {
				$list = array();
				foreach ($paramValues['list'] as $uid) {
					if (preg_match('/^(\d+)$/', $uid, $match))
						$list[] = $match[1];
				}
			}
			switch($paramValues['field']) {
				case 'users':
					$list = join(',', $list);
					$req =  $mysqli->prepare("UPDATE `users` ".
												"SET `partner_id` = NULL ".
												"WHERE `partner_id` = ? AND `rights` = 'partner' ".($list != '' ? ("AND `id` NOT IN ({$list})") : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($list != '') {
						$req =  $mysqli->prepare("UPDATE `users` ".
													"SET `partner_id` = ? ".
													"WHERE `id` IN ({$list})");
						$req->bind_param('i', $id);
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
						$req->close();
					}
					break;
				case 'contracts':
					$req = $mysqli->prepare("DELETE FROM `allowedContracts` ".
												"WHERE `partner_id` = ? ".
												(count($list) != 0 ? ("AND `contractDivisions_id` NOT IN (".join(',', $list).")") : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					$req = $mysqli->prepare("INSERT IGNORE INTO `allowedContracts` (`partner_id`, `contractDivisions_id`) VALUES (?, ?)");
					$req->bind_param('ii', $id, $divId);
					foreach ($list as $divId) {
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
					}
					$req->close(); 
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			$lastId = $id;
			break;
		case 'update':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['name']) || ($name = trim($paramValues['name'])) == '' ||
				!isset($paramValues['address'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$address = trim($paramValues['address']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `partner` (`name`, `address`) VALUES (?, ?)");
				$req->bind_param('ss', $name, $address);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `partner` SET `name` = ?, `address` = ? WHERE `id` = ?");
				$req->bind_param('ssi', $name, $address, $id);
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
			$req = $mysqli->prepare("DELETE IGNORE FROM `partner` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Партнёру назначены клиенты, есть сотрудники или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `guid`, `name`, `address` FROM `partners` ORDER BY `name`");
	$req->bind_result($servId, $name, $address);
	$req1 =  $mysqli->prepare("SELECT `firstName`, `lastName`, `middleName` ".
								"FROM `users` ".
								"WHERE `partner_guid` = UNHEX(REPLACE(?, '-', '')) AND `isDisabled` = 0 ".
								"ORDER BY `lastName`, `firstName`, `middleName`");
	$req1->bind_param('s', $servId);
	$req1->bind_result($gn, $fn, $mn);
	$req2 =  $mysqli->prepare("SELECT `cd`.`name`, `c`.`number`, `ca`.`name` ".
								"FROM `partnerDivisions` AS `ac` ".
								"JOIN `contractDivisions` AS `cd` ON `ac`.`partner_guid` = UNHEX(REPLACE(?, '-', '')) ".
									"AND `cd`.`guid` = `ac`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ".
								"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
								"JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
								"ORDER BY `c`.`number`, `cd`.`name`");
	$req2->bind_param('s', $servId);
	$req2->bind_result($divName, $contractNum, $contragentName);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	$req->store_result();
	while ($req->fetch()) {
		$servId = formatGuid($servId);
		$users = array();
		if (!$req1->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		while ($req1->fetch()) {
			$users[] = htmlspecialchars($fn.($gn == '' ? '' : ' '.$gn).($mn == '' ? '' : ' '.$mn));
		}
		$users = join('<br>', $users);
		$contracts = array();
		if (!$req2->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		$lastContract = '';
		$contracts = array();
		$divs = array();
		while ($req2->fetch()) {
			$contract = htmlspecialchars($contractNum.' - '.$contragentName); 
			if ($contract != $lastContract) {
				if (count($divs) > 0)
					$contracts[] = $lastContract.'<ul><li>'.join('<li>', $divs).'</ul>';
				$divs = array();
				$lastContract = $contract; 
			}
			$divs[] = htmlspecialchars($divName);
		}
		if (count($divs) > 0)
			$contracts[] = '<li>'.$lastContract.'<ul><li>'.join('<li>', $divs).'</ul>';
		$contracts = (count($contracts) == 0 ? '' : '<ul class="simple"><li>'.join('<li>', $contracts).'</ul>');
		$row = array('id' => $servId, 'fields' => array(htmlspecialchars($name), htmlspecialchars($address), $users, $contracts));
		if ($servId == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		if ($users != '' || $contracts != '')
			$row['notDel'] = 1;
		$tbl[] = $row;
		$i++;
	}
	returnJson(array('table' => $tbl, 'last' => $last));
?>