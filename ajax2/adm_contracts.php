<?php
	include('common.php');

	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	function getContractUsers($mysqli, $contractId) {
		$req = $mysqli->prepare("SELECT `u`.`firstName`, `u`.`lastName`, `u`.`middleName` ".
									"FROM `users` AS `u` ".
									"JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = UNHEX(REPLACE(?, '-', '')) ".
										"AND `uc`. `user_guid` = `u`.`guid` ".
									"ORDER BY `u`.`lastName`, `u`.`firstName`, `u`.`middleName`");
		$req->bind_param('s', $contractId);
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
		$req =  $mysqli->prepare("SELECT `guid`, `number`, `contractStart` > CURDATE(), `contractEnd` < CURDATE() ".
									"FROM `contracts` ".
									"WHERE `contragent_guid` = UNHEX(REPLACE(?, '-', '')) ".
									"ORDER BY `number`");
		$req->bind_param('s', $contragentId);
		$req->bind_result($cId, $cNum, $early, $late);
		if (!$req->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		$list = array();
		while ($req->fetch()) {
			$contract = array('id' => formatGuid($cId), 'name' => htmlspecialchars($cNum));
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
	// Подключаемся к MySQL
	$mysqli = mysqli_init(); 
	$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, 3306, null, MYSQLI_CLIENT_FOUND_ROWS);
	if ($mysqli->connect_error) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$ret = array();
	$id = $paramValues['id'];
	$lastId = '';
	switch($paramValues['call']) {
		case 'getlists':
			switch($paramValues['field']) {
				case 'contragents':
					$req =  $mysqli->prepare("SELECT `ca`.`guid`, `ca`.`name` ".
												"FROM `contragents` AS `ca` ".
												"JOIN `contracts` AS `c` ON `c`.`contragent_guid` = `ca`.`guid` ".
												"ORDER BY `name`");
					$req->bind_result($caId, $caName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch())
						$list[] = array('id' => formatGuid($caId), 'name' => htmlspecialchars($caName)); 
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
													"`c`.`contractStart` > CURDATE(), DATE(`c`.`contractEnd`), `c`.`contractEnd` < CURDATE(), ".
													"`t`.`contract_guid` ". 
												"FROM `contracts` AS `c` ". 
    											"LEFT JOIN ( ". 
													"SELECT DISTINCT `contract_guid` FROM `divServicesSLA` ".
//													"UNION SELECT `contract_guid` FROM `replacement` ".
												") AS `t` ON `c`.`guid` = `t`.`contract_guid` ". 
    											"WHERE `c`.`guid` = UNHEX(REPLACE(?, '-', ''))");
					$req->bind_param('s', $id);
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
/*					$req = $mysqli->prepare("SELECT `u`.`firstName`, `u`.`lastName`, `u`.`middleName` ".
												"FROM `users` AS `u` ".
												"JOIN `userContracts` AS `uc` ON `uc`.`contracts_guid` = UNHEX(REPLACE('?', '-', '')) ".
													"AND  `uc`. `user_guid` = `u`.`guid` ".
												"ORDER BY `u`.`lastName`, `u`.`firstName`, `u`.`middleName`");
					$req->bind_param('s', $id);
					$req->bind_result($gn, $fn, $mn);									
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$names = array();
					while ($req->fetch())
						$names[] = htmlspecialchars($fn.($gn != '' ? ' '.$gn : '').($mn != '' ? ' '.$mn : ''));
					$req->close(); */
					$names = join(', ', getContractUsers($mysqli, $id));
					if ($notDel != '' || $names != '')
						$ret['notDel'] = 1;
					$ret['main']['cUsers'] = $names;
					$req =  $mysqli->prepare("SELECT `div`.`guid`, `div`.`name`, IFNULL(`eq`.`total`, 0), IFNULL(`eq`.`onService`, 0), ". 
													"`div`.`isDisabled`, `f`.`free` ". 
    											"FROM `contractDivisions` AS `div` ". 
    											"LEFT JOIN ( ". 
													"SELECT `contractDivision_guid`, COUNT(contractDivision_guid) AS `total`, ". 
															"SUM(`onService`) AS `onService` ". 
														"FROM `equipment` ". 
														"GROUP BY `contractDivision_guid` ". 
												") AS `eq` ON `eq`.`contractDivision_guid` = `div`.`guid` ". 
    											"LEFT JOIN ( ". 
													"SELECT `contract_guid`, COUNT(contract_guid) AS `free` ". 
														"FROM `equipment` ". 
            											"WHERE `contractDivision_guid` IS NULL ". 
            											"GROUP BY `contract_guid` ". 
												") AS `f` ON `f`.`contract_guid` = `div`.`contract_guid` ". 
    											"WHERE `div`.`contract_guid` = UNHEX(REPLACE(?, '-', '')) ".
    											"ORDER BY `div`.`name`");
					$req->bind_param('s', $id);
					$req->bind_result($divId, $divName, $eqTotal, $eqOnService, $disabled, $free);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$eqTotal += $free;
						$div = array('id' => formatGuid($divId), 'name' => htmlspecialchars($divName), 'count' => "");// 'count' => "($eqOnService / $eqTotal)");
						if ($disabled == 1)
							$div['mark'] = 'gray';
						$list[] = $div;
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
												"LEFT JOIN `userContracts` AS `r` ON `r`.`users_id` = `u`.`id` ".
													"AND `contracts_id` = ? ".
												"WHERE `u`.`rights` = 'client' AND `u`.`isDisabled` = 0 AND `u`.`loginDB` = 'mysql' ".
												"ORDER BY `u`.`secondName`, `u`.`firstName`, `u`.`middleName`");
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['caId']) || ($caId = $paramValues['caId']) < 0 ||
				!isset($paramValues['selContractIn']) || ($cNum = $paramValues['selContractIn']) == '' ||
				!isset($paramValues['cStartIn']) || !preg_match('/\d\d\d\d-\d\d-\d\d/', $paramValues['cStartIn']) ||
				!isset($paramValues['cEndIn']) || !preg_match('/\d\d\d\d-\d\d-\d\d/', $paramValues['cEndIn']) ||
				$paramValues['cStartIn'] > $paramValues['cEndIn'] || !isset($paramValues['cTelIn']) || !isset($paramValues['cEmailIn']) ||
				!isset($paramValues['cAddrIn']) || !isset($paramValues['cYurAddrIn'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$email = trim($paramValues['cEmailIn']);
			$tel = trim($paramValues['cTelIn']);
			$addr = trim($paramValues['cAddrIn']);
			$yurAddr = trim($paramValues['cYurAddrIn']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `contracts` (`number`, `email`, `phone`, `address`, `yurAddress`, ". 
											"`contractStart`, `contractEnd`, `contragents_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('sssssssi', $cNum, $email, $tel, $addr, $yurAddr, $paramValues['cStartIn'], $paramValues['cEndIn'], $caId);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contracts` SET `number` = ?, `email` = ?, `phone` = ?, `address` = ?, ".
											"`yurAddress` = ?, `contractStart` = ?, `contractEnd` = ? WHERE `id` = ?");
				$req->bind_param('sssssssi', $cNum, $email, $tel, $addr, $yurAddr, $paramValues['cStartIn'], $paramValues['cEndIn'], $id);
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
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