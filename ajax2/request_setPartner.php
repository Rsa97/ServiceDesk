<?php

header('Content-Type: application/json; charset=UTF-8');

include 'common.php';
include 'init.php';
include 'smtp.php';

$allowedTo = array('engineer', 'admin');

if (!in_array($rights, $allowedTo)) {
	echo json_encode(array('error' => 'Недостаточно прав.'));
	exit;
}

$partnerGuid = $paramValues['partner'];
if ('0' == $partnerGuid)
	$partnerGuid = null;

// Получаем текущего партнёра по заявке
$cancelEmails = array();
try {
	$req = $db->prepare("SELECT `u`.`email`, `u`.`lastName`, `u`.`firstName`, `u`.`middleName` ".
							"FROM `requests` AS `rq` ".
							"JOIN `users` AS `u` ON `rq`.`id` = :requestId AND `u`.`partner_guid` = `rq`.`partner_guid` ".
								"AND `u`.`isDisabled` = 0");
	$req->execute(array('requestId' => $paramValues['request']));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
	exit;
}
while ($row = $req->fetch(PDO::FETCH_NUM))
	$cancelEmails[] = array('email' => $row[0], 'name' => nameWithInitials($row[1], $row[2], $row[3]));

// Получаем нового партнёра по заявке
$appointEmails = array();
if (null != $partnerGuid) {
	try {
		$req = $db->prepare("SELECT `email`, `lastName`, `firstName`, `middleName` ".
								"FROM `users` WHERE `partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')) ".
								"AND `isDisabled` = 0");
		$req->execute(array('partnerGuid' => $partnerGuid));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
		exit;
	}
	while ($row = $req->fetch(PDO::FETCH_NUM))
		$appointEmails[] = array('email' => $row[0], 'name' => nameWithInitials($row[1], $row[2], $row[3]));
}

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
	echo json_encode(array('error' => "Заявке {$paramValues['request']} не может быть назначена партнёру из-за ошибок связи с внутренней базой.",
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

if (count($cancelEmails) > 0) {
	$msg = compose_mail("Отменено назначение вам заявки №{$paramValues['request']}".
						"\r\n----\r\n".
						"С уважением, служба технической поддержки «Со-Действие».\r\n".
						"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.",
						"<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>".
						"<style><!--".
						".header { font-size: 1.1em; font-weight: bold; } ".
						".login { font-weight: bold; font-style: italic; } ".
						".error { color: red; } ".
						".warn { color: #FF5050; } ".
						"--></style>".
						"<div dir='ltr'>".
						"Отменено назначение вам заявки №{$paramValues['request']}".
						"<p>----<br>".
						"С уважением, служба технической поддержки «Со-Действие».<br>".
						"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
						"</div>");
	$subj = "Отменено назначение вам заявки №{$paramValues['request']}";
	foreach($cancelEmails as $user)
		smtpmail($user['email'], $user['name'], $subj, $msg['body'], $msg['header']);
}

if (count($appointEmails) > 0) {
	try {
		$req = $db->prepare("SELECT `problem` FROM `requests` WHERE `id` = :requestId");
		$req->execute(array('requestId' => $paramValues['request']));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error in line ".$e->getLine().': '.$e->getMessage()));
		exit;
	}
	if ($row = $req->fetch(PDO::FETCH_NUM)) {
		$msg = compose_mail("Вам назначена новая заявка №{$paramValues['request']}\r\n".
							$row[0].
							"\r\n----\r\n".
							"С уважением, служба технической поддержки «Со-Действие».\r\n".
							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.",
							"<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>".
							"<style><!--".
							".header { font-size: 1.1em; font-weight: bold; } ".
							".login { font-weight: bold; font-style: italic; } ".
							".error { color: red; } ".
							".warn { color: #FF5050; } ".
							"--></style>".
							"<div dir='ltr'>".
							"<p>Вам назначена новая заявка №{$paramValues['request']}<p>".
							nl2br(htmlspecialchars(strip_tags($row[0]))).
							"<p>----<br>".
							"С уважением, служба технической поддержки «Со-Действие».<br>".
							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
							"</div>");
		$subj = "Вам назначена новая заявка №{$paramValues['request']}";
		foreach($appointEmails as $user)
			smtpmail($user['email'], $user['name'], $subj, $msg['body'], $msg['header']);
	}
}

echo json_encode(array('Ok' => 'Ok'));
?>