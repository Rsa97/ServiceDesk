<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	$contragent_guid = (isset($params['guid']) ? $params['guid'] : null);
	
	$userData = getUserData($db, $user_guid);
	if ('ok' != $userData['result']) {
		echo json_encode($userData);
		exit;
	}

// Готовим фильтр SQL
	$join = "";
	$where = array();
	$reqVars = array();
	if ('client' == $userData['rights']) {
		$join .= "JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
				 "JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ";
		$where[] = "(`ucd`.`user_guid` = UNHEX(:user_guid, '-', '') ".
            	   "OR `uc`.`user_guid` = UNHEX(:user_guid, '-', '')) ";
		$reqVars['user_guid'] = $user_guid; 
	}
	if ('partner' == $userData['rights']) {
		$join .= "JOIN `partnerDivisions` AS `a` ON `a`.`partner_guid` = UNHEX(:partner_guid, '-', '') ".
					"AND `a`.`contractDivision_guid` = `div`.`guid` ";
		$reqVars['partner_guid'] = $partner_guid;
	}
	if ('admin' != $userData['rights']) {
		$where[] = "(`c`.`contractStart` <= NOW() AND `c`.`contractEnd` >= NOW()) ";
	}
	
// Проверяем доступность контрагента
	$reqVars['contragent_guid'] = $contragent_guid;
	try {
		$req = $db->prepare("SELECT `ca`.`name`, `ca`.`fullName`, `ca`.`inn`, `ca`.`kpp` ".
								"FROM `contragents` AS `ca` ".
									"JOIN `contracts` AS `c` ON `ca`.`guid` = UNHEX(:contragent_guid) ".
										"AND `ca`.`guid` = `c`.`contragent_guid` ".
									"JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` ".
									$join.
									(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
        	                	    "ORDER BY `ca`.`name`");
		$req->execute($reqVars);
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера',
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	if (!($row = $req->fetch(PDO::FETCH_NUM))) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Неверный идентификатор контрагента или недостаточно прав'));
		
	}
	list($name, $fullName, $INN, $KPP) = $row;
	$contragent = array('name' => $name, 'full_name' => $fullName, 'inn' => $INN, 'kpp' => $contragentKPP);

	echo json_encode(array('result' => 'ok', 'contragent' => $contragent, 'expireTime' => 3600));
?>