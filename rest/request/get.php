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
	
	$request_id = (isset($params['id']) ? $params['id'] : null);

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
/*	if ('admin' != $userData['rights']) {
		$where[] = "(`c`.`contractStart` <= NOW() AND `c`.`contractEnd` >= NOW()) ";
	} */
	
	$reqVars['request_id'] = $request_id;
// Получаем заявку с проверкой прав на просмотр
	try {
		$req = $db->prepare("SELECT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangedAt`, `srv`.`shortName`, `srv`.`name`, ".
									"`rq`.`createdAt`, `rq`.`repairBefore`, `div`.`name`, `ca`.`name`, `e`.`lastName`, ".
									"`e`.`firstName`, `e`.`middleName`, `e`.`email`, `e`.`phone`, `et`.`name`, `est`.`name`, ".
									"`em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `co`.`lastName`, ".
									"`co`.`firstName`, `co`.`middleName`, `co`.`email`, `co`.`phone`, `rq`.`fixBefore`, ".
									"`rq`.`repairBefore`, CAST(`rq`.`problem` AS CHAR(8192)), `rq`.`slaLevel`, `co`.`address`, ".
                        			"`rq`.`solutionProblem`, `rq`.`solution`,  `rq`.`solutionRecomendation`, HEX(`div`.`guid`), ".
                        			"`c`.`number`, `rq`.`repairedAt`, HEX(`ca`.`guid`), HEX(`c`.`guid`), `c`.`number`, `div`.`address`, ".
	                        		"HEX(`rq`.`guid`), `p`.`name`, HEX(`rq`.`service_guid`), HEX(`rq`.`contractDivision_guid`), ".
    	                    		"HEX(`rq`.`contactPerson_guid`), HEX(`rq`.`equipment_guid`), HEX(`rq`.`partner_guid`), ".
    	                    		"`rq`.`onWait`, `srv`.`autoOnly`, HEX(`rq`.`engineer_guid`) ".
        	  					"FROM `requests` AS `rq` ".
	        	    			"JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
    	        				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
    	        				$join.
        	    				"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
	            				"LEFT JOIN `services` AS `srv` ON `srv`.`guid` = `rq`.`service_guid` ".
		            			"LEFT JOIN `users` AS `e` ON `e`.`guid` = `rq`.`engineer_guid` ".
    		        			"LEFT JOIN `users` AS `co` ON `co`.`guid` = `rq`.`contactPerson_guid` ".
        		    			"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `rq`.`equipment_guid` ".
            					"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ".
            					"LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`guid` = `em`.`equipmentSubType_guid` ".
            					"LEFT JOIN `equipmentTypes` AS `et` ON `et`.`guid` = `est`.`equipmentType_guid` ".
            					"LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`guid` = `em`.`equipmentManufacturer_guid` ".
	            				"LEFT JOIN `partners` AS `p` ON `p`.`guid` = `rq`.`partner_guid` ".
    	        				"WHERE `rq`.`id` = :request_id ".
    	        				(count($where) > 0 ? "AND ".implode(' AND', $where) : ""));
		$req->execute($reqVars);
		if (!($row = $req->fetch(PDO::FETCH_NUM))) {
			echo json_encode(array('result' => 'error',
									'error' => 'Нет такой заявки или недостаточно прав для просмотра.'));
			exit;
		}
	} catch (PDOException $e) {
		echo json_encode(array('result' => 'error',
								'error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	list($id, $state, $stateTime, $srvSName, $serviceName, $createdAt, $repairBefore, $div, $contragent, $engLN, $engGN, 
		 $engMN, $engEmail, $engPhone, $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $contactLN, $contactGN, 
		 $contactMN, $contactEmail, $contactPhone, $fixBefore, $repairBefore, $problem, $slaLevel, $contactAddress, 
		 $solProblem, $sol, $solRecomend, $divGuid, $contractNumber, $repairedAt, $contragentGuid, $contractGuid, 
		 $contractNumber, $divAddress, $requestGuid, $partnerName, $serviceGuid, $divisionGuid, $contactGuid, 
		 $equipmentGuid, $partnerGuid, $onWait, $engineerGuid) = $row;
		 
	$result = array('id' => $id,
					'guid' => $requestGuid,
					'state' => $state,
					'onWait' => $onWait,
					'equipment' => array('guid' => $equipmentGuid, 'service_number' => $servNum, 'serial_number' => $serial,
										'type' => $eqType, 'subtype' => $eqSubType, 'manufacturer' => $eqMfg,
										'model' => $eqName),
					'service' => array('guid' => $serviceGuid, 'name' => $serviceName, 'autocreate_only' => $autoOnly),
					'sla' => array('level' => $slaLevel, 'name' => $slaLevels[$slaLevel]),
					'problem' => $problem,
					'contact' => array('guid' => $contactGuid, 'name' => nameFull($contactLN, $contactGN, $contactMN), 
										'email' => $contactEmail, 'phone' => $contactPhone, 
										'address' => (('' == $divAddress) ? $contactAddress : $divAddress)),
					'division' => array('guid' => $divGuid, 'name' => $div),
					'contract' => array('guid' => $contractGuid, 'name' => $contractNumber),
					'contragent' => array('guid' => $contragentGuid, 'name' => $contragent),
					'createdAt' => date_format(date_create($createdAt), 'd.m.Y H:i'),
					'repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
					'solution' => array('problem' => $solProblem, 'solution' => $sol, 'recomendation' => $solRecomend),
					'can_change_equipment' => (('received' == $state  || ('client' != $userData['rights'] && 'accepted' == $state)) ? 1 : 0),
					'can_change_partner' => (('received' == $state  && ('admin' == $userData['result'] || 'engineer' == $userData['result'])) ? 1 : 0),
					'engineer' => array('guid' => $engineerGuid, 'name' => nameFull($engLN, $engGN, $engMN), 'email' => $engEmail,
										'phone' => $engPhone),
					'partner' => array('guid' => $partnerGuid, 'name' => $partnerName)
			);

// Получаем доступные по подразделению услуги
	if (in_array($userData['rights'], array('admin', 'engineer')) && 'received' == $state) {
		$result['services'] = array();
		try {
			$req = $db->prepare("SELECT DISTINCT HEX(`srv`.`guid`), `srv`.`name`, `srv`.`autoOnly` ".
									"FROM `contractDivisions` AS `cd` ".
									"JOIN `divServicesSLA` AS `dss` ON `cd`.`guid` = UNHEX(:divisionGuid) AND `cd`.`isDisabled` = 0 ".
										"AND `dss`.`contract_guid` = `cd`.`contract_guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
									"JOIN `services` AS `srv` ON `srv`.`utility` = 0 AND `srv`.`guid` = `dss`.`service_guid` ".
									"ORDER BY `srv`.`name`");
			$req->execute(array('divisionGuid' => $divisionGuid)); 
		} catch (PDOException $e) {
			echo json_encode(array('result' => 'error',
									'error' => 'Внутренняя ошибка сервера', 
									'orig' => "MySQL error".$e->getMessage()));
			exit;
		}
		while (($row = $req->fetch(PDO::FETCH_NUM))) {
			list($servGuid, $servName, $autoOnly) = $row;
			$result['services'][$servGuid] = array(
					'name' => $servName,
					'autocreate_only' => $autoOnly
				);
		}

// Получаем возможные уровни SLA
		$result['slas'] = array();
		try {
			$req = $db->prepare("SELECT DISTINCT `dss`.`slaLevel` ".
									"FROM `contractDivisions` AS `cd` ".
									"JOIN `contracts` AS `c` ON `cd`.`guid` = UNHEX(:divisionGuid) AND `cd`.`isDisabled` = 0 ".
										"AND `c`.`guid` = `cd`.`contract_guid` ".
									"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = UNHEX(:serviceGuid) ".
										"AND `dss`.`contract_guid` = `c`.`guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
									"ORDER BY `dss`.`slaLevel` ");
			$req->execute(array('divisionGuid' => $divisionGuid, 'serviceGuid' => $serviceGuid));
		} catch (PDOException $e) {
			echo json_encode(array('result' => 'error',
									'error' => 'Внутренняя ошибка сервера', 
									'orig' => "MySQL error".$e->getMessage()));
			exit;
		}
		while (($row = $req->fetch())) {
			list($slaLvl) = $row;
			$result['slas'][$slaLvl] = $slaLevels[$slaLvl];
		}
	}

// Получаем список контактных лиц
	$result['contacts'] = array();
	if ('admin' == $userData['rights'] && 'received' == $state) {
		try {
			$req = $db->prepare("SELECT HEX(`u`.`guid`), `u`.`firstName`, `u`.`lastName`, `u`.`middleName`, `u`.`email`, ".
										"`u`.`phone`, `u`.`address`, `cd`.`address` ".
									"FROM `users` AS `u` ".
									"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = UNHEX(:divisionGuid) ".
										"AND `u`.`rights` = 'client' AND `ucd`.`user_guid` = `u`.`guid` ".
									"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = `ucd`.`contractDivision_guid` ".
										"AND `cd`.`isDisabled` = 0 ".
								"UNION SELECT HEX(`u`.`guid`), `u`.`firstName`, `u`.`lastName`, `u`.`middleName`, `u`.`email`, ".
										"`u`.`phone`, `u`.`address`, `cd`.`address` ".
									"FROM `users` AS `u` ".
									"JOIN `userContracts` AS `uc` ON `u`.`rights` = 'client' AND `uc`.`user_guid` = `u`.`guid` ".
									"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = UNHEX(:divisionGuid) ".
										"AND `cd`.`contract_guid` = `uc`.`contract_guid`");
			$req->execute(array('divisionGuid' => $divisionGuid)); 
		} catch (PDOException $e) {
			echo json_encode(array('result' => 'error',
									'error' => 'Внутренняя ошибка сервера', 
									'orig' => "MySQL error".$e->getMessage()));
			exit;
		}
		while (($row = $req->fetch(PDO::FETCH_NUM))) {
			list($contGuid, $contGN, $contLN, $contMN, $contEmail, $contPhone, $contAddress, $divAddress) = $row;
			$result['contacts'][$contGuid] = array('name' => nameFull($contLN, $contGN, $contMN), 'email' => $contEmail,
													'phone' => $contPhone, 'address' => (('' == $divAddress) ? $contAddress : $divAddress)
				);
		}
	}

// Получаем все события и документы по заявке
	try {
		$req = $db->prepare("SELECT `log`.`timestamp`, `log`.`event`, CAST(`log`.`text` AS CHAR(1024)), `log`.`newState`, ".
									"`u`.`lastName`, `u`.`firstName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, ".
									"`d`.`guid`, `d`.`name`, `d`.`uniqueName`, HEX(`log`.`user_guid`) ".
                	            "FROM `requestEvents` AS `log` ".
                    	        "LEFT JOIN `users` AS `u` ON `u`.`guid` = `log`.`user_guid` ".
                        	    "LEFT JOIN `documents` AS `d` ON `d`.`requestEvent_id` = `log`.`id` ".
                            	"WHERE `log`.`request_guid` = UNHEX(:requestGuid) ".
                            	"ORDER BY `log`.`timestamp`");
		$req->execute(array('requestGuid' => $requestGuid));
	} catch (PDOException $e) {
		echo json_encode(array('result' => 'error',
								'error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL connection error".$e->getMessage()));
		exit;
	}

	$result['files'] = array();
	$result['log'] = array();
	while ($row = ($req->fetch(PDO::FETCH_NUM))) {
		list($time, $eventCode, $text, $newState, $ln, $gn, $mn, $email, $phone, $docGuid, $docName, $docUName, $authorGuid) = $row;
		$event = array('code' => $eventCode, 'time' => date_format(date_create($time), 'd.m.Y H:i'));
		switch ($eventCode) {
			case 'open':
				$event['text'] =  "Заявка создана";
				break;
			case 'changeState':
				$event['text'] = "Статус заявки изменён";
				$event['comment'] = $statusNames[$newState];
				if ('canceled' == $newState) {
					$event['cause'] = $text;
				}
				break;
			case 'changeDate':
				$event['text'] = "Срок завершения перенесён";
				$event['comment'] = date_format(date_create($text), 'd.m.Y H:i');
				break;
			case 'changePartner':
				if (null == $text) {
					$event['text'] = "Отменено назначение заявки партнёру";
				} else {
					$event['text'] = "Заявка назначена партнёру";
					$event['comment'] = $text;
				}
				break;
			case 'changeContact':
				$event['text'] = "Новое контактное лицо";
				$event['comment'] = $text;
				break;
			case 'changeService':
				$event['text'] = "Услуга изменена";
				$event['comment'] = $text;
				break;
			case 'comment':
				$event['text'] = "Комментарий";
				$event['comment'] = $text;
				break;
			case 'onWait':
				$event['text'] = "Заявка поставлена в ожидание";
				$event['cause'] = $text;
				break;
			case 'offWait':
				$event['text'] = "Заявка снята с ожидания";
				$event['comment'] = $text;
				break;
			case 'unClose':
				$event['text'] = "Отказано в закрытии заявки!";
				$event['cause'] = $text;
				break;
			case 'unCancel':
				$event['text'] = "Отмена заявки отменена!";
				$event['cause'] = $text;
				break;
			case 'eqChange':
				$event['text'] = "Изменено оборудование по заявке";
				$event['comment'] = $text;
				break;
			case 'addDocument':
				$event['text'] = "Добавлен документ";
				if (file_exists("{$fileStorage}/{$id}/{$docUName}")) {
					$event['doc'] = array('name' => $docName, 'href' => "/api/v2/file/request/{$id}/doc/$docGuid", 
											'size' => filesize("{$fileStorage}/{$id}/{$docUName}"));
					$result['files'][] = $event['doc'];
				} else {
					$event['doc'] = array('name' => $docName);
				}
    	   		break;
		}
		$result['log'][] = array(
				'author' => array('guid' => $authorGuid, 'name' => nameFull($ln, $gn, $mn), 
									'shortname' => nameWithInitials($ln, $gn, $mn), 'email' => $email, 'phone' => $phone),
				'event' => $event,
			);
	}

	echo json_encode(array('result' => 'ok', 'request' => $result, 'expire' => 120));

?>