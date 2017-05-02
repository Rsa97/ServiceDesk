<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';
include '../config/files.php';

if (!isset($_FILES['file'])) {
	header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
	exit;
}

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT DISTINCT `rq`.`guid` AS `guid` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`onWait` = 0 AND `rq`.`id` = :requestId ".
            					"AND `div`.`guid` = `rq`.`contractDivision_guid` ".
            				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            					"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` OR `rq`.`currentState` NOT IN ('closed', 'canceled')) ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
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

if (!($row = $req->fetch(PDO::FETCH_ASSOC))) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}	
if (null === $row['guid']) {
	echo json_encode(array('error' => "Документ не может быть добавлен к заявке {$paramValues['id']} до синхронизации с внутренней базой."));
	exit;
}

$guid = formatGuid($row['guid']);

$error = (is_array($_FILES['file']['error']) ? $_FILES['file']['name'][0] : $_FILES['file']['name']);
if ($error != 0) {
	echo json_encode(array('error' => 'Ошибка передачи файла.'));
	exit;
}

$size = (is_array($_FILES['file']['size']) ? $_FILES['file']['size'][0] : $_FILES['file']['size']);
if ($size > 10*1024*1024) {
	echo json_encode(array('error' => 'Слишком большой файл, ограничение - 10 Mb.'));
	exit;
}

$fileName = stripslashes(is_array($_FILES['file']['name']) ? $_FILES['file']['name'][0] : $_FILES['file']['name']);
$fileTName = (is_array($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'][0] : $_FILES['file']['tmp_name']);

$ext = '';
if (preg_match('/\.(.*?)$/', $fileName, $match))
	$ext = ".{$match[1]}";

$storeDir = "{$fileStorage}/{$paramValues['id']}";
if (!file_exists($storeDir))
	mkdir($storeDir, 0755);

$docB64 = base64_encode(fread(fopen($fileTName, "r"), filesize($fileTName)));

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
															'nameDoc'		 => $fileName,
															'DocData'		 => $docB64,
															'user_guid' 	 => $userGuid)));
	$res = $soap->sd_Request_addDocument($soapReq);
} catch (Exception $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера',
							'req' => $soapReq,  
							'orig' => "SOAP error".$e->getMessage()));
	unlink($storeName);
	exit;
}

$answer = $res->return->sd_requestevent_row;		
if (is_array($answer))
	$answer = $answer[0];
if (true != $answer->ResultSuccessful) {
	echo json_encode(array('error' => "Документ не может быть добавлен к заявке {$paramValues['id']} из-за ошибок связи с внутренней базой.",
							'err1C' => $res));
	unlink($storeName);
	exit;
}

$uniqName = "{$answer->UniqueNameDoc}{$ext}";
$storeName = "{$storeDir}/{$uniqName}";

if (!move_uploaded_file($fileTName, $storeName)) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
	exit;
}
	
try {
	$db->query('START TRANSACTION');
	
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_guid`, `user_guid`) ".
							"VALUES(:stateChangedAt, 'addDocument', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')))");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid));
	
	$eventId = $db->lastInsertId();

	$req = $db->prepare("INSERT INTO `documents` (`guid`, `name`, `uniqueName`, `requestEvent_id`) ".
							"VALUES(UNHEX(REPLACE(:fileGuid, '-', '')), :fileName, :fileUniqueName, :eventId)");
	$req->execute(array('fileGuid' => $answer->UniqueNameDoc, 'fileName' => $fileName, 'fileUniqueName' => $uniqName, 
						'eventId' => $eventId));
	
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('ok' => 'ok', 'answer' => $answer));

?>