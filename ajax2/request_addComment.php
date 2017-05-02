<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$comment = trim($paramValues['comment']);

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
	echo json_encode(array('error' => "Комментарий к заявке {$paramValues['id']} не может быть добавлен до синхронизации с внутренней базой."));
	exit;
}

$guid = formatGuid($row['guid']);

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
															'comment' 		 => $comment)));
	$res = $soap->sd_Request_comment($soapReq);
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
	echo json_encode(array('error' => "Комментарий к заявке {$paramValues['id']} не может быть добавлен из-за ошибок связи с внутренней базой.",
							'err1C' => $res));
	exit;
}
	
try {
	$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_guid`, `user_guid`, `text`) ".
							"VALUES (:stateChangedAt, 'comment', UNHEX(REPLACE(:requestGuid, '-', '')), ".
									"UNHEX(REPLACE(:userGuid, '-', '')), :comment)");
	$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid, 'comment' => $comment));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}

echo json_encode(array('ok' => 'ok', 'answer' => $answer));

?>