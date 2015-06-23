<?php
	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	function getContractUsers($mysqli, $contractId) {
		$req = $mysqli->prepare("SELECT `u`.`firstName`, `u`.`secondName`, `u`.`middleName` ".
									"FROM `users` AS `u` ".
									"JOIN `userContracts` AS `uc` ON `uc`. `users_id` = `u`.`id` ".
									"WHERE `uc`.`contracts_id` = ? ".
									"ORDER BY `secondName`, `firstName`, `middleName`");
		$req->bind_param('i', $contractId);
		$req->bind_result($gn, $fn, $mn);									
		if (!$req->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		$names = array();
		while ($req->fetch())
			$names[] = htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : ''));
		$req->close();
		return $names;
	}
	
	function getContractList($mysqli, $contragentId) {
		$req =  $mysqli->prepare("SELECT `id`, `number`, `contractStart` > CURDATE(), `contractEnd` < CURDATE() ".
									"FROM `contracts` ".
									"WHERE `contragents_id` = ? ".
									"ORDER BY `number`");
		$req->bind_param('i', $contragentId);
		$req->bind_result($cId, $cNum, $early, $late);
		if (!$req->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		$list = array();
		while ($req->fetch()) {
			$contract = array('id' => $cId, 'name' => htmlspecialchars($cNum));
			if ($early == 1)
				 $contract['mark'] = 'blue';
			if ($late == 1)
				$contract['mark'] = 'red';
			$list[] = $contract;
		}
		$req->close();
		return $list;
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
	if (!isset($_REQUEST['call'])) {
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
	switch($_REQUEST['call']) {
		case 'getlists':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) ||($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($_REQUEST['field']) {
				case 'contragents':
					$req =  $mysqli->prepare("SELECT `id`, `name` ".
												"FROM `contragents` ".
												"ORDER BY `name`");
					$req->bind_result($caId, $caName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch())
						$list[] = array('id' => $caId, 'name' => htmlspecialchars($caName)); 
					$req->close();
					returnJson(array('list' => $list));
					exit;
					break;
				case 'contracts':
					returnJson(array('list' => getContractList($mysqli, $id)));
					exit;
					break;
				case 'contract':
					$ret = array();
					$req = $mysqli->prepare("SELECT `c`.`email`, `c`.`phone`, `c`.`address`, `c`.`yurAddress`, DATE(`c`.`contractStart`), ".
												"`c`.`contractStart` > CURDATE(), DATE(`c`.`contractEnd`), `c`.`contractEnd` < CURDATE(), `t`.`id` ".
												"FROM `contracts` AS `c` ".
												"LEFT JOIN ( ".
													"SELECT DISTINCT `contract_id` AS `cid`, 1 AS `id` FROM `divServicesSLA` ".
													"UNION SELECT `contracts_id` AS `cid`, 1 AS `id` FROM `replacement` ".
												") AS `t` ON `c`.`id` = `t`.`cid` ".  
												"WHERE `c`.`id` = ? ");
					$req->bind_param('i', $id);
					$req->bind_result($email, $phone, $address, $yurAddress, $start, $early, $end, $late, $notDel);
					if (!$req->execute() || !$req->fetch()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					if ($early == 1)
						$ret['early'] = 1;
					if ($late == 1)
						$ret['late'] = 1;
					$ret['main'] = array('cTel' => $phone, 'cEmail' => $email, 'cAddr' => $address, 'cYurAddr' => $yurAddress, 
										 'cStart' => $start, 'cEnd' => $end);
					$req->close();
					$req = $mysqli->prepare("SELECT `u`.`firstName`, `u`.`secondName`, `u`.`middleName` ".
												"FROM `users` AS `u` ".
												"JOIN `userContracts` AS `uc` ON `uc`. `users_id` = `u`.`id` ".
												"WHERE `uc`.`contracts_id` = ? ".
												"ORDER BY `secondName`, `firstName`, `middleName`");
					$req->bind_param('i', $id);
					$req->bind_result($gn, $fn, $mn);									
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$names = array();
					while ($req->fetch())
						$names[] = htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : ''));
					$req->close();
					$names = join(', ', getContractUsers($mysqli, $id));
					if ($notDel != '' || $names != '')
						$ret['notDel'] = 1;
					$ret['main']['cUsers'] = $names;
					$req =  $mysqli->prepare("SELECT `d`.`id`, `d`.`name`, IFNULL(`eq`.`count`, 0), IFNULL(`os`.`count`, 0) ".
												"FROM `contractDivisions` AS `d`".
												"LEFT JOIN ( ".
												    "SELECT `contractDivisions_id`, COUNT(contractDivisions_id) AS `count` ".
												    "FROM `equipment` ".
												    "GROUP BY `contractDivisions_id` ".
												") AS `eq` ON `eq`.`contractDivisions_id` = `d`.`id` ".
												"LEFT JOIN ( ".
												    "SELECT `contractDivisions_id`, COUNT(contractDivisions_id) AS `count` ".
												    "FROM `equipment` ".
												    "WHERE `onService` = 1 ".
												    "GROUP BY `contractDivisions_id` ".
												") AS `os` ON `os`.`contractDivisions_id` = `d`.`id` ".
												"WHERE `d`.`contracts_id` = ? ".
												"ORDER BY `d`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($divId, $divName, $eqTotal, $eqOnService);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$list[] = array('id' => $divId, 'name' => htmlspecialchars($divName), 'count' => "($eqOnService / $eqTotal)");
					}
					$req->close();
					$ret['list'] = $list;
					returnJson($ret);
					exit;
					break;
				case 'users':
					$req =  $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, ".
												"`t`.`users_id`, `r`.`users_id` ".
												"FROM `users` AS `u` ".
												"LEFT JOIN ( ".
													"SELECT DISTINCT `users_id` FROM `userContracts` ".
												") AS `t` ON `t`.`users_id` = `u`.`id` ".
												"LEFT JOIN ( ".
													"SELECT `users_id` FROM `userContracts` WHERE `contracts_id` = ? ". 
												") AS `r` ON `r`.`users_id` = `u`.`id` ".
												"WHERE `rights` = 'client' AND `isDisabled` = 0 AND `loginDB` = 'mysql' ".
												"ORDER BY `secondName`, `firstName`, `middleName`");
					$req->bind_param('i', $id);
					$req->bind_result($uid, $gn, $fn, $mn, $isFree, $isSelected);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					$selected = array();
					while ($req->fetch()) {
						$user = array('id' => $uid, 'name' => htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : '')));
						if ($isFree == '')
							$user['mark'] = 'green';
						if ($isSelected == '')
							$total[] = $user;
						else 
							$selected[] = $user;
					}
					$req->close();
					returnJson(array('total' => $total, 'selected' => $selected, 'mode' => 'flat'));
					exit;
					break; 
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			break;
		case 'updatelists':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$list = array();
			if (isset($_REQUEST['list'])) {
				$list = array();
				foreach ($_REQUEST['list'] as $uid) {
					if (preg_match('/^(\d+)$/', $uid, $match))
						$list[] = $match[1];
				}
			}
			switch($_REQUEST['field']) {
				case 'users':
					$list1 = join(',', $list);
					$req =  $mysqli->prepare("DELETE FROM `userContracts` WHERE `contracts_id` = ? ".
												($list1 != '' ? "AND `users_id` NOT IN ({$list1})" : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($list1 != '') {
						$list2 = "({$id}, ".join("), ({$id}, ", $list).")";
						$req = $mysqli->prepare("INSERT IGNORE INTO `userContracts` (`contracts_id`, `users_id`) VALUES {$list2}");
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
						$req->close();
					}
					$names = join(', ', getContractUsers($mysqli, $id));
					returnJson(array('cUsers' => $names));
					exit;
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			$lastId = $id;
			break;
		case 'update':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0 ||
				!isset($_REQUEST['caId']) || ($caId = $_REQUEST['caId']) < 0 ||
				!isset($_REQUEST['selContractIn']) || ($cNum = $_REQUEST['selContractIn']) == '' ||
				!isset($_REQUEST['cStartIn']) || !preg_match('/\d\d\d\d-\d\d-\d\d/', $_REQUEST['cStartIn']) ||
				!isset($_REQUEST['cEndIn']) || !preg_match('/\d\d\d\d-\d\d-\d\d/', $_REQUEST['cEndIn']) ||
				$_REQUEST['cStartIn'] > $_REQUEST['cEndIn'] || !isset($_REQUEST['cTelIn']) || !isset($_REQUEST['cEmailIn']) ||
				!isset($_REQUEST['cAddrIn']) || !isset($_REQUEST['cYurAddrIn'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$email = trim($_REQUEST['cEmailIn']);
			$tel = trim($_REQUEST['cTelIn']);
			$addr = trim($_REQUEST['cAddrIn']);
			$yurAddr = trim($_REQUEST['cYurAddrIn']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `contracts` (`number`, `email`, `phone`, `address`, `yurAddress`, ". 
											"`contractStart`, `contractEnd`, `contragents_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('sssssssi', $cNum, $email, $tel, $addr, $yurAddr, $_REQUEST['cStartIn'], $_REQUEST['cEndIn'], $caId);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contracts` SET `number` = ?, `email` = ?, `phone` = ?, `address` = ?, ".
											"`yurAddress` = ?, `contractStart` = ?, `contractEnd` = ? WHERE `id` = ?");
				$req->bind_param('sssssssi', $cNum, $email, $tel, $addr, $yurAddr, $_REQUEST['cStartIn'], $_REQUEST['cEndIn'], $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
 			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
	 		}
			$req->close();
			returnJson(array('list' => getContractList($mysqli, $caId), 'last' => $lastId));
			break;			
		case 'del':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contracts` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			returnJson(array('ok' => 1));
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
?>