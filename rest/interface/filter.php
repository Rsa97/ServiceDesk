<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	
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
	
// Строим список доступных контрагентов и подразделений
	try {
		$req = $db->prepare("SELECT DISTINCT `ca`.`name`, HEX(`c`.`guid`), `c`.`number`, HEX(`div`.`guid`), `div`.`name` ".
								"FROM `contragents` AS `ca` ".
									"JOIN `contracts` AS `c` ON `ca`.`guid` = `c`.`contragent_guid` ".
									"JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` ".
									$join.
									(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
        	                	    "ORDER BY `ca`.`name`, `c`.`number`, `div`.`name`");
		$req->execute($reqVars);
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера',
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	$prevContragent = "";
	$prevContract = "";
	$divFilter = array();
	$iContragent = -1;
	$iContract = -1;
	$iDivision = 0;
	while ($row = $req->fetch(PDO::FETCH_NUM)) {
		list($contragentName, $contractGuid, $contractNumber, $divisionGuid, $divisionName) = $row;
		if ($contragentName != $prevContragent) {
			$divFilter[++$iContragent] = array('name' => $contragentName, 'contracts' => array());
			$iContract = -1;
			$prevContragent = $contragentName;
		}
		if ($contractGuid != $prevContract) {
			$divFilter[$iContragent]['contracts'][++$iContract] = 
				array('name' => $contractNumber, 'guid' => $contractGuid, 'divisions' => array());
			$iDivision = 0;
			$prevContract = $contractGuid;
		}
		$divFilter[$iContragent]['contracts'][$iContract]['divisions'][$iDivision++] =
			array('name' => $divisionName, 'guid' => $divisionGuid);
	}
	
// Строим список доступных услуг
try {
	$req = $db->prepare("SELECT DISTINCT HEX(`s`.`guid`), `s`.`name` ".
						    "FROM `contracts` AS `c` ".
							"JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` ".
							$join. 
    						"JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid` ".
    						"JOIN `services` AS `s` ON `s`.`guid` = `cs`.`service_guid` ".
							(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
    						"ORDER BY `s`.`name`");
	$req->execute($reqVars);
} catch (PDOException $e) {
	echo json_encode(array(	'result' => 'ok',
							'error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}

$srvFilter = array();
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($serviceGuid, $serviceName) = $row;
	$srvFilter[] = array('guid' => $serviceGuid, 'name' => $serviceName);  
}
$bySrv .= "</select>\n";
	
	
	echo json_encode(array('result' => 'ok', 'division_filter' => $divFilter, 'service_filter' => $srvFilter, 
							'expireTime' => 24*60*60));
?>

