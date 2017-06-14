<?php

header('Content-Type: application/json; charset=UTF-8');

include '../config/events.php'; 
include 'common.php';
include 'init.php';

try {
	$req = $db->prepare("UPDATE `sendMethods` SET `updated` = 0 WHERE `user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))");
	$req->execute(array('userGuid' => $userGuid));
	$req = $db->prepare("UPDATE `users` SET `cellPhone` = :cellPhone, `jid` = :jid WHERE `guid` = UNHEX(REPLACE(:userGuid, '-', ''))");
	$req->execute(array('userGuid' => $userGuid, 'cellPhone' => $paramValues['cellPhone'], 'jid' => $paramValues['jid']));
	$req = $db->prepare("INSERT INTO `sendMethods` (`user_guid`, `method`, `event`, `updated`) ".
							"VALUES (UNHEX(REPLACE(:userGuid, '-', '')), :method, :event, 1) ".
							"ON DUPLICATE KEY UPDATE `updated` = 1");
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error ".$e->getMessage()));
	exit;
}

foreach(explode('|', $paramValues['data']) as $pair) {
	if ('' == $pair)
		continue;
	list($event, $method) = explode(',', $pair);
	$req->execute(array('userGuid' => $userGuid, 'method' => $method, 'event' => $event));
}

try {
	$req = $db->prepare("DELETE FROM `sendMethods` WHERE `user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) AND `updated` = 0");
	$req->execute(array('userGuid' => $userGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error ".$e->getMessage()));
	exit;
}

echo json_encode(array('ok' => 'ok'));
exit;
?>