<?php

include '../config/files.php';
include 'common.php';
include 'init.php';

header('Content-Type: application/json; charset=UTF-8');

// Получаем заявку с проверкой прав на просмотр
try {
	$req = $db->prepare("SELECT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangedAt`, `srv`.`shortName`, `srv`.`name`, ".
								"`rq`.`createdAt`, `rq`.`repairBefore`, `div`.`name`, `ca`.`name`, `e`.`lastName`, ".
								"`e`.`firstName`, `e`.`middleName`, `e`.`email`, `e`.`phone`, `et`.`name`, `est`.`name`, ".
								"`em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `rq`.`fixBefore`, ".
								"`rq`.`repairBefore`, CAST(`rq`.`problem` AS CHAR(8192)), `rq`.`slaLevel`, ".
                        		"`rq`.`solutionProblem`, `rq`.`solution`,  `rq`.`solutionRecomendation`, `div`.`guid`, ".
                        		"`c`.`number`, `rq`.`repairedAt`, `ca`.`guid`, `c`.`guid`, `c`.`number`, `div`.`address`, ".
                        		"`rq`.`guid`, `p`.`name`, `rq`.`service_guid`, `rq`.`contractDivision_guid`, ".
                        		"`rq`.`contactPerson_guid`, `rq`.`equipment_guid` ".
          					"FROM `requests` AS `rq` ".
	            			"LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
    	        			"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
        	    			"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `rq`.`contractDivision_guid` = `ucd`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
            				"LEFT JOIN `services` AS `srv` ON `srv`.`guid` = `rq`.`service_guid` ".
	            			"LEFT JOIN `users` AS `e` ON `e`.`guid` = `rq`.`engineer_guid` ".
        	    			"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `rq`.`equipment_guid` ".
            				"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ".
            				"LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`guid` = `em`.`equipmentSubType_guid` ".
            				"LEFT JOIN `equipmentTypes` AS `et` ON `et`.`guid` = `est`.`equipmentType_guid` ".
            				"LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`guid` = `em`.`equipmentManufacturer_guid` ".
            				"LEFT JOIN `partners` AS `p` ON `p`.`guid` = `rq`.`partner_guid` ".
            				"WHERE (`rq`.`id` = :reqNum) ".
//	            				"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
    	        				"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
        	    					"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
	$req->execute(array('reqNum' => $paramValues['n'], 'byClient' => $byClient, 'userGuid' => $userGuid, 'byPartner' => $byPartner, 
						'partnerGuid' => $partnerGuid));
	if (!($row = $req->fetch(PDO::FETCH_NUM))) {
		echo json_encode(array('error' => 'Нет такой заявки или недостаточно прав для просмотра.'));
		exit;
	}
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
list($id, $state, $stateTime, $srvSName, $serviceName, $createdAt, $repairBefore, $div, $contragent, $engLN, $engGN, $engMN, $engEmail, 
	 $engPhone, $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $fixBefore, $repairBefore, $problem, $slaLevel, 
	 $solProblem, $sol, $solRecomend, $divGuid, $contractNumber, $repairedAt, $contragentGuid, $contractGuid, $contractNumber, 
	 $divAddress, $requestGuid, $partnerName, $serviceGuid, $divisionGuid, $contactGuid, $equipmentGuid) = $row;
$requestGuid = formatGuid($requestGuid);
$engName = nameWithInitials($engLN, $engGN, $engMN);
$createTime = date_timestamp_get(date_create($createdAt));
$passedTime = date_timestamp_get(date_create('now'))-$createTime;
$timeToFix = date_timestamp_get(date_create($fixBefore))-$createTime;
$timeToRepair = date_timestamp_get(date_create($repairBefore))-$createTime;
$serviceGuid = formatGuid($serviceGuid);
$divisionGuid = formatGuid($divisionGuid);
$contactGuid = formatGuid($contactGuid);
$equipmentGuid = formatGuid($equipmentGuid);
$result = array('_servNum' => $servNum,
				'equipment_guid' => $equipmentGuid,
				'_SN' => $serial,
				'_eqType' => (('' == $eqType || '' == $eqSubType) ? $eqType.$eqSubType : $eqType.' / '.$eqSubType),
				'_manufacturer' => $eqMfg,
				'_model' => $eqName,
				'_problem' => $problem,
				'_email' => $contactEmail,
				'_phone' => $contactPhone,
				'_address' => (('' == $divAddress) ? $contactAddress : $divAddress),
				'contragent' => "<option value='".formatGuid($contragentGuid)."'>".htmlspecialchars($contragent),
				'contract' => "<option value='".formatGuid($contractGuid)."'>".htmlspecialchars($contractNumber),
				'_createdAt' => date_format(date_create($createdAt), 'd.m.Y H:i'),
				'_repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
				'division' => "<option value='".formatGuid($divGuid)."'>".htmlspecialchars($div),
				'_cardSolProblem' => $solProblem,
				'_cardSolSolution' => $sol,
				'_cardSolRecomendation' => $solRecomend,
				'!lookServNum' => (('received' == $state  || ('client' != $rights && 'accepted' == $state)) ? 1 : 0),
				'!lookPartner' => (('received' == $state  && ('admin' == $rights || 'engineer' == $rights)) ? 1 : 0),
				'requestGuid' => $requestGuid,
				'_partner' => $partnerName,
			);

// Получаем доступные по подразделению услуги
$have = 0;
$services = '';
if (in_array($rights, array('admin', 'engineer')) && 'received' == $state) {
	try {
		$req = $db->prepare("SELECT DISTINCT `srv`.`guid`, `srv`.`name`, `srv`.`autoOnly` ".
								"FROM `contractDivisions` AS `cd` ".
								"JOIN `divServicesSLA` AS `dss` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) AND `cd`.`isDisabled` = 0 ".
									"AND `dss`.`contract_guid` = `cd`.`contract_guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
								"JOIN `services` AS `srv` ON `srv`.`utility` = 0 AND `srv`.`guid` = `dss`.`service_guid` ".
								"ORDER BY `srv`.`name`");
		$req->execute(array('divisionGuid' => $divisionGuid)); 
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	while (($row = $req->fetch(PDO::FETCH_NUM))) {
		list($servGuid, $servName, $autoOnly) = $row;
		$servGuid = formatGuid($servGuid);
		if ($servGuid == $serviceGuid)
			$have = 1;
		$services .= "<option value='{$servGuid}' data-autoonly={$autoOnly}".($servGuid == $serviceGuid ? ' selected' : '').">".htmlspecialchars($servName);
	}
}
if (0 == $have)
	$services = "<option value='{$serviceGuid}' selected>".htmlspecialchars($serviceName).$services;
$result['service'] = $services;

// Получаем возможные уровни SLA
$have = 0;
$levels = '';
if (in_array($rights, array('admin', 'engineer')) && 'received' == $state) {
	try {
		$req = $db->prepare("SELECT DISTINCT `dss`.`slaLevel` ".
								"FROM `contractDivisions` AS `cd` ".
								"JOIN `contracts` AS `c` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) AND `cd`.`isDisabled` = 0 ".
									"AND `c`.`guid` = `cd`.`contract_guid` ".
								"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')) ".
									"AND `dss`.`contract_guid` = `c`.`guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
								"ORDER BY `dss`.`slaLevel` ");
		$req->execute(array('divisionGuid' => $divisionGuid, 'serviceGuid' => $serviceGuid));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	while (($row = $req->fetch())) {
		list($slaLvl) = $row;
		$levels .= "<option value='{$slaLvl}'".($slaLvl == $slaLevel ? ' selected' : '').">{$slaLevels[$slaLvl]}";
		if ($slaLvl == $slaLevel)
			$have = 1;
	}
}
if (0 == $have)
	$levels = "<option value='{$slaLevel}' selected>{$slaLevels[$slaLevel]}".$levels;
$result['level'] = $levels;


// Получаем список контактных лиц
$have = 0;
$contacts = '';
$curContact = null;
if (in_array($rights, array('admin')) && 'received' == $state) {
	try {
		$req = $db->prepare("SELECT `u`.`guid`, `u`.`firstName`, `u`.`lastName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, `u`.`address` ".
								"FROM `users` AS `u` ".
								"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
									"AND `u`.`rights` = 'client' AND `ucd`.`user_guid` = `u`.`guid` ".
								"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = `ucd`.`contractDivision_guid` ".
									"AND `cd`.`isDisabled` = 0 ".
							"UNION SELECT `u`.`guid`, `u`.`firstName`, `u`.`lastName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, `u`.`address` ".
								"FROM `users` AS `u` ".
								"JOIN `userContracts` AS `uc` ON `u`.`rights` = 'client' AND `uc`.`user_guid` = `u`.`guid` ".
								"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
									"AND `cd`.`contract_guid` = `uc`.`contract_guid`");
		$req->execute(array('divisionGuid' => $divisionGuid)); 
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	while (($row = $req->fetch(PDO::FETCH_NUM))) {
		list($contGuid, $contGN, $contLN, $contMN, $contEmail, $contPhone, $contAddress) = $row;
		$contGuid = formatGuid($contGuid);
		$contacts .= "<option value='{$contGuid}' data-email='".htmlspecialchars($contEmail).
					 "' data-phone='".htmlspecialchars($contPhone)."' data-address='".
					 htmlspecialchars(('' == $divAddress) ? $contAddress : $divAddress)."'".
					 ($contGuid == $contactGuid ? ' selected' : '').">".htmlspecialchars(nameFull($contLN, $contGN, $contMN));
		if ($contGuid == $contactGuid)
			$have = 1;
	}
}
if (0 == $have)
	$contacts = "<option value='{$contactGuid}' data-email='".htmlspecialchars($contactEmail).
					 "' data-phone='".htmlspecialchars($contactPhone)."' data-address='".
					 htmlspecialchars(('' == $divAddress) ? $contactAddress : $divAddress)."'".
					 " selected>".htmlspecialchars(nameFull($contactLN, $contactGN, $contactMN)).$contacts;
$result['contact'] = $contacts;

// Получаем все события и документы по заявке
try {
	$req = $db->prepare("SELECT `log`.`timestamp`, `log`.`event`, CAST(`log`.`text` AS CHAR(1024)), `log`.`newState`, ".
								"`u`.`lastName`, `u`.`firstName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, ".
								"`d`.`guid`, `d`.`name`, `d`.`uniqueName` ".
                            "FROM `requestEvents` AS `log` ".
                            "LEFT JOIN `users` AS `u` ON `u`.`guid` = `log`.`user_guid` ".
                            "LEFT JOIN `documents` AS `d` ON `d`.`requestEvent_id` = `log`.`id` ".
                            "WHERE `log`.`request_guid` = UNHEX(REPLACE(:requestGuid, '-', '')) ".
                            "ORDER BY `log`.`timestamp`");
	$req->execute(array('requestGuid' => $requestGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
$log = '';
$files = '';
while ($row = ($req->fetch(PDO::FETCH_NUM))) {
	list($time, $event, $text, $newState, $ln, $gn, $mn, $email, $phone, $docGuid, $docName, $docUName) = $row;
	$date = date_format(date_create($time), 'd.m.Y H:i');
	$name = nameWithInitials($ln, $gn, $mn);
	$log .= "<p class='".($event == 'comment' ? 'logDateComm' : 'logDate')."'>{$date}: <abbr title='".
				htmlspecialchars(nameFull($ln, $gn, $mn))."\nE-mail: {$email}\nТелефон: ${phone}'>".htmlspecialchars($name)."</abbr>";
	switch ($event) {
		case 'open':
			$log .= "<p class='logMain'>Заявка создана";
			break;
		case 'changeState':
			$log .= "<p class='logMain'>Статус заявки изменён на '{$statusNames[$newState]}'";
			if ($newState == 'canceled')
				$log .= "\n<span class='logComment'>Причина отмены: ".htmlspecialchars($text);
			break;
		case 'changeDate':
			$log .= "<p class='logMain'>Срок завершения перенесён на ".date_format(date_create($text), 'd.m.Y H:i');
			break;
		case 'changePartner':
			if (null == $text)
				$log .= "<p class='logMain'>Отменено назначение заявки партнёру";
			else
				$log .= "<p class='logMain'>Заявка назначена партнёру<p class='logComment'>".htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
			break;
		case 'changeContact':
			$log .= "<p class='logMain'>Новое контактное лицо<p class='logComment'>".htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
			break;
		case 'changeService':
			$log .= "<p class='logMain'>Услуга изменена<p class='logComment'>".htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
			break;
		case 'comment':
			$log .= "<p class='logComment'>".htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
			break;
		case 'onWait':
			$log .= "<p class='logMain'>Заявка поставлена в ожидание\n<span class='logComment'>Причина: ".htmlspecialchars($text);
			break;
		case 'offWait':
			$log .= "<p class='logMain'>Заявка снята с ожидания\n<span class='logComment'>Комментарий: ".htmlspecialchars($text);
			break;
		case 'unClose':
			$log .= "<p class='logMain'>Отказано в закрытии заявки!\n<span class='logComment'>Причина: ".htmlspecialchars($text);
			break;
		case 'unCancel':
			$log .= "<p class='logMain'>Отмена заявки отменена!\n<span class='logComment'>Причина: ".htmlspecialchars($text);
			break;
		case 'eqChange':
			$log .= "<p class='logMain'>Изменено оборудование по заявке\n<span class='logComment'>".htmlspecialchars($text);
			break;
		case 'addDocument':
			if (file_exists("{$fileStorage}/{$id}/{$docUName}")) {
				$log .= "<p class='logMain'>Добавлен документ '<a href='/ajax/file/get/{$id}/".formatGuid($docGuid)."'>".
						htmlspecialchars($docName)."</a>'";
				$files .= "<tr><td><td>{$date}<td>".htmlspecialchars($docName)."<td>".filesize("{$fileStorage}/{$id}/{$docUName}").
							"<td><a href='/ajax/file/get/{$id}/".formatGuid($docGuid)."'>Скачать</a>"  ;
			} else
				$log .= "<p class='logMain'>Добавлен документ '".htmlspecialchars($docName)."' (потерян)";
       		break;
	}
}
if ($files != '')
	$files = "<tr><th><th>Дата<th>Имя файла<th>Размер<th>{$files}";
$result['comments'] = $log;
$result['cardDocTbl'] = $files;

// Проверяем права на изменение оборудования
try {
	$req = $db->prepare("SELECT COUNT(*) AS `count` ".
    						"FROM `requests` AS `rq` ".
    						"LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ".
    						"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
   	                        "WHERE `rq`.`id` = :reqNum ".
   	                        	"AND (:byClient = 0 OR (`rq`.`currentState` = 'received' ".
   	                        		"AND `rq`.`contactPerson_guid` = UNHEX(REPLACE(:userGuid, '-', '')))) ".
   	                        	"AND (:byEngineer = 0 OR (`rq`.`currentState` IN ('accepted','fixed') ".
   	                        		"AND `rq`.`engineer_guid` = UNHEX(REPLACE(:userGuid, '-', '')))) ".
       	                		"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`))");
	$req->execute(array('reqNum' => $paramValues['n'], 'userGuid' => $userGuid, 'byClient' => $byClient, 'byEngineer' => $byEngineer, 
						'byActive' => $byActive));
	$row = $req->fetch(PDO::FETCH_ASSOC);
	$result['!lookServNum'] = 0;
	if (isset($row['count']) && $row['count'] > 0)
		$result['!lookServNum'] = 1;
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
	
echo json_encode($result);
exit;	 
?>