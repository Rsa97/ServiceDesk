<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	$contract_guid = (isset($params['guid']) ? $params['guid'] : null);
	
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
	try {
		$req = $db->prepare("SELECT `c`.`number`, `c`.`email`, `c`.`phone`, `c`.`address`, `c`.`yurAddress`, `c`.`contractStart`, ".
									"`c`.`contractEnd`, `c`.`isActive`, `c`.`isStopped`, HEX(`ca`.`guid`), `ca`.`name`, ".
									"`ca`.`fullName`, `ca`.`inn`, `ca`.`kpp` ".
								"FROM `contracts` AS `c` ".
									"JOIN `contractDivisions` AS `div` ON `c`.`guid` = UNHEX(:contract_guid) ".
										"AND `div`.`contract_guid` = `c`.`guid` ".
									$join.
									"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
									(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
        	                	    "ORDER BY `ca`.`name`");
		$reqVars['contract_guid'] = $contract_guid;
       	$req->execute($reqVars);
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера',
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	if (!($row = $req->fetch(PDO::FETCH_NUM))) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Неверный идентификатор контракта или недостаточно прав'));
		
	}
	list($number, $email, $phone, $address, $yurAddress, $start, $end, $isActive, $isStopped, $caGuid, $caName, $caFullName,
		$caINN, $caKPP) = $row;
	$contract = array(
			'number' => $number,
			'contact' => array('email' => $email, 'phone' => $phone, 'address' => $address, 'yur_address' => $yurAddress),
			'state' => array('start_date' => $start, 'end_date' => $end, 'active' => $isActive, 'stopped' => $isStopped),
			'contragent' => array('guid' => $caGuid, 'name' => $caName, 'full_name' => $caFullName, 'inn' => $caINN, 'kpp' => $caKPP)
		);

	echo json_encode(array('result' => 'ok', 'contract' => $contract, 'expireTime' => 300));
?>