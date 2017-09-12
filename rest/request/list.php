<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/commonData.php");
	
	header('Content-Type: application/json; charset=UTF-8');
	
	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	
	$userData = getUserData($db, $user_guid);
	if ('ok' != $userData['result']) {
		echo json_encode($userData);
		exit;
	}
	
	$selType = (isset($params['type']) ? $params['type'] : 'all');
	$selContract = ((isset($params['contract']) && !isset($params['division'])) ? str_replace('-', '', $params['contract']) : null);
	$selDivision = (isset($params['division']) ? str_replace('-', '', $params['division']) : null);
	$selService = (isset($params['service']) ? str_replace('-', '', $params['service']) : null);
	$selFromDate = (isset($params['from']) ? $params['from'].' 00:00:00' : date('Y-m-d 00:00:00', strtotime('-3 months')));
	$selToDate = (isset($params['to']) ? $params['to'].' 23:59:59' : date('Y-m-d 00:00:00', strtotime('now')));
	$selOnlyMy = (isset($params['onlyMy']) ? $params['onlyMy'] : 0);
	$selText = (isset($params['text']) ? trim($params['text']) : '');

	$statusGroup = array('received' => 'received',
					 	'preReceived' => 'received',
					 	'accepted' => 'accepted',
					 	'fixed' => 'accepted',
					 	'repaired' => 'toClose',
					 	'closed' => 'closed',
					 	'canceled' => 'canceled');

	$groupStatus = array('received' => "'received','preReceived'",
					 	'accepted' => "'accepted','fixed'",
					 	'toClose' => "'repaired'",
					 	'closed' => "'closed'",
					 	'canceled' => "'canceled'");

	$sortOrder = array('received' => 'ASC',
					 	'accepted' => 'ASC',
					 	'repaired' => 'DESC',
					 	'closed' => 'DESC',
					 	'canceled' => 'DESC',
					 	'toClose' => 'DESC');
	
// Готовим фильтр прав для SQL
	$join = "";
	$firstJoin = array();
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
		$where[] = "`rq`.`partner_guid` = UNHEX(REPLACE(:partner_guid, '-', '')) ";
		$reqVars['partner_guid'] = $partner_guid;
	}
	if ('admin' != $userData['rights']) {
		$where[] = "(`c`.`contractStart` <= NOW() AND `c`.`contractEnd` >= NOW()) ";
	}
	if ('all' != $selType && 'planned' != $selType) {
		$firstJoin[] = "`rq`.`currentState` IN ({$groupStatus[$selType]})";
	}
// Считаем общее количество заявок
$totalCount = array();
if ('planned' != $selType) {
	try {
		$req = $db->prepare("SELECT `rq`.`currentState`, COUNT(*) ".
       		  					"FROM `requests` AS `rq` ".
           						"JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
           						(count($firstJoin) > 0 ? "AND ".implode(' AND', $firstJoin) : "").
           						"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
           						$join.
           						(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
   								"GROUP BY `rq`.`currentState`");
			$req->execute($reqVars);
	} catch (PDOException $e) {
		echo json_encode(array( 'resuilt' => 'error',
								'error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	while ($row = $req->fetch(PDO::FETCH_NUM)) {
		list($state, $count) = $row;
		if (!isset($totalCount[$statusGroup[$state]])) {
			$totalCount[$statusGroup[$state]] = 0;
		}
		$totalCount[$statusGroup[$state]] += $count;
	}
}
if ('planned' == $selType || 'all' == $selType) {
	$totalCount['planned'] = 0;
}

// Добавляем фильтр, заданный пользователем

if (null != $selContract) {
	 $firstJoin[] = "`div`.`contract_guid` = UNHEX(:contract_guid) ";
	 $reqVars['contract_guid'] = $selContract;
}
if (null != $selDivision) {
	 $firstJoin[] = "`rq`.`contractDivision_guid` = UNHEX(:division_guid) ";
	 $reqVars['division_guid'] = $selDivision;
}
if (null != $selService) {
	 $firstJoin[] = "`rq`.`service_guid` = UNHEX(:service_guid) ";
 	$reqVars['service_guid'] = $selService;
}

$requests = array();

if ('planned' == $selType || 'all' == $selType) {
	$requests['planned'] = array();
	if ('partner' != $userData['rights']) {
		try {
			$req = $db->prepare("SELECT DISTINCT `pr`.`id`, `pr`.`slaLevel`, `s`.`shortname`, `s`.`name`, `pr`.`nextDate`, `ca`.`name`, ".
  												"`div`.`name`, `pr`.`problem`, `pr`.`nextDate` <= DATE_ADD(NOW(), INTERVAL `pr`.`preStart` DAY), ".
  												"`div`.`addProblem`, `c`.`number` ".
	  								"FROM `plannedRequests` AS `pr` ".
    	    	    				"JOIN `contractDivisions` AS `div` ON `pr`.`contractDivision_guid` = `div`.`guid` ".
        	    						"AND `div`.`isDisabled` = 0 AND `pr`.`nextDate` < DATE_ADD(NOW(), INTERVAL 1 MONTH) ".
		   	    	    			(count($firstJoin) > 0 ? "AND ".implode(' AND', $firstJoin) : "").
        		    				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
	            					$join.
	            					"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
		            				"LEFT JOIN `services` AS `s` ON `s`.`guid` = `pr`.`service_guid` ".
   	    		   					(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
            						"ORDER BY `pr`.`nextDate`");
			$req->execute($reqVars);
		} catch (PDOException $e) {
			echo json_encode(array('result' => 'error',
									'error' => 'Внутренняя ошибка сервера', 
									'orig' => "MySQL error".$e->getMessage()));
			exit;
		}

		while ($row = $req->fetch(PDO::FETCH_NUM)) {
			list($id, $slaLevel, $srvSName, $srvName, $nextDate, $contragent, $div, $problem, $canPreStart, $divProblem, 
				$contragent) = $row;
			$requests['planned'][$id] = array(
				'canPreStart' => $canPreStart,
				'status' => array('name' => $statusNames['planned'], 'icon' => $statusIcons['planned']),
				'slaLevel' => $slaLevels[$slaLevel],
				'service' => array('name' => $srvName, 'shortName' => $srvSName),
				'nextDate' => date_format(date_create($nextDate), 'd.m.Y'),
				'contract' => $contractNumber,
				'division' => $div,
				'contragent' => $contragent,
				'problem' => $problem,
				'addProblem' => $divProblem
			);
		}
	}
}

if ('planned' != $selType) {
	
	$where[] = "`rq`.`createdAt` >= :from_date AND `rq`.`createdAt` <= :to_date ";
	$reqVars['from_date'] = $selFromDate;
	$reqVars['to_date'] = $selToDate;
	if (1 == $selOnlyMy) {
		$firstJoin[] = "(`rq`.`contactPerson_guid` = UNHEX(:user_guid) ".
        		    	"OR `rq`.`engineer_guid` = UNHEX(:user_guid)) ";
		$reqVars['user_guid'] = $user_guid;
	}
	if ('' != $selText) {
		$firstJoin[] = "`rq`.`problem` LIKE :text ";
		$reqVars['text'] = '%'.$selText.'%';
	}
	
	try {
		$req = $db->prepare("SELECT DISTINCT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangedAt`, `srv`.`shortName`, `srv`.`name`, `srv`.`autoOnly`, ". 
											"`rq`.`createdAt`, `rq`.`reactBefore`, `rq`.`fixBefore`, `rq`.`repairBefore`, `div`.`name`, ".
											"`ca`.`name`, `e`.`lastName`, `e`.`firstName`, `e`.`middleName`, `e`.`email`, `e`.`phone`, ".
											"`et`.`name`, `est`.`name`, `em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, ".
											"`eq`.`serialNumber`, `c`.`number`, `co`.`lastName`, `co`.`firstName`, `co`.`middleName`, `co`.`email`, ".
											"`co`.`phone`, CAST(`rq`.`problem` AS CHAR(1024)), `rq`.`onWait`, `rq`.`reactedAt`, ".
											"IFNULL(`rq`.`fixedAt`, `rq`.`repairedAt`), `rq`.`repairedAt`, `rq`.`slaLevel`, `rq`.`toReact`, `rq`.`toFix`, ".
											"`rq`.`toRepair`, `p`.`name`, `rq`.`guid`, `ow`.`onWaitAt`, ".
											"IFNULL(`rq`.`reactRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`reactedAt`, NOW())))/`rq`.`toReact`), ".
											"IFNULL(`rq`.`fixRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`fixedAt`, IFNULL(`rq`.`repairedAt`, NOW()))))/`rq`.`toFix`), ".
	    									"IFNULL(`rq`.`repairRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`repairedAt`, NOW())))/`rq`.`toRepair`) ".
		            			"FROM `requests` AS `rq` ".
    		        			"JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
    	    	    			(count($firstJoin) > 0 ? "AND ".implode(' AND', $firstJoin) : "").
        	    				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
        	    				$join.
            					"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
								"LEFT JOIN `partners` AS `p` ON `p`.`guid` = `rq`.`partner_guid` ".
	            				"LEFT JOIN `services` AS `srv` ON `srv`.`guid` = `rq`.`service_guid` ".
		            			"LEFT JOIN `users` AS `e` ON `e`.`guid` = `rq`.`engineer_guid` ".
    		        			"LEFT JOIN `users` AS `co` ON `co`.`guid` = `rq`.`contactPerson_guid` ".
        		    			"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `rq`.`equipment_guid` ".
            					"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ".
            					"LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`guid` = `em`.`equipmentSubType_guid` ".
            					"LEFT JOIN `equipmentTypes` AS `et` ON `et`.`guid` = `est`.`equipmentType_guid` ".
            					"LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`guid` = `em`.`equipmentManufacturer_guid` ".
		            			"LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` ".
    	    	    				"AND `dss`.`service_guid` = `rq`.`service_guid` ".
    		        				"AND `dss`.`divType_guid` = `div`.`type_guid` AND `dss`.`slaLevel` = `rq`.`slaLevel` ".
    	    	    			"LEFT JOIN (".
    								"SELECT MAX(`timestamp`) AS `onWaitAt`, `request_guid` ".
    									"FROM `requestEvents` ".
    									"WHERE `event` = 'onWait' ".
    									"GROUP BY `request_guid`".
	    						") AS `ow` ON `ow`.`request_guid` = `rq`.`guid` ".
    	       					(count($where) > 0 ? "WHERE ".implode(' AND', $where) : "").
    							"ORDER BY `rq`.`id`");
		$req->execute($reqVars);
	} catch (PDOException $e) {
		echo json_encode(array('result' => 'error',
								'error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	while ($row = $req->fetch(PDO::FETCH_NUM)) {
		list($id, $state, $stateTime, $srvSName, $srvName, $srvAutoOnly, $createdAt, $reactBefore, $fixBefore, $repairBefore, $div, $contragent, 
			 $engLN, $engGN, $engMN, $engEmail, $engPhone, $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $contractNumber, 
		 	$contLN, $contGN, $contMN, $contEmail, $contPhone, $problem, $onWait, $reactedAt, $fixedAt, $repairedAt, $slaLevel, 
		 	$timeToReact, $timeToFix, $timeToRepair, $partnerName, $requestGuid, $onWaitAt, $reactPercent, $fixPercent, $repairPercent) = $row;
		if (!isset($requests[$statusGroup[$state]])) {
			$requests[$statusGroup[$state]] = array();
		}

		$timeToReact *= 60;
		$timeToFix *= 60;
		$timeToRepair *= 60;
		if ($reactPercent > 1)
			$reactPercent = 1;
		if ($fixPercent > 1)
			$fixPercent = 1;
		if ($repairPercent > 1)
       		$repairPercent = 1;
	
		$reactComment = ($reactedAt == '' ? (1 == $onWait ? ("Приостановлено ".date_format(date_create($onWaitAt), 'd.m.Y H:i')) :
						("Принять до ".date_format(date_create($reactBefore), 'd.m.Y H:i'))) : 
						("Принято ".date_format(date_create($reactedAt), 'd.m.Y H:i')));
		$fixComment = 	($fixedAt == '' ? (1 == $onWait ? ('' == $reactedAt ? '' : ("Приостановлено ".date_format(date_create($onWaitAt), 'd.m.Y H:i'))) : 
        	            ("Восстановить до ".date_format(date_create($fixBefore), 'd.m.Y H:i'))) : 
            	        ("Восстановлено ".date_format(date_create($fixedAt), 'd.m.Y H:i')));
    	$repairComment =($repairedAt == '' ? (1 == $onWait ? ('' == $fixedAt ? '' : ("Приостановлено ".date_format(date_create($onWaitAt), 'd.m.Y H:i'))) : 
        	            ("Завершить до ".date_format(date_create($repairBefore), 'd.m.Y H:i'))) : 
            	        ("Завершено ".date_format(date_create($repairedAt), 'd.m.Y H:i')));
		$requests[$statusGroup[$state]][$id] = array(
			'service_only_auto' => $srvAutoOnly,
			'status' => array('name' => $statusNames[$state], 'icon' => $statusIcons[$state], 'onWait' => $onWait, 
						  	'sync1C' => (null == $requestGuid ? 0 : 1), 'toPartner' => ('' == $partnerName ? 0 : 1)),
			'slaLevel' => $slaLevels[$slaLevel],
			'service' => array('name' => $srvName, 'shortName' => $srvSName),
			'problem' => $problem,
			'receiveTime' => date_format(date_create($createdAt), 'd.m.Y H:i'),
			'repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
			'contract' => $contractNumber,
			'division' => $div,
			'contragent' => $contragent,
			'contact' => array('name' => nameFull($contLN, $contGN, $contMN), 'email' => $contEmail, 'phone' => $contPhone, 
								'shortname' => nameWithInitials($contLN, $contGN, $contMN)), 
			'engineer' => array('name' => nameFull($engLN, $engGN, $engMN), 'email' => $engEmail, 'phone' => $engPhone, 
								'shortname' => nameWithInitials($engLN, $engGN, $engMN)),
			'time' => array('toReact' => array('percent' => $reactPercent, 'text' => $reactComment),
							'toFix' => array('percent' => $fixPercent, 'text' => $fixComment),
							'toRepair' => array('percent' => $repairPercent, 'text' => $repairComment))
			);
		if (1 == $onWait) {
			$requests[$statusGroup[$state]][$id]['onWait'] = array('name' => $statusNames['onWait'], 'icon' => $statusIcons['onWait']);
		}
		if (null == $requestGuid) {
			$requests[$statusGroup[$state]][$id]['sync1C'] = array('name' => $statusNames['notSync'], 'icon' => $statusIcons['notSync']);
		}
		if ('' != $partnerName) {
			$requests[$statusGroup[$state]][$id]['toPartner'] = array('name' => $partnerName, 'icon' => $statusIcons['toPartner']);
		}
	}
}

echo json_encode(array( 'resuilt' => 'ok', 'total' => $totalCount, 'requests' => $requests, 'expireTime' => 120));

?>