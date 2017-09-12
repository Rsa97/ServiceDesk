<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	$contragent_guid = (isset($params['contragent']) ? $params['contragent'] : null);
	
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
	
// Строим список доступных контрактов
	try {
		$req = $db->prepare("SELECT DISTINCT HEX(`c`.`guid`), `c`.`number`, `c`.`isActive`, `c`.`isStopped` ".
								"FROM `contracts` AS `c` ".
									"JOIN `contractDivisions` AS `div` ON `c`.`contragent_guid` = UNHEX(:contragent_guid) ".
										"AND `div`.`contract_guid` = `c`.`guid` ".
									$join.
									(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
        	                	    "ORDER BY `c`.`number`");
       	$reqVars['contragent_guid'] = $contragent_guid;
		$req->execute($reqVars);
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера',
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	$contracts = array();
	while ($row = $req->fetch(PDO::FETCH_NUM)) {
		list($contractGuid, $contractNumber, $isActive, $isStopped) = $row;
		$contracts[$contractGuid] = array(
				'number' => $contractNumber, 
				'state' => array('active' => $isActive, 'stopped' => $isStopped)
			);
	}
	
	echo json_encode(array('result' => 'ok', 'contracts' => $contracts, 'expireTime' => 3600));
?>