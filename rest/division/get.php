<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	$division_guid = (isset($params['guid']) ? $params['guid'] : null);
	
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
	
// Проверяем доступность филиала
	try {
		$req = $db->prepare("SELECT `div`.`name`, `div`.`email`, `div`.`phone`, `div`.`address`, `div`.`yurAddress`, HEX(`c`.`guid`), ".
									"`c`.`number`, `c`.`email`, `c`.`phone`, `c`.`address`, `c`.`yurAddress`, `c`.`contractStart`, ".
									"`c`.`contractEnd`, `c`.`isActive`, `c`.`isStopped`, HEX(`cca`.`guid`), `cca`.`name`, ".
									"`cca`.`fullName`, `cca`.`inn`, `cca`.`kpp`, HEX(`dca`.`guid`), `dca`.`name`, ".
									"`dca`.`fullName`, `dca`.`inn`, `dca`.`kpp`, HEX(`e`.`guid`), `e`.`lastName`, `e`.`firstName`, ".
									"`e`.`middleName`, `e`.`email`, `e`.`phone`, `e`.`address`, `div`.`isDisabled` ".
								"FROM `contracts` AS `c` ".
								"JOIN `contractDivisions` AS `div` ON `div`.`guid` = UNHEX(:division_guid) ".
									"AND `div`.`contract_guid` = `c`.`guid` ".
								$join.
								"LEFT JOIN `contragents` AS `cca` ON `cca`.`guid` = `c`.`contragent_guid` ".
								"LEFT JOIN `contragents` AS `dca` ON `dca`.`guid` = `div`.`contragent_guid` ".
								"LEFT JOIN `users` AS `e` ON `e`.`guid` = `div`.`engineer_guid` ".
								(count($where) > 0 ? "WHERE ".implode(' AND', $where) : ""));
		$reqVars['division_guid'] = $division_guid;
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
	list($name, $email, $phone, $address, $yurAddress, $contractGuid, $contractNumber, $contractEmail, $contractPhone, 
		 $contractAddress, $contractYurAddress, $contractStart, $contractEnd, $contractIsActive, $contractIsStopped, 
		 $contractCaGuid, $contractName, $contractCaFullName, $contractCaINN, $contractCaKPP, $divCaGuid, $divCaName,
		 $divCaFullName, $divCaINN, $divCaKPP, $engineerGuid, $engineerLN, $engineerFN, $engineerMN, $engineerEmail, 
		 $engineerPhone, $engineerAddress, $isDisabled) = $row;
	$division = array(
			'name' => $number,
			'contact' => array('email' => $email, 'phone' => $phone, 'address' => $address, 'yur_address' => $yurAddress),
			'state' => array('active' => (1-$isDisabled)),
			'contragent' => array('guid' => $divCaGuid, 'name' => $divCaName, 'full_name' => $divCaFullName, 
								  'inn' => $divCaINN, 'kpp' => $divCaKPP),
			'contract' => array(
					'guid' => $contractGuid, 'number' => $contractNumber,
					'contact' => array('email' => $contractEmail, 'phone' => $contractPhone, 'address' => $contractAddress, 
									   'yur_address' => $contractYurAddress),
					'state' => array('start_date' => $contractStart, 'end_date' => $contractEnd, 'active' => $contractIsActive, 
									 'stopped' => $contractIsStopped),
					'contragent' => array('guid' => $contractCaGuid, 'name' => $contractCaName, 'full_name' => $contractCaFullName, 
											'inn' => $contractCaINN, 'kpp' => $contractCaKPP)
				),
			'engineer' => array(
					'guid' => $engineerGuid,
					'fullName' => nameFull($engineerLN, $engineerFN, $engineerMN), 
					'shortName' => nameWithInitials($engineerLN, $engineerFN, $engineerMN),
					'email' => $engineerEmail, 'phone' => $engineerPhone, 'address' => $engineerAddress
			)
		);

	echo json_encode(array('result' => 'ok', 'division' => $division, 'expireTime' => 300));
?>