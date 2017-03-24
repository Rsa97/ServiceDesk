<?php
	include('common.php');

	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	function getDivisionUsers($mysqli, $divId) {
		$req = $mysqli->prepare("SELECT `u`.`firstName`, `u`.`lastName`, `u`.`middleName` ".
									"FROM `users` AS `u` ".
									"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = UNHEX(REPLACE(?, '-', '')) ".
										"AND `ucd`.`user_guid` = `u`.`guid` ".
									"ORDER BY `u`.`lastName`, `u`.`firstName`, `u`.`middleName`");
		$req->bind_param('s', $divId);
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
									"FROM `partners` AS `p` ".
									"JOIN `partnerDivisions` AS `ac` ON `ac`.`contractDivision_guid` = UNHEX(REPLACE(?, '-', ''))".
										"AND `ac`.`partner_guid` = `p`.`guid` ".
									"ORDER BY `name`");
		$req->bind_param('s', $divId);
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
	$lastId = 0;
	switch($paramValues['call']) {
		case 'init':
			$ret = array();
			$req = $mysqli->prepare("SELECT `d`.`name`, `d`.`email`, `d`.`phone`, `d`.`address`, `d`.`yurAddress`, `t`.`name`, ". 
											"`c`.`name`, `u`.`guid`, `d`.`isDisabled`, `o`.`guid` ". 
										"FROM `contractDivisions` AS `d` ". 
    									"LEFT JOIN `divisionTypes` AS `t` ON `t`.`guid` = `d`.`type_guid` ".
    									"LEFT JOIN `contragents` AS `c` ON `c`.`guid` = `d`.`contragent_guid` ".
    									"LEFT JOIN ( ".
        									"SELECT DISTINCT `contractDivision_guid` AS `guid` FROM `equipment` ".
        									"UNION SELECT `contractDivision_guid` AS `guid` FROM `requests` ".
//											"UNION SELECT `contractDivision_guid` AS `guid` FROM `replacementLog` ".
//											"UNION SELECT `contractDivision_guid` AS `guid` FROM `replacement` ".
										") AS `u` ON `u`.`guid` = `d`.`guid` ".
    									"LEFT JOIN ( ".
											"SELECT DISTINCT `contractDivision_guid` AS `guid` ".
												"FROM `requests` ".
												"WHERE `currentState` NOT IN ('closed','canceled') ".
										") AS `o` ON `o`.`guid` = `d`.`guid` ".
    									"WHERE `d`.`guid` = UNHEX(REPLACE(?, '-', ''))");
			$req->bind_param('s', $id);
			$req->bind_result($divName, $divEmail, $divTel, $divAddr, $divYurAddr, $divType, $contragent, $inUse, $disabled, $haveReq);	
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
			if ($haveReq != '')
				$ret['notDisable'] = 1;
			$ret['disabled'] = $disabled;
			returnJson($ret);
			exit;
			break;
		case 'getlists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) ||($id = $paramValues['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($paramValues['field']) {
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
												"LEFT JOIN `userContractDivisions` AS `r` ON `r`.`users_id` = `u`.`id` ". 
													"AND `contractDivisions_id` = ? ".
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
				case 'partners':
					$req =  $mysqli->prepare("SELECT `p`.`id`, `p`.`name`, `t`.`partner_id`, `r`.`partner_id` ".
												"FROM `partner` AS `p` ".
												"LEFT JOIN ( ".
													"SELECT DISTINCT `partner_id` FROM `allowedContracts` ".
												") AS `t` ON `t`.`partner_id` = `p`.`id` ".
												"LEFT JOIN `allowedContracts` AS `r` ON `r`.`partner_id` = `p`.`id` ". 
													"AND `contractDivisions_id` = ? ". 
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['selDivisionIn']) || ($dName = $paramValues['selDivisionIn']) == '' ||
				!isset($paramValues['cId']) || ($contractId = $paramValues['cId']) == '' ||
				!isset($paramValues['dContragentIn']) || ($caId = $paramValues['dContragentIn']) <= 0 ||
				!isset($paramValues['dTypeIn']) || ($dType = $paramValues['dTypeIn']) <= 0 ||
				!isset($paramValues['dTelIn']) || !isset($paramValues['dEmailIn']) ||
				!isset($paramValues['dAddrIn']) || !isset($paramValues['dYurAddrIn'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$email = trim($paramValues['dEmailIn']);
			$tel = trim($paramValues['dTelIn']);
			$addr = trim($paramValues['dAddrIn']);
			$yurAddr = trim($paramValues['dYurAddrIn']);
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
			$req =  $mysqli->prepare("SELECT `d`.`id`, `d`.`name`, IFNULL(`eq`.`total`, 0), IFNULL(`eq`.`onService`, 0) ".
										"FROM `contractDivisions` AS `d`".
										"LEFT JOIN ( ".
										    "SELECT `contractDivisions_id`, COUNT(contractDivisions_id) AS `total`, ".
										    		"SUM(`onService`) AS `onService` ".
										    "FROM `equipment` ".
										    "GROUP BY `contractDivisions_id` ".
										") AS `eq` ON `eq`.`contractDivisions_id` = `d`.`id` ".
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
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
			returnJson(array('ok' => 1));
			break;
		case 'serviceOn':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE IGNORE `contractDivisions` SET `isDisabled` = 0 WHERE `id` = ?");
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
		case 'serviceOff':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("SELECT `id` FROM `request` WHERE `contractDivisions_id` = ? AND `currentState` NOT IN ('closed','canceled') LIMIT 1");
			$req->bind_param('i', $id);
			$req->bind_result($tmp);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($req->fetch()) {
				returnJson(array('error' => 'По подразделению есть незакрытые заявки.'));
				exit;
			}
			$req->close();
			$req = $mysqli->prepare("UPDATE IGNORE `contractDivisions` SET `isDisabled` = 1 WHERE `id` = ?");
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
			break;
	}
?>