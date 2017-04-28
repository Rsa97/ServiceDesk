<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';

$allowedTo = array('client', 'admin', 'operator');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$err = '';

$list = implode(',', array_filter(explode(',', $paramValues['ids']), function($num) { return preg_match('/^\d+$/', $num); }));

if ('' == $list) {
	header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
	exit;
}

try {
// Получаем список заявок с проверкой прав доступа
	$req = $db->prepare("SELECT DISTINCT `rq`.`id` AS `id`, `rq`.`guid` AS `guid` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`onWait` = 0 AND FIND_IN_SET(`rq`.`id`, :list) ".
            					"AND 'repaired' = `rq`.`currentState` AND `div`.`guid` = `rq`.`contractDivision_guid` ".
            				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            					"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` OR `rq`.`currentState` NOT IN ('closed', 'canceled')) ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
          					"WHERE (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
          							"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
    $req->execute(array('byClient' => $byClient, 'userGuid' => $userGuid, 'byPartner' => $byPartner, 'partnerGuid' => $partnerGuid,
    					'list' => $list));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}
$list = array();
$notSync = array();
while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
	if (null === $row['guid'])
		$notSync[] = $row['id'];
	else 
		$list[$row['id']] = formatGuid($row['guid']);
}

$err = '';
if (count($notSync) > 0)
	$err = 'Статус заяв'.(count($notSync) > 1 ? 'ок ' : 'ки ').
			implode(', ', $notSync).' не может быть изменён до синхронизации с внутренней базой.';

include 'init_soap.php';
if (false === $soap) {
	echo json_encode(array('error' => 'Выполнение операции невозможно без синхронизации с внутренней базой. Попробуйте повторить действие позднее.'));
	exit;
}

$time = date_format(new DateTime, 'Y-m-d H:i:s');
$soapTime = timeToSOAP($time);
$err1C = array(); 

foreach($list as $id => $guid) {

	try {
		$soapReq = array('sd_requestevent_table' => array(array('CodeNodeSiteSD' => 'SDTEST2',
																'GUID' => $list[$id],
																'newState' => 'closed',
																'timestamp' => $soapTime,
																'user_guid' => $userGuid)));
		$res = $soap->sd_Request_changeState($soapReq);
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
		$err1C[$id] = $answer->ErrorDescription;
		continue;
	}
	
	try {
		$db->query('START TRANSACTION');
		
		$req = $db->prepare("UPDATE `requests` SET `currentState` = 'closed', `stateChangedAt` = :stateChangedAt ".
								"WHERE `id` = :requestId");
		$req->execute(array('stateChangedAt' => $time, 'requestId' => $id));
		$req = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`) ".
								"VALUES (:stateChangedAt, 'changeState', 'closed', UNHEX(REPLACE(:requestGuid, '-', '')), ".
										"UNHEX(REPLACE(:userGuid, '-', '')))");
		$req->execute(array('stateChangedAt' => $time, 'requestGuid' => $guid, 'userGuid' => $userGuid));

		$db->query('COMMIT');
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
		exit;
	}
}

$res = array();

$res['ok'] = 'ok';
if (count($err1C) != 0) {
	$err .= '\nСтатус заяв'.(count($err1C) > 1 ? 'ок ' : 'ки ').
			implode(', ', array_keys($err1C)).' не может быть изменён из-за ошибок связи с внутренней базой.';
}
$res['err1C'] = $err1C;
if ('' != $err)
	$res['error'] = $err;
echo json_encode($res);

?>