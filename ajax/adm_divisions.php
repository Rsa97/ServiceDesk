<?php
	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	function getDivisionUsers($mysqli, $divId) {
		$req = $mysqli->prepare("SELECT `u`.`firstName`, `u`.`secondName`, `u`.`middleName` ".
									"FROM `users` AS `u` ".
									"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`users_id` = `u`.`id` ".
									"WHERE `ucd`.`contractDivisions_id` = ? ".
									"ORDER BY `secondName`, `firstName`, `middleName`");
		$req->bind_param('i', $divId);
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
	
	function getDivisionPartners($mysqli, $divId) {
		$req = $mysqli->prepare("SELECT `p`.`name` ".
									"FROM `partner` AS `p` ".
									"JOIN `allowedContracts` AS `ac` ON `ac`.`partner_id` = `p`.`id` ".
									"WHERE `ac`.`contractDivisions_id` = ? ".
									"ORDER BY `name`");
		$req->bind_param('i', $divId);
		$req->bind_result($partner);
		if (!$req->execute()) {
			returnJson(array('error' => 'Внутренняя ошибка сервера.'));
			exit;
		}
		$names = array();
		while ($req->fetch())
			$names[] = htmlspecialchars($partner);
		$req->close();
		return $names;
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
		case 'init':
			$ret = array();
			if (!isset($_REQUEST['id']) ||($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("SELECT `d`.`name`, `d`.`email`, `d`.`phone`, `d`.`address`, `d`.`yurAddress`, `t`.`name`, ".
										"`c`.`name`, `u`.`id` ".
										"FROM `contractDivisions` AS `d` ".
										"LEFT JOIN `divisionTypes` AS `t` ON `t`.`id` = `d`.`type_id` ".
										"LEFT JOIN `contragents` AS `c` ON `c`.`id` = `d`.`contragents_id` ". 
										"LEFT JOIN ( ".
											"SELECT DISTINCT `contractDivisions_id` AS `id` FROM `replacementLog` ".
											"UNION SELECT `contractDivisions_id` AS `id` FROM `replacement` ".
											"UNION SELECT `contractDivisions_id` AS `id` FROM `equipment` ".
											"UNION SELECT `contractDivisions_id` AS `id` FROM `request` ".
										") AS `u` ON `u`.`id` = `d`.`id` ".
										"WHERE `d`.`id` = ?");
			$req->bind_param('i', $id);
			$req->bind_result($divName, $divEmail, $divTel, $divAddr, $divYurAddr, $divType, $contragent, $inUse);	
			if (!$req->execute() || !$req->fetch()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$ret['main'] = array('dContragent' => $contragent, 'dType' => $divType, 'dTel' => $divTel, 'dEmail' => $divEmail, 
								 'dAddr' => $divAddr, 'dYurAddr' => $divYurAddr);
			$req->close();
			$names = join(', ', getDivisionUsers($mysqli, $id));
			$ret['main']['dUsers'] = $names;
			$partners = join('<br>', getDivisionPartners($mysqli, $id));
			$ret['main']['dPartners'] = $partners;
			if ($inUse != '' || $names != '' || $partners != '')
				$ret['notDel'] = 1;
			returnJson($ret);
			exit;
			break;
		case 'getlists':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) ||($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($_REQUEST['field']) {
				case 'dContragent':
					$req =  $mysqli->prepare("SELECT `c`.`id`, `c`.`name`, `d`.`id` ".
												"FROM `contragents` AS `c` ".
												"LEFT JOIN `contractDivisions` AS `d` ON `d`.`contragents_id` = `c`.`id` AND `d`.`id` = ? ".
												"ORDER BY `c`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($caId, $caName, $cur);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$contragent = array('id' => $caId, 'name' => htmlspecialchars($caName));
						if ($cur != '')
							$contragent['cur'] = 1;
						$list[] = $contragent;
					} 
					$req->close();
					returnJson(array('list' => $list));
					exit;
					break;
				case 'dType':
					$req =  $mysqli->prepare("SELECT `t`.`id`, `t`.`name`, `d`.`id` ".
												"FROM `divisionTypes` AS `t` ".
												"LEFT JOIN `contractDivisions` AS `d` ON `d`.`type_id` = `t`.`id` AND `d`.`id` = ? ".
												"ORDER BY `t`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($typeId, $typeName, $cur);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array();
					while ($req->fetch()) {
						$type = array('id' => $typeId, 'name' => htmlspecialchars($typeName));
						if ($cur != '')
							 $type['cur'] = 1;
						$list[] = $type;
					}
					$req->close();
					returnJson(array('list' => $list));
					exit;
					break;
				case 'users':
					$req =  $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, ".
												"`t`.`users_id`, `r`.`users_id` ".
												"FROM `users` AS `u` ".
												"LEFT JOIN ( ".
													"SELECT DISTINCT `users_id` FROM `userContractDivisions` ".
												") AS `t` ON `t`.`users_id` = `u`.`id` ".
												"LEFT JOIN ( ".
													"SELECT `users_id` FROM `userContractDivisions` WHERE `contractDivisions_id` = ? ". 
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
				case 'partners':
					$req =  $mysqli->prepare("SELECT `p`.`id`, `p`.`name`, `t`.`partner_id`, `r`.`partner_id` ".
												"FROM `partner` AS `p` ".
												"LEFT JOIN ( ".
													"SELECT DISTINCT `partner_id` FROM `allowedContracts` ".
												") AS `t` ON `t`.`partner_id` = `p`.`id` ".
												"LEFT JOIN ( ".
													"SELECT `partner_id` FROM `allowedContracts` WHERE `contractDivisions_id` = ? ". 
												") AS `r` ON `r`.`partner_id` = `p`.`id` ".
												"ORDER BY `p`.`name`");
					$req->bind_param('i', $id);
					$req->bind_result($partnerId, $partnerName, $isFree, $isSelected);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$total = array();
					$selected = array();
					while ($req->fetch()) {
						$partner = array('id' => $partnerId, 'name' => htmlspecialchars($partnerName));
						if ($isFree == '')
							$partner['mark'] = 'green';
						if ($isSelected == '')
							$total[] = $partner;
						else 
							$selected[] = $partner;
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
					$req =  $mysqli->prepare("DELETE FROM `userContractDivisions` WHERE `contractDivisions_id` = ? ".
												($list1 != '' ? "AND `users_id` NOT IN ({$list1})" : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($list1 != '') {
						$list2 = "({$id}, ".join("), ({$id}, ", $list).")";
						$req = $mysqli->prepare("INSERT IGNORE INTO `userContractDivisions` (`contractDivisions_id`, `users_id`) VALUES {$list2}");
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
						$req->close();
					}
					$names = join(', ', getDivisionUsers($mysqli, $id));
					returnJson(array('dUsers' => $names));
					exit;
					break;
				case 'partners':
					$list1 = join(',', $list);
					$req =  $mysqli->prepare("DELETE FROM `allowedContracts` WHERE `contractDivisions_id` = ? ".
												($list1 != '' ? "AND `partner_id` NOT IN ({$list1})" : ""));
					$req->bind_param('i', $id);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$req->close();
					if ($list1 != '') {
						$list2 = "({$id}, ".join("), ({$id}, ", $list).")";
						$req = $mysqli->prepare("INSERT IGNORE INTO `allowedContracts` (`contractDivisions_id`, `partner_id`) VALUES {$list2}");
						if (!$req->execute()) {
							returnJson(array('error' => 'Внутренняя ошибка сервера.'));
							exit;
						}
						$req->close();
					}
					$names = join('<br>', getDivisionPartners($mysqli, $id));
					returnJson(array('dPartners' => $names));
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
				!isset($_REQUEST['selDivisionIn']) || ($dName = $_REQUEST['selDivisionIn']) == '' ||
				!isset($_REQUEST['cId']) || ($contractId = $_REQUEST['cId']) == '' ||
				!isset($_REQUEST['dContragentIn']) || ($caId = $_REQUEST['dContragentIn']) <= 0 ||
				!isset($_REQUEST['dTypeIn']) || ($dType = $_REQUEST['dTypeIn']) <= 0 ||
				!isset($_REQUEST['dTelIn']) || !isset($_REQUEST['dEmailIn']) ||
				!isset($_REQUEST['dAddrIn']) || !isset($_REQUEST['dYurAddrIn'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$email = trim($_REQUEST['dEmailIn']);
			$tel = trim($_REQUEST['dTelIn']);
			$addr = trim($_REQUEST['dAddrIn']);
			$yurAddr = trim($_REQUEST['dYurAddrIn']);
			if ($id == 0) {
				$req = $mysqli->prepare("INSERT IGNORE INTO `contractDivisions` (`name`, `email`, `phone`, `address`, `yurAddress`, ". 
											"`contracts_id`, `contragents_id`, `type_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
				$req->bind_param('sssssiii', $dName, $email, $tel, $addr, $yurAddr, $contractId, $caId, $dType);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contractDivisions` SET `name` = ?, `email` = ?, `phone` = ?, `address` = ?, ".
											"`yurAddress` = ?, `contragents_id` = ?, `type_id` = ? WHERE `id` = ?");
				$req->bind_param('sssssiii', $dName, $email, $tel, $addr, $yurAddr, $caId, $dType, $id);
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
			$req->bind_param('i', $contractId);
			$req->bind_result($divId, $divName, $eqTotal, $eqOnService);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$list = array();
			while ($req->fetch()) {
				$list[] = array('id' => $divId, 'name' => $divName, 'count' => "($eqOnService / $eqTotal)");
			}
			returnJson(array('list' => $list, 'last' => $lastId));
			break;			
		case 'del':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `contractDivisions` WHERE `id` = ?");
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
			break;			 
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
?>