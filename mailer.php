<?php
include 'config/db.php';
include 'config/soap.php';
include 'config/events.php';
include 'config/jabber.php';

include 'ajax2/common.php';
include 'ajax2/smtp.php';
include 'ajax2/genderByName.php';
include 'ajax2/sms.php';

include 'libphp-jabber/class.jabber.php'; 

// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass,
				  array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
} catch (PDOException $e) {
	print("Ошибка подключения к MySQL ".$e->getMessage()."\n");
	exit;
}
$db->exec("SET NAMES utf8");

// Ищем пользователя 'Робот'
try {
	$req = $db->prepare("SELECT `guid` FROM `users` WHERE `token` = '2549410fef96b09cb7f814df31acb7404c89c433'");
	$req->execute();
} catch (PDOException $e) {
	print("Ошибка MySQL ".$e->getMessage()."\n");
	exit;
} 
if ($row = $req->fetch(PDO::FETCH_NUM)) {
	$userGuid = formatGuid($row[0]); 
} else {
	print("Не найден служебный пользователь\n");
	exit;
} 

// Сброс статуса отправки всех событий! Только для разработки!
// $db->query("UPDATE `requestEvents` SET `mailed` = 0");
// $db->query("UPDATE `requests` SET `alarm` = 0");

// Получаем список администраторов, операторов, инженеров и партнёров
try {
	$req = $db->prepare("SELECT `guid`, `firstName`, `lastName`, `middleName`, `email`, IF(0 = `isDisabled`, `rights`, 'none'), `cellPhone`, `jid` FROM `users` WHERE `isDisabled` = 0");
	$req->execute();
} catch (PDOException $e) {
	print("Ошибка MySQL ".$e->getMessage()."\n");
	exit;
} 

$users = array();
$userRights = array();

while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($uid, $givenName, $familyName, $middleName, $email, $rights, $cellPhone, $jid) = $row;
	$uid = formatGuid($uid); 
  	if (!isset($userRights[$rights]))
		$userRights[$rights] = array();
	$userRights[$rights][] = $uid;
  	$users[$uid] = array('name' => nameWithInitials($familyName, $givenName, $middleName),
	  					 'email' => $email,
	  					 'gender' => genderByNames($givenName, $middleName, $familyName),
						 'cellPhone' => $cellPhone,
						 'jid' => $jid);
}

print "--- users ---\n";
print_r($users);
print "\n\n--- userRights ---\n";
print_r($userRights);

$msgList = array();

// Формируем список методов рассылки
$sendList = array();
try {
	$req = $db->prepare("SELECT `user_guid`, `method`, `event` FROM `sendMethods`");
	$req->execute();
} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
}

while($row = $req->fetch()) {
	list($userGuid, $method, $event) = $row;
	$userGuid = formatGuid($userGuid);
	if (!isset($sendList[$event]))
		$sendList[$event] = array();
	if (!isset($sendList[$event][$method]))
		$sendList[$event][$method] = array();
	$sendList[$event][$method][] = $userGuid;
}

foreach($forcedSendTo as $event => $rightsList) {
	foreach($rightsList as $rights) {
		foreach($userRights[$rights] as $user) {
			if (!isset($sendList[$event]))
				$sendList[$event] = array();
			if (!isset($sendList[$event]['email']))
				$sendList[$event]['email'] = array();
			if (!in_array($user, $sendList[$event]['email']))
				$sendList[$event]['email'][] = $user;
		}
	}
}

print("\n\n--- sendList ---\n");
print_r($sendList);

// Обрабатываем события
$i = 0;
try {
	$req = $db->prepare("SELECT `re`.`timestamp`, `re`.`event`, `re`.`text`, `re`.`newState`, `r`.`id`, `r`.`problem`, ". 
	   							"`r`.`contactPerson_guid`, `div`.`guid`, `div`.`name`, IFNULL(`dcntr`.`name`, `ccntr`.`name`), ". 
	   							"`r`.`slaLevel`, `eq`.`serviceNumber`, `em`.`name`, `emfg`.`name`, ". 
       							"`re`.`user_guid`, `doc`.`name`, `r`.`engineer_guid` ". 
							"FROM `requestEvents` AS `re` ". 
    						"LEFT JOIN `requests` AS `r` ON `r`.`guid` = `re`.`request_guid` ". 
    						"LEFT JOIN `contractDivisions` AS `div` ON `div`.`guid` = `r`.`contractDivision_guid` ".
    						"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ". 
    						"LEFT JOIN `contragents` AS `dcntr` ON `dcntr`.`guid` = `div`.`contragent_guid` ".
    						"LEFT JOIN `contragents` AS `ccntr` ON `ccntr`.`guid` = `c`.`contragent_guid` ".  
    						"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `r`.`equipment_guid` ". 
    						"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ". 
    						"LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`guid` = `em`.`equipmentManufacturer_guid` ". 
    						"LEFT JOIN `documents` AS `doc` ON `doc`.`requestEvent_id` = `re`.`id` ".
    						"WHERE `re`.`id` = @id");
    $req1 = $db->prepare("UPDATE `requestEvents` AS `re1` ".
  							"JOIN ( ".
								"SELECT MIN(`id`) AS `id` ". 
	  								"FROM `requestEvents` ". 
      								"WHERE `mailed` = 0 ".
							") AS `re2` ON `re2`.`id` = `re1`.`id` ".
    						"SET `re1`.`mailed` = 1 ". 
    						"WHERE @id := `re1`.`id`");
} catch (PDOException $e) {
	print("Ошибка MySQL ".$e->getMessage()."\n");
	exit;
}
    						   
$isOpened = array();
while (true) {
	try {
		$req1->execute();
		if (0 == $req1->rowCount())
			break;
		$req->execute(	);
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
	}
	if ($row = $req->fetch(PDO::FETCH_NUM)) {
		list($timestamp, $event, $evText, $newState, $reqId, $problem, $contId, $divId, $div, $contragent, 
			 $slaLevel, $servNum, $eqModel, $eqMfg, $authorId, $document, $engId) = $row;
		$contId = formatGuid($contId);
		$divId = formatGuid($divId);
		$authorId = formatGuid($authorId);
		$engId = formatGuid($engId);
  		if ($event == 'changeState')
    		$event .= $newState;
  		$text = '';
		$html = '';
		$sms = '';
		$authorName = (isset($users[$authorId]) ? $users[$authorId]['name'] : 'Неизвестный');
		$authorGender = (isset($users[$authorId]) ? $users[$authorId]['gender'] : 1);
		switch($event) {
  			case 'open':
  				if ($servNum == 0 || $servNum == '')
  					$eq = 'не указано';
				else
					$eq = "{$eqMfg} {$eqModel}, сервисный номер {$servNum}";
				$problem = preg_replace('/(?:\r?\n)+/', "\r\n", $problem);
	    		$text = "- Поступила новая заявка №{$reqId}, уровень критичности - {$slaLevels[$slaLevel]}\r\n".
	    			 	"  Автор: {$authorName} ({$div}, {$contragent})\r\n".
	    			 	"  Оборудование: {$eq}\r\n".
	    			 	"  Проблема: {$problem}\r\n";
				$problem = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($problem));
	    		$html = "<li>Поступила новая заявка №{$reqId}, уровень критичности - {$slaLevels[$slaLevel]}<br>".
	    			 	"Автор: {$authorName} ({$div}, {$contragent})<br>".
	    			 	"Оборудование: {$eq}<br>".
	    			 	"Проблема: {$problem}";
				$dName = $contragent.(('' == $div || '' == $contragent) ? '' : '. ').$div.('' == $div ? '' : '. ');
				$sms = "Новая заявка №{$reqId}. {$dName}Уровень критичности - {$slaLevels[$slaLevel]}";
	    		$isOpened[] = $reqId;
	    		break;
			case 'changeState'.'accepted':
				$text = "- {$authorName} принял".($authorGender >= 0 ? '' : 'а')." заявку к исполнению\r\n";
				$html = "<li>{$authorName} принял".($authorGender >= 0 ? '' : 'а')." заявку к исполнению";
				$sms = "{$authorName} принял".($authorGender >= 0 ? '' : 'а')." заявку №{$reqId} к исполнению";
				break;
			case 'changeState'.'repaired':
				$text = "- {$authorName} отметил".($authorGender >= 0 ? '' : 'а')." заявку как выполненную\r\n".
					 	"  Если в течение трёх дней Вы не отмените закрытие заявки, то она будет закрыта автоматически\r\n";
				$html = "<li>{$authorName} отметил ".($authorGender >= 0 ? '' : 'а')." заявку как выполненную<br>".
					 	"Если в течение трёх дней Вы не отмените закрытие заявки, то она будет закрыта автоматически";
				$sms =  "{$authorName} отметил".($authorGender >= 0 ? '' : 'а')." заявку №{$reqId} как выполненную";
				break;
			case 'changeDate':
				$text = "- Контрольный срок завершения работ по заявке был перенесён на {$evText}\r\n";
				$evText = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($evText));
				$html = "<li>Контрольный срок завершения работ по заявке был перенесён на {$evText}";
				$sms = "Контрольный срок завершения работ по заявке №{$reqId} был перенесён на {$evText}";
				break;
			case 'comment':
				$text = "- {$authorName} добавил".($authorGender >= 0 ? '' : 'а')." комментарий:\r\n".
						"  {$evText}";
				$evText = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$authorName} добавил".($authorGender >= 0 ? '' : 'а')." комментарий:<br>".
						"{$evText}";
				$sms = "{$authorName} добавил".($authorGender >= 0 ? '' : 'а')." комментарий к заявке №{$reqId}"; 
				break;
			case 'addDocument':
				$text = "- {$authorName} добавил".($authorGender >= 0 ? '' : 'а')." файл '{$document}'\r\n";
				$html = "<li>{$authorName} добавил".($authorGender >= 0 ? '' : 'а')." файл '".htmlspecialchars($document)."'";
				$sms = "{$authorName} добавил".($authorGender >= 0 ? '' : 'а')." файл '{$document}' к заявке №{$reqId}";
				break;
			case 'eqChange':
				$text = "- {$authorName} изменил".($authorGender >= 0 ? '' : 'а')." оборудование\r\n".
						"  {$evText}\n";
				$evText = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$authorName} изменил".($authorGender >= 0 ? '' : 'а')." оборудование <br>".
						"{$evText}";
				$sms = "{$authorName} изменил".($authorGender >= 0 ? '' : 'а')." оборудование в заявке №{$reqId}";
				break;
			case 'onWait':
				$text = "- {$authorName} приостановил".($authorGender >= 0 ? '' : 'а')." выполнение заявки\r\n".
						"  Причина: {$evText}\r\n";
				$evText = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$authorName} приостановил".($authorGender >= 0 ? '' : 'а')." выполнение заявки<br>".
						"Причина: {$evText}";
				$sms = "{$authorName} приостановил".($authorGender >= 0 ? '' : 'а')." выполнение заявки №{$reqId}";
				break;
			case 'offWait':
				$text = "- {$authorName} возобновил".($authorGender >= 0 ? '' : 'а')." выполнение заявки\r\n".
						"  Примечание: {$evText}\r\n";
				$evText = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$authorName} возобновил".($authorGender >= 0 ? '' : 'а')." выполнение заявки<br>".
						"Примечание: {$evText}";
				$sms = "{$authorName} возобновил".($authorGender >= 0 ? '' : 'а')." выполнение заявки №{$reqId}";
				break;
			case 'changePartner':
				$text = "- {$authorName} назначил".($authorGender >= 0 ? '' : 'а')." заявку партнёру {$evText}\r\n";
				$evText = preg_replace('/(?:\r?\n)+/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$authorName} назначил".($authorGender >= 0 ? '' : 'а')." заявку партнёру {$evText}<br>";
				$sms = "{$authorName} назначил".($authorGender >= 0 ? '' : 'а')." заявку №{$reqId} партнёру {$evText}";
				break;
			case 'changeContact':
				break;
			case 'changeService':
				break;
		}
  		if ($text != '')
	  		$msgList[$reqId][] = array('event' => $event,
  								 	'text' => $text,
  								 	'html' => $html,
  								 	'contId' => $contId,
  								 	'engId' => $engId,
  								 	'divId' => $divId,
									'sms' => $sms);
	}
}

// Обрабатываем остаток времени по заявкам
$times = array();

try {
	$req = $db->prepare("SELECT @id");
	$req1 = $db->query("UPDATE `requests` ".
                    	"SET `alarm` = 3 ".
                    	"WHERE `alarm` < 3 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      		"AND `onWait` = 0 AND calcTime_v3(`id`, NOW())/`toRepair` >= 1 AND @id := `id` ".
                    	"LIMIT 1");
	$req2 = $db->query("UPDATE `requests` ".
                    	"SET `alarm` = 2 ".
                    	"WHERE `alarm` < 2 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      		"AND `onWait` = 0 AND calcTime_v3(`id`, NOW())/`toRepair` >= 0.8 AND @id := `id` ".
                    	"LIMIT 1");
	$req3 = $db->query("UPDATE `requests` ".
                    	"SET `alarm` = 1 ".
                    	"WHERE `alarm` < 1 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      		"AND `onWait` = 0 AND calcTime_v3(`id`,NOW())/`toRepair` >= 0.5 AND @id := `id` ".
                    	"LIMIT 1");
} catch (PDOException $e) {
	print("Ошибка MySQL ".$e->getMessage()."\n");
	exit;
}
while (true) {
	try {
		$req1->execute();
		if (0 == $req1->rowCount())
			break;
		$req->execute();
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
	}
	if ($row = $req->fetch(PDO::FETCH_NUM))
		$times[$row[0]] = 'time00';
}
while (true) {
	try {
		$req2->execute();
		if (0 == $req1->rowCount())
			break;
		$req->execute();
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
	}
	if ($row = $req->fetch(PDO::FETCH_NUM))
		$times[$row[0]] = 'time20';
}
while (true) {
	try {
		$req1->execute();
		if (0 == $req1->rowCount())
			break;
		$req->execute();
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
	}
	if ($row = $req->fetch(PDO::FETCH_NUM))
		$times[$row[0]] = 'time50';
}
 
// Автоматически закрываем заявки

$soapParameters = array('login' => $soap_user, 'password' => $soap_pass, "cache_wsdl" => 0);
try {
	$soap = new SoapClient($soap_uri, $soapParameters);
} catch (Exception $e) {
	print "Нет подключения к 1C\n";
	exit;
}

$time = date_format(new DateTime, 'Y-m-d H:i:s');
$soapTime = timeToSOAP($time);

try {
	$req = $db->prepare("SELECT `id`, `guid` FROM `requests` ".
							"WHERE `currentState` = 'repaired' AND NOW() > DATE_ADD(`repairedAt`, INTERVAL 3 DAY)");
	$req1 = $db->prepare("UPDATE `requests` SET `currentState` = 'closed', `stateChangedAt` = NOW() WHERE `id` = :reqId");
	$req2 = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`, `text`) ".
    						"VALUES (NOW(), 'changeState', 'closed', UNHEX(REPLACE(:reqGuid, '-', '')), ".
    								"UNHEX(REPLACE(:userGuid, '-', '')), 'Автоматически')");
	$req->execute();
} catch (PDOException $e) {
	print("Ошибка MySQL ".$e->getMessage()."\n");
	exit;
} 
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	$reqId = $row[0];
	$reqGuid = formatGuid($row[1]);
	try {
		$soapReq = array('sd_requestevent_table' => array(array('CodeNodeSiteSD' => $node_1c,
																'GUID' 			 => $reqGuid,
																'newState' 		 => 'closed',
																'timestamp' 	 => $soapTime,
																'user_guid' 	 => $userGuid)));
		$res = $soap->sd_Request_changeState($soapReq);
		
	} catch (Exception $e) {
		print("SOAP error ".$e->getMessage());
		continue;
	}

	$answer = $res->return->sd_requestevent_row;		
	if (is_array($answer))
		$answer = $answer[0];
	if (true != $answer->ResultSuccessful) {
		print("SOAP request error ".$answer->ErrorDescription);
		continue;
	}
	try {
		$req1->execute(array('reqId' => $reqId));
		$times[$reqId] = 'autoclose';
		$req2->execute(array('reqGuid' => $reqGuid, 'userGuid' => $userGuid));
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
	} 
}	


// Выбираем заявки с предупреждениями по времени
try {
	$req = $db->prepare("SELECT `engineer_guid`, `contractDivision_guid`, `contactPerson_guid` ".
                         "FROM `requests` ".
                         "WHERE `id` = :id");
} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
} 

foreach ($times as $id => $event) {
	try {
		$req->execute(array('id' => $id));
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
		exit;
	} 
	if ($row = $req->fetch(PDO::FETCH_NUM)) {
		$engId = formatGuid($row[0]);
		$divId = formatGuid($row[1]);
		$contId = formatGuid($row[2]);
		$engineerName = (isset($users[$engId]) ? $users[$engId]['name'] : 'Неизвестный');
		switch($event) {
			case 'time00':
				$text = "- !!! Заявка просрочена! Ответственный - {$engineerName}\r\n";
				$html = "<li><span class='error'>Заявка просрочена! Ответственный - {$engineerName}</span>";
				$sms = "Заявка №{$reqId} просрочена! Ответственный - {$engineerName}";
				break;
			case 'time20':
				$text = "- !! По заявке осталось меньше 20% времени до контрольного срока завершения работ!\r\n".
						"  Ответственный - {$engineerName}\r\n";
				$html = "<li><span class='warn'>По заявке осталось меньше 20% времени до контрольного срока завершения работ!<br>".
						"Ответственный - {$engineerName}</span>";
				$sms = "По заявке №{$reqId} осталось меньше 20% времени до контрольного срока завершения работ!";
				break;
			case 'time50':
				$text = "- !! По заявке осталось меньше 50% времени до контрольного срока завершения работ\r\n";
				$html = "<li><span class='warn'>По заявке осталось меньше 50% времени до контрольного срока завершения работ</span>";
				$sms = "По заявке №{$reqId} осталось меньше 50% времени до контрольного срока завершения работ!";
				break;
			case 'autoclose':
				$text = "- Заявка была закрыта автоматически по истечении контрольного срока\r\n";
				$html = "<li>Заявка была закрыта автоматически по истечении контрольного срока";
				$sms = "Заявка №{$reqId} была закрыта автоматически по истечении контрольного срока";
				break;
		}
		if ($text != '')
 				$msgList[$id][] = array('event' => $event,
 								 		'text' => $text,
 									 	'html' => $html,
 									 	'contId' => $contId,
 									 	'engId' => $engId,
 									 	'divId' => $divId,
										'sms' => $sms);
	}
}

print "\n\n--- msgList ---\n";
print_r($msgList);

// Формируем списки рассылки
$mails = array();
$names = array();
$smss = array();
$jabs = array();
foreach ($msgList as $reqId => $msgs) {
	foreach ($msgs as $msg) {
		foreach($sendto[$msg['event']] as $to) {
			print "{$reqId} - {$msg['event']} - {$to}\n"; 
			switch ($to) {
				case 'client':
					if ($msg['contId'] == '')
						break;
					if (isset($sendList[$msg['event']]['email']) && in_array($msg['contId'], $sendList[$msg['event']]['email'])) {
						if (!isset($mails[$msg['contId']][$reqId]))
							$mails[$msg['contId']][$reqId] = array('text' => '', 'html' => '');
						$mails[$msg['contId']][$reqId]['text'] .= $msg['text'];
						$mails[$msg['contId']][$reqId]['html'] .= $msg['html'];
						print "\tclient - email - {$msg['contId']}\n";
					}
					if (isset($sendList[$msg['event']]['sms']) && in_array($msg['contId'], $sendList[$msg['event']]['sms'])) {
						if (!isset($smss[$msg['contId']][$reqId]))
							$smss[$msg['contId']][$reqId] = array();
						$smss[$msg['contId']][$reqId][] = $msg['sms'];
						print "\tclient - sms - {$msg['contId']}\n";
					}
					if (isset($sendList[$msg['event']]['jabber']) && in_array($msg['contId'], $sendList[$msg['event']]['jabber'])) {
						if (!isset($jabs[$msg['contId']][$reqId]))
							$jabs[$msg['contId']][$reqId] = '';
						$jabs[$msg['contId']][$reqId] .= $msg['text'];
						print "\tclient - jabber - {$msg['contId']}\n";
					}
					break;
				case 'engineer':
					if ($msg['engId'] == '')
						break;
					if (in_array($msg['engId'], $sendList[$msg['event']]['email'])) {
						if (!isset($mails[$msg['engId']][$reqId]))
							$mails[$msg['engId']][$reqId] = array('text' => '', 'html' => '');
						$mails[$msg['engId']][$reqId]['text'] .= $msg['text'];
						$mails[$msg['engId']][$reqId]['html'] .= $msg['html'];
						print "\tengineer - email - {$msg['engId']}\n";
					}
					if (in_array($msg['engId'], $sendList[$msg['event']]['sms'])) {
						if (!isset($smss[$msg['engId']][$reqId]))
							$smss[$msg['engId']][$reqId] = array();
						$smss[$msg['engId']][$reqId][] = $msg['sms'];
						print "\tengineer - sms - {$msg['engId']}\n";
											}
					if (in_array($msg['engId'], $sendList[$msg['event']]['jabber'])) {
						if (!isset($jabs[$msg['engId']][$reqId]))
							$jabs[$msg['engId']][$reqId] = '';
						$jabs[$msg['engId']][$reqId] .= $msg['text'];
						print "\tengineer - jabber - {$msg['engId']}\n";
					}
					break;
				case 'engineers':
				case 'operators':
				case 'admins':
					if (!isset($userRights[$to]))
						break;
					foreach ($userRights[$to] as $uid) {
						if (isset($sendList[$msg['event']]['email']) && in_array($uid, $sendList[$msg['event']]['email'])) {
							if (!isset($mails[$uid][$reqId]))
								$mails[$uid][$reqId] = array('text' => '', 'html' => '');
							$mails[$uid][$reqId]['text'] .= $msg['text'];
							$mails[$uid][$reqId]['html'] .= $msg['html'];
							print "{$to} - email - {$uid}\n";
						}
						if (isset($sendList[$msg['event']]['sms']) && in_array($uid, $sendList[$msg['event']]['sms'])) {
							if (!isset($smss[$uid][$reqId]))
								$smss[$uid][$reqId] = array();
							$smss[$uid][$reqId][] = $msg['sms'];
							print "{$to} - sms - {$uid}\n";
						}
						if (isset($sendList[$msg['event']]['jabber']) && in_array($uid, $sendList[$msg['event']]['jabber'])) {
							if (!isset($jabs[$uid][$reqId]))
								$jabs[$uid][$reqId] = '';
							$jabs[$uid][$reqId] .= $msg['text'];
							print "{$to} - jabber - {$uid}\n";
						}
					}
					break;
			}
		}
	}
}

print "\n\n--- mails ---\n";
print_r($mails);
print "\n\n--- smss ---\n";
print_r($smss);
print "\n\n--- jabs ---\n";
print_r($jabs);

// Рассылаем электронную почту 
foreach ($mails as $uid => $requests) {
//	print "----------------------\n";
	if (!isset($users[$uid]) || $users[$uid]['email'] == '')
		break;
	foreach ($requests as $reqId => $mail) {
		$msg = compose_mail($mail['text'].
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
    						"<ul>{$mail['html']}</ul>".
    						"<p>----<br>".
    						"С уважением, служба технической поддержки «Со-Действие».<br>".
    						"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
							"</div>");
		if (in_array($reqId, $isOpened))
			$subj = "Открыта новая заявка №{$reqId}";
		else 
			$subj = "События по заявке №{$reqId}";
		smtpmail($users[$uid]['email'], $users[$uid]['name'], $subj, $msg['body'], $msg['header']);
//		print "{$users[$uid]['email']} - {$users[$uid]['name']} - {$reqId} - {$subj}\n{$mail['text']}\n";
	}	
}

// Рассылаем SMS 
foreach ($smss as $uid => $requests) {
//	print "----------------------\n";
	if (!isset($users[$uid]) || $users[$uid]['cellPhone'] == '')
		break;
	foreach ($requests as $reqId => $sms) {
		foreach($sms as $message) {
			send_sms($message, '7'.$users[$uid]['cellPhone']);
//			print "7{$users[$uid]['cellPhone']} - {$message}\n";
		}
	}	
}

// Рассылаем сообщения в Jabber 
$jabber = new Jabber();
$jabber->server = $jaServer;
$jabber->port = $jaPort;
$jabber->username = $jaUser;
$jabber->password = $jaPass;
$jabber->resource = $jaResource;
if ($jabber->Connect()) {
    if ($jabber->SendAuth() && $jabber->SendPresence(null, null, 'online')) {
		foreach ($jabs as $uid => $requests) {
//			print "----------------------\n";
			if (!isset($users[$uid]) || $users[$uid]['jid'] == '')
				break;
			foreach ($requests as $reqId => $text) {
				if (in_array($reqId, $isOpened))
					$subj = "Открыта новая заявка №{$reqId}";
				else 
					$subj = "События по заявке №{$reqId}";
	    		$jabber->SendMessage($users[$uid]['jid'], 'normal', null, array('subject' => $subj, 'body' => $message));
//				print "{$users[$uid]['jid']} - {$subj} - {$text}\n";
			}	
		}
    }
    $jabber->Disconnect();
}

?>