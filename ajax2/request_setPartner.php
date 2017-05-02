<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$allowedTo = array('engineer', 'admin');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$partnerGuid = $paramValues['partner'];
if ('0' == $partnerGuid)
	$partnerGuid = null;

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT `rq`.`guid`, `p`.`name` ".
          					"FROM `requests` AS `rq` ".
          					"JOIN `partnerDivisions` AS `pd` ON `pd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ". 
          						"AND `rq`.`id` = :requestId AND `rq`.`currentState` IN ('received') ".
          					"JOIN `partners` AS `p` ON `p`.`guid` = `pd`.`partner_guid` ".
							"WHERE :partnerGuid IS NULL OR `pd`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', ''))");
    $req->execute(array('requestId' => $paramValues['request'], 'partnerGuid' => $partnerGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

$guid = null;
if ($row = $req->fetch(PDO::FETCH_NUM)) {
	$guid = formatGuid($row[0]);
	$partnerName = $row[1];
}

if (null === $guid) {
	echo json_encode(array('error' => "Заявке {$paramValues['request']} не может быть назначена партнёру до синхронизации с внутренней базой."));
	exit;
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
															'partner_guid'	 => $partnerGuid)));
	$res = $soap->sd_Request_setPartner($soapReq);
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
	echo json_encode(array('error' => "Заявке {$paramValues['request']} не может быть назхначена партнёру из-за ошибок связи с внутренней базой.",
							'err1C' => $res));
	exit;
}
	
try {
	$db->query('START TRANSACTION');
	
	$req = $db->prepare("UPDATE `requests` SET `partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')) WHERE `id` = :requestId");
	$req->execute(array('requestId' => $paramValues['request'], 'partnerGuid' => $partnerGuid));
	if (null == $partnerGuid)
		$text = null;
	else 
		$text = $partnerName;
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, 'changePartner', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :text)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 'text' => $text));
	$db->query('COMMIT');
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('Ok' => 'Ok'));
?>