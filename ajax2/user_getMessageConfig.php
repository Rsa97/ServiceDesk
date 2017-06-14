<?php

header('Content-Type: application/json; charset=UTF-8');

include '../config/events.php'; 
include 'common.php';
include 'init.php';

$enabled = array();
$table = '<table><thead><tr><th>';
foreach($sendMethodNames as $code => $name) {
	$table .= "<th>".htmlspecialchars($name);
	$enabled[$code] = array();
}

try {
	$req = $db->prepare("SELECT `method`, `event` FROM `sendMethods` WHERE `user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))");
	$req->execute(array('userGuid' => $userGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error ".$e->getMessage()));
	exit;
}

while ($row = $req->fetch(PDO::FETCH_ASSOC))
	$enabled[$row['method']][] = $row['event'];

$table .= '<tbody>';
foreach($eventNames as $code => $name) {
	if (in_array($rights, $sendto[$code]) || in_array($rights.'s', $sendto[$code])) {
		$table .= "<tr data-id='{$code}'><td>".htmlspecialchars($name);
		foreach($sendMethodNames as $mCode => $mName)
			$table .= "<td data-id='{$mCode}'><input type='checkbox'".
					  ((in_array($code, $enabled[$mCode]) || ('email' == $mCode &&  in_array($rights, $forcedSendTo[$code]))) ? ' checked' : '').
					  (('email' == $mCode && in_array($rights, $forcedSendTo[$code])) ? ' disabled' : '').">";
	}
}

try {
	$req = $db->prepare("SELECT `email`, `cellPhone`, `jid` FROM `users` WHERE `guid` = UNHEX(REPLACE(:userGuid, '-', ''))");
	$req->execute(array('userGuid' => $userGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error ".$e->getMessage()));
	exit;
}
$row = $req->fetch(PDO::FETCH_ASSOC);
$cellPhone = ('' != $row['cellPhone'] ? '+7'.$row['cellPhone'] : '');

echo json_encode(array('sendMethods' => $table, '_userRole' => $rightNames[$rights], '_userEmail' => $row['email'], 
					   '_cellPhone' => $cellPhone, '_jabberUID' => $row['jid']));
exit;
?>