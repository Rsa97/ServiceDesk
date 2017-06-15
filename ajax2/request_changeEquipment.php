<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$allowedTo = array('client', 'operator', 'engineer', 'admin', 'partner');
$allowedFrom = "'fixed','accepted'";
if ('client' == $rights)
	$allowedFrom = "'received'";
if ('admin' == $rights)
	$allowedFrom = "'received','fixed','accepted'";
	

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$equipmentGuid = null;
if (isset($paramValues['equipment']))
	$equipmentGuid = $paramValues['equipment'];

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT DISTINCT `rq`.`guid`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `mdl`.`name`, `mfg`.`name` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`onWait` = 0 AND `rq`.`id` = :requestId ".
            					"AND `rq`.`currentState` IN ({$allowedFrom}) AND `div`.`guid` = `rq`.`contractDivision_guid` ".
            				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            					"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` OR `rq`.`currentState` NOT IN ('closed', 'canceled')) ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
            				"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `rq`.`equipment_guid` ".
            				"LEFT JOIN `equipmentModels` AS `mdl` ON `mdl`.`guid` = `eq`.`equipmentModel_guid` ".
							"LEFT JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`guid` = `mdl`.`equipmentManufacturer_guid` ".
          					"WHERE (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
          							"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
    $req->execute(array('byClient' => $byClient, 'userGuid' => $userGuid, 'byPartner' => $byPartner, 'partnerGuid' => $partnerGuid,
    					'requestId' => $paramValues['id']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

if (!($row = $req->fetch(PDO::FETCH_NUM))) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}	

list($guid, $oldServNum, $oldSerial, $oldModel, $oldMfg) = $row;
if (null === $guid) {
	echo json_encode(array('error' => "Статус заявки {$paramValues['id']} не может быть изменён до синхронизации с внутренней базой."));
	exit;
}
$guid = formatGuid($guid);

$newServNum = '';
$newSerial = '';
$newModel = '';
$newMfg = '';
if (null != $equipmentGuid) {
	try {
		$req = $db->prepare("SELECT `eq`.`serviceNumber`, `eq`.`serialNumber`, `mdl`.`name`, `mfg`.`name` ".
								"FROM `requests` AS `rq` ".
            					"JOIN `equipment` AS `eq` ON `rq`.`id` = :requestId ".
            						"AND `eq`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            						"AND `eq`.`guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) ".
            					"LEFT JOIN `equipmentModels` AS `mdl` ON `mdl`.`guid` = `eq`.`equipmentModel_guid` ".
								"LEFT JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`guid` = `mdl`.`equipmentManufacturer_guid`");
    	$req->execute(array('requestId' => $paramValues['id'], 'equipmentGuid' => $equipmentGuid));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
		exit;
	}
	if (!($row = $req->fetch(PDO::FETCH_NUM))) {
		echo json_encode(array('error' => 'Ошибка в данных.'));
		exit;
	}
	
	list($newServNum, $newSerial, $newModel, $newMfg) = $row;
}

include 'init_soap.php';
if (false === $soap) {
	echo json_encode(array('error' => 'Выполнение операции невозможно без синхронизации с внутренним сервером. Попробуйте повторить действие позднее.'));
	exit;
}

$time = date_format(new DateTime, 'Y-m-d H:i:s');
$soapTime = timeToSOAP($time);

try {
	$soapReq = array('sd_requestevent_table' => array(array('CodeNodeSiteSD' => $node_1c,
															'GUID' 			 => $guid,
															'timestamp' 	 => $soapTime,
															'user_guid' 	 => $userGuid,
															'equipment_guid' => $equipmentGuid)));
	$res = $soap->sd_Request_eqChange($soapReq);
} catch (Exception $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера',
							'req' => $soapReq,  
							'orig' => "SOAP error".$e->getMessage()));
	exit;
}

$answer = $res->return->sd_requestevent_row;		
if (is_array($answer))
	$answer = $answer[0];
if (true != $answer->ResultSuccessful) {
	echo json_encode(array('error' => "Оборудование в заявке {$paramValues['id']} не может быть изменён из-за ошибок связи с внутренней базой.",
							'err1C' => $answer->ErrorDescription));
	exit;
}
	
try {
	$db->query('START TRANSACTION');
	$req = $db->prepare("UPDATE `requests` SET `equipment_guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) ".
							"WHERE `id` = :requestId");
	$req->execute(array('equipmentGuid' => $equipmentGuid, 'requestId' => $paramValues['id']));
	
	$from = ('' == $oldServNum ? 'не указано' : "{$oldServNum} - {$oldMfg} {$oldModel} (SN:{$oldSerial})");	
	$to = ('' == $newServNum ? 'не указано' : "{$newServNum} - {$newMfg} {$newModel} (SN:{$newSerial})");
	$text = "Было: {$from}\nСтало: {$to}";	
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, 'eqChange', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :text)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 'text' => $text));
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('ok' => 'ok', 'answer' => $answer));

?>