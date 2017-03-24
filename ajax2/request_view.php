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
								"`em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `co`.`lastName`, ".
								"`co`.`firstName`, `co`.`middleName`, `co`.`email`, `co`.`phone`, `rq`.`fixBefore`, ".
								"`rq`.`repairBefore`, CAST(`rq`.`problem` AS CHAR(8192)), `rq`.`slaLevel`, `co`.`address`, ".
                        		"`rq`.`solutionProblem`, `rq`.`solution`,  `rq`.`solutionRecomendation`, `div`.`guid`, ".
                        		"`c`.`number`, `rq`.`repairedAt`, `ca`.`guid`, `c`.`guid`, `c`.`number`, `div`.`address`, ".
                        		"`rq`.`guid`, `p`.`name` ".
          					"FROM `requests` AS `rq` ".
	            			"LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
    	        			"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
        	    			"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `rq`.`contractDivision_guid` = `ucd`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
            				"LEFT JOIN `services` AS `srv` ON `srv`.`guid` = `rq`.`service_guid` ".
	            			"LEFT JOIN `users` AS `e` ON `e`.`guid` = `rq`.`engineer_guid` ".
    	        			"LEFT JOIN `users` AS `co` ON `co`.`guid` = `rq`.`contactPerson_guid` ".
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
list($id, $state, $stateTime, $srvSName, $srvName, $createdAt, $repairBefore, $div, $contragent, $engLN, $engGN, $engMN, $engEmail, 
	 $engPhone, $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $contLN, $contGN, $contMN, $contEmail, $contPhone, $fixBefore, 
	 $repairBefore, $problem, $slaLevel, $contAddress, $solProblem, $sol, $solRecomend, $divGuid, $contractNumber, $repairedAt, 
	 $contragentGuid, $contractGuid, $contractNumber, $divAddress, $requestGuid, $partnerName) = $row;
$requestGuid = formatGuid($requestGuid);
$engName = nameWithInitials($engLN, $engGN, $engMN);
$createTime = date_timestamp_get(date_create($createdAt));
$passedTime = date_timestamp_get(date_create('now'))-$createTime;
$timeToFix = date_timestamp_get(date_create($fixBefore))-$createTime;
$timeToRepair = date_timestamp_get(date_create($repairBefore))-$createTime;
$result = array('_servNum' => $servNum,
				'_SN' => $serial,
				'_eqType' => (('' == $eqType || '' == $eqSubType) ? "{$eqType}{$eqSubType}" : "{$eqType} / {$eqSubType}"),
				'_manufacturer' => $eqMfg,
				'_model' => $eqName,
				'_problem' => $problem,
				'contragent' => "<option value='".formatGuid($contragentGuid)."'>".htmlspecialchars($contragent),
				'contract' => "<option value='".formatGuid($contractGuid)."'>".htmlspecialchars($contractNumber),
				'_service' => $srvName,
				'level' => "<option value='{$slaLevel}' selected>{$slaLevels[$slaLevel]}",
				'_createdAt' => date_format(date_create($createdAt), 'd.m.Y H:i'),
				'_repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
				'division' => "<option value='".formatGuid($divGuid)."'>".htmlspecialchars($div),
				'_contact' => htmlspecialchars(nameFull($contLN, $contGN, $contMN)),
				'_email' => $contEmail,
				'_phone' => $contPhone,
				'_address' => ('' == $divAddress ? $contAddress : $divAddress),
				'_cardSolProblem' => $solProblem,
				'_cardSolSolution' => $sol,
				'_cardSolRecomendation' => $solRecomend,
				'!lookServNum' => (('received' == $state  || ('client' != $rights && 'accepted' == $state)) ? 1 : 0),
				'requestGuid' => $requestGuid,
				'_partner' => $partnerName,
				'!lookService' => 1 // (('received' == $state && ('admin' == $rights || 'engineer' == $rights)) ? 1 : 0)
			);

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

// Проверяем права на назначение партнёра
$result['!lookPartner'] = 0;
if (in_array($rights, array('engineer', 'admin')) && 'received' == $state)
	$result['!lookPartner'] = 1;
try {
	$req = $db->prepare("SELECT COUNT(*) AS `count` ".
    						"FROM `requests` AS `rq` ".
    						"JOIN `contractDivisions` AS `cd` ON `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ".
    						"JOIN `partnerDivisions` AS `pd` ON `pd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
    						"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
 	                        "WHERE `rq`.`id` = :reqNum ".
       	                		"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)");
	$req->execute(array('reqNum' => $paramValues['n']));
	$row = $req->fetch(PDO::FETCH_ASSOC);
	if (!isset($row['count']) || 0 == $row['count'])
		$result['!lookPartner'] = 0;
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}

// Проверяем права на смену контактного лица  
$result['!lookContact'] = 0;
if (in_array($rights, array('admin')) && 'received' == $state)
	$result['!lookContact'] = 1;
try {
	$req = $db->prepare("SELECT COUNT(*) AS `count` ". 
							"FROM ( ".
								"SELECT `rq`.`id` ". 
									"FROM `requests` AS `rq` ". 
									"JOIN `contractDivisions` AS `cd` ON `rq`.`id` = :reqNum ". 
										"AND `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ". 
            						"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
										"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`) ".
									"JOIN `userContractDivisions` AS `ucd` ON `ucd`.`user_guid` = `rq`.`contractDivision_guid` ". 
								"UNION SELECT `rq`.`id` ". 
									"FROM `requests` AS `rq` ". 
									"JOIN `contractDivisions` AS `cd` ON `rq`.`id` = :reqNum ". 
										"AND `cd`.`guid` = `rq`.`contractDivision_guid` AND `cd`.`isDisabled` = 0 ". 
            						"JOIN `contracts` AS `c` ON `c`.`guid` = `cd`.`contract_guid` ".
										"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`) ".
									"JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `cd`.`contract_guid` ".
							") AS `t`");
	$req->execute(array('reqNum' => $paramValues['n']));
	$row = $req->fetch(PDO::FETCH_ASSOC);
	if (!isset($row['count']) || 0 == $row['count'])
		$result['!lookContact'] = 0;
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}

	
echo json_encode($result);
exit;	 
?>