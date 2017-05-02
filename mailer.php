<?php
include 'config/db.php';
include 'config/soap.php';
include 'ajax2/common.php';
include 'ajax2/smtp.php';
include 'ajax2/genderByName.php';

/*$sendto = array('open'                   => 'engineers,operators,admins',
           		'changeState'.'accepted' => 'contact',
          		'changeState'.'fixed'    => '',
           		'changeState'.'repaired' => 'contact',
           		'changeState'.'closed'   => '',
           		'changeState'.'canceled' => '',
           		'unClose'                => 'engineer',
           		'unCancel'               => 'engineers,operators,admins',
           		'onWait'                 => 'contact',
           		'offWait'                => '',
           		'changeDate'             => 'contact',
           		'comment'                => 'contact,engineer',
           		'addDocument'            => 'contact,engineer',
           		'time50'                 => 'engineer,operators',
           		'time20'                 => 'engineer,operators',
           		'time00'                 => 'engineer,operators,admins',
           		'autoclose'              => 'contact',
           		'eqChange'				 => 'contact'
          ); */

$sendto = array();

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
// $mysqli->query("UPDATE `requestEvents` SET `mailed` = 0");
//$mysqli->query("UPDATE `request` SET `alarm` = 0");

// Получаем список администраторов, операторов, инженеров и партнёров
/* try {
	$req = $db->prepare("SELECT `id`, `firstName`, `secondName`, `middleName`, `email`, `rights` FROM `users` WHERE `isDisabled` = 0");
	$req->execute();
} catch (PDOException $e) {
	print("Ошибка MySQL ".$e->getMessage()."\n");
	exit;
} 

$users = array();
$userRights = array();

while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($uid, $givenName, $familyName, $middleName, $email, $rights) = $row; 
  	if ($email != '') {
  		$rights .= 's';
  	if (!isset($userRights[$rights]))
		$userRights[$rights] = array();
	$userRights[$rights][] = $uid;
  	$users[$uid] = array('name' => nameWithInitials($familyName, $givenName, $middleName),
	  					 'email' => $email,
	  					 'gender' => genderByNames($givenName, $middleName, $familyName));
  }
}
$req->close();

$msgList = array();

// Обрабатываем события
$i = 0;
try {
	$req = $db->prepare("SELECT `re`.`timestamp`, `re`.`event`, `re`.`text`, `re`.`newState`, `r`.`id`, `r`.`problem`, ".
                              "`r`.`contactPersons_id`, `div`.`id`, `div`.`name`, `cntr`.`name`, ".
                              "`r`.`slaLevel`, `r`.`equipment_id`, `em`.`name`, `emfg`.`name`, ".
                              "`re`.`users_id`, `doc`.`name`, `r`.`engeneer_id` ".
                         "FROM `requestEvents` AS `re` ".
                           "LEFT JOIN `request` AS `r` ON `r`.`id` = `re`.`request_id` ".
                           "LEFT JOIN `contractDivisions` AS `div` ON `div`.`id` = `r`.`contractDivisions_id` ".
                           "LEFT JOIN `contragents` AS `cntr` ON `cntr`.`id` = `div`.`contragents_id` ".
                           "LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `r`.`equipment_id` ".
                           "LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
                           "LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `em`.`equipmentManufacturers_id` ".
                           "LEFT JOIN `documents` AS `doc` ON `doc`.`requestEvents_id` = `re`.`id` ".
                         "WHERE `re`.`id` = @id ".
                         "ORDER BY `request_id`, `timestamp`");
$req->bind_result($timestamp, $event, $evText, $newState, $reqId, $problem, $contId, $divId, $div, $contragent, 
				  $slaLevel, $servNum, $eqModel, $eqMfg, $authorId, $document, $engId);

$isOpened = array();
while ($mysqli->query("UPDATE `requestEvents` SET `mailed` = 1 WHERE `mailed` = 0 AND @id := `id` LIMIT 1") && ($mysqli->affected_rows > 0)) {
	if (!$req->execute()) {
		print ("Ошибка MySQL ({$mysqli->errno})  {$mysqli->error}\n");
		exit;
	}
	$req->store_result();
	if ($req->fetch()) {
  		if ($event == 'changeState')
    		$event .= $newState;
  		$text = '';
		$html = '';
		if ($sendto[$event] == '')
			continue;
		switch($event) {
  			case 'open':
  				if ($servNum == 0 || $servNum == '')
  					$eq = 'не указано';
				else
					$eq = "{$eqMfg} {$eqModel}, сервисный номер {$servNum}";
	    		$text = "- Появилась новая заявка №{$reqId}, уровень критичности - {$slaLevels[$slaLevel]}\r\n".
	    			 	"  Автор: {$users[$authorId]['name']} ({$div}, {$contragent})\r\n".
	    			 	"  Оборудование: {$eq}\r\n".
	    			 	"  Проблема: {$problem}\r\n";
				$problem = preg_replace('/\r?\n/', '<br>', htmlspecialchars($problem));
	    		$html = "<li>Появилась новая заявка №{$reqId}, уровень критичности - {$slaLevels[$slaLevel]}<br>".
	    			 	"Автор: {$users[$authorId]['name']} ({$div}, {$contragent})<br>".
	    			 	"Оборудование: {$eq}<br>".
	    			 	"Проблема: {$problem}";
	    		$isOpened[] = $reqId;
	    		break;
			case 'changeState'.'accepted':
				$text = "- {$users[$authorId]['name']} принял".($users[$authorId]['gender'] >= 0 ? '' : 'а')." заявку к исполнению\r\n";
				$html = "<li>{$users[$authorId]['name']} принял".($users[$authorId]['gender'] >= 0 ? '' : 'а')." заявку к исполнению";
				break;
			case 'changeState'.'repaired':
				$text = "- {$users[$authorId]['name']} отметил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." заявку как выполненную\r\n".
					 	"  Если в течение трёх дней Вы не отмените закрытие заявки, то она будет закрыта автоматически\r\n";
				$html = "<li>{$users[$authorId]['name']} отметил ".($users[$authorId]['gender'] >= 0 ? '' : 'а')." заявку как выполненную<br>".
					 	"Если в течение трёх дней Вы не отмените закрытие заявки, то она будет закрыта автоматически";
				break;
			case 'changeDate':
				$text = "- Контрольный срок завершения работ по заявке был перенесён на {$evText}\r\n";
				$evText = preg_replace('/\r?\n/', '<br>', htmlspecialchars($evText));
				$html = "<li>Контрольный срок завершения работ по заявке был перенесён на {$evText}";
				break;
			case 'comment':
				$text = "- {$users[$authorId]['name']} добавил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." комментарий:\r\n".
						"  {$evText}";
				$evText = preg_replace('/\r?\n/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$users[$authorId]['name']} добавил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." комментарий:<br>".
						"{$evText}";
				break;
			case 'comment':
				$text = "- {$users[$authorId]['name']} добавил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." комментарий:\r\n".
						"  {$evText}\n";
				$evText = preg_replace('/\r?\n/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$users[$authorId]['name']} добавил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." комментарий:<br>".
						"{$evText}";
				break;
			case 'addDocument':
				$text = "- {$users[$authorId]['name']} добавил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." файл '{$document}'\r\n";
				$html = "<li>{$users[$authorId]['name']} добавил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." файл '".htmlspecialchars($document)."'";
				break;
			case 'eqChange':
				$text = "- {$users[$authorId]['name']} изменил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." оборудование\r\n".
						"  {$evText}\n";
				$evText = preg_replace('/\r?\n/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$users[$authorId]['name']} изменил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." оборудование <br>".
						"{$evText}";
			case 'onWait':
				$text = "- {$users[$authorId]['name']} приостановил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." выполнение заявки\r\n".
						"  Причина: {$evText}\r\n";
				$evText = preg_replace('/\r?\n/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$users[$authorId]['name']} приостановил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." выполнение заявки<br>".
						"Причина: {$evText}";
			case 'offWait':
				$text = "- {$users[$authorId]['name']} возобновил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." выполнение заявки\r\n".
						"  Примечание: {$evText}\r\n";
				$evText = preg_replace('/\r?\n/', '<br>', htmlspecialchars($evText));
				$html = "<li>{$users[$authorId]['name']} возобновил".($users[$authorId]['gender'] >= 0 ? '' : 'а')." выполнение заявки<br>".
						"Примечание: {$evText}";
				break;
			break;
		}
  		if ($text != '')
	  		$msgList[$reqId][] = array('event' => $event,
  								 	'text' => $text,
  								 	'html' => $html,
  								 	'contId' => $contId,
  								 	'engId' => $engId,
  								 	'divId' => $divId);
	}
}
$req->close();

// Обрабатываем остаток времени по заявкам
$req = $mysqli->prepare("SELECT @id");
$req->bind_result($id);
$times = array();
while ($mysqli->query("UPDATE `request` ".
                    "SET `alarm` = 3 ".
                    "WHERE `alarm` < 3 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      "AND calcTime_v2(`id`,NOW())/`toRepair` >= 1 ".
                      "AND @id := `id` ".
                    "LIMIT 1") && ($mysqli->affected_rows > 0)) {
	if (!$req->execute()) {
		print ("Ошибка MySQL ({$mysqli->errno})  {$mysqli->error}\n");
		exit;
	}
	$req->store_result();
	if ($req->fetch())
		$times[$id] = 'time00';
}
while ($mysqli->query("UPDATE `request` ".
                    "SET `alarm` = 2 ".
                    "WHERE `alarm` < 2 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      "AND calcTime_v2(`id`,NOW())/`toRepair` >= 0.8 ".
                      "AND @id := `id` ".
                    "LIMIT 1")  && ($mysqli->affected_rows > 0)) {
	if (!$req->execute()) {
		print ("Ошибка MySQL ({$mysqli->errno})  {$mysqli->error}\n");
		exit;
	}
	$req->store_result();
	if ($req->fetch())
    	$times[$id] = 'time20';
}
while ($mysqli->query("UPDATE `request` ".
                    "SET `alarm` = 1 ".
                    "WHERE `alarm` < 1 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      "AND calcTime_v2(`id`,NOW())/`toRepair` >= 0.5 ".
                      "AND @id := `id` ".
                    "LIMIT 1") && ($mysqli->affected_rows > 0)) {
	if (!$req->execute()) {
		print ("Ошибка MySQL ({$mysqli->errno})  {$mysqli->error}\n");
		exit;
	}
	$req->store_result();
	if ($req->fetch())
    	$times[$id] = 'time50';
}
*/ 

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
		$req2->execute(array('reqGuid' => $reqGuid, 'userGuid' => $userGuid));
	} catch (PDOException $e) {
		print("Ошибка MySQL ".$e->getMessage()."\n");
	} 
}	

/*
// Выбираем заявки с предупреждениями по времени
$req = $mysqli->prepare("SELECT `engeneer_id`, `contractDivisions_id`, `contactPersons_id` ".
                         "FROM `request`".
                         "WHERE `id` = ?");
$req->bind_param('i', $id);
$req->bind_result($engId, $divId, $contId);
foreach ($times as $id => $event) {
	if ($sendto[$event]== '')
		continue;
	if (!$req->execute()) {
		print ("Ошибка MySQL ({$mysqli->errno})  {$mysqli->error}\n");
		exit;
	}
	$req->store_result();
	if ($req->fetch()) {
		switch($event) {
			case 'time00':
				$text = "- !!! Заявка просрочена! Ответственный - {$users[$engId]['name']}\r\n";
				$html = "<li><span class='error'>Заявка просрочена! Ответственный - {$users[$engId]['name']}</span>";
				break;
			case 'time20':
				$text = "- !! По заявке осталось меньше 20% времени до контрольного срока завершения работ!\r\n".
						"  Ответственный - {$users[$engId]['name']}\r\n";
				$html = "<li><span class='warn'>По заявке осталось меньше 20% времени до контрольного срока завершения работ!<br>".
						"Ответственный - {$users[$engId]['name']}</span>";
				break;
			case 'time50':
				$text = "- !! По заявке осталось меньше 50% времени до контрольного срока завершения работ\r\n";
				$html = "<li><span class='warn'>По заявке осталось меньше 50% времени до контрольного срока завершения работ</span>";
				break;
			case 'autoclose':
				$text = "- Заявка была закрыта автоматически по истечении контрольного срока\r\n";
				$html = "<li>Заявка была закрыта автоматически по истечении контрольного срока";
				break;
		}
		if ($text != '')
 				$msgList[$id][] = array('event' => $event,
 								 		'text' => $text,
 									 	'html' => $html,
 									 	'contId' => $contId,
 									 	'engId' => $engId,
 									 	'divId' => $divId);
	}
}
$req->close();

// Формируем список рассылки
$req = $mysqli->prepare("SELECT `ac`.`partner_id` ".
							"FROM `allowedContracts` AS `ac` ".
							"LEFT JOIN `users` AS `u` ON `u`.`partner_id` = `ac`.`partner_id` ".
							"WHERE `ac`.`contractDivisions_id` = ? AND `u`.`isDisabled` = 0");
$req->bind_param('i', $divId);
$req->bind_result($partnerId);
$mails = array();
$names = array();
foreach ($msgList as $reqId => $msgs) {
	foreach ($msgs as $msg) {
		foreach(split(',', $sendto[$msg['event']]) as $to) {
			switch ($to) {
				case 'contact':
					if ($msg['contId'] == '')
						break;
					if (!isset($mails[$msg['contId']][$reqId]))
						$mails[$msg['contId']][$reqId] = array('text' => '', 'html' => '');
					$mails[$msg['contId']][$reqId]['text'] .= $msg['text'];
					$mails[$msg['contId']][$reqId]['html'] .= $msg['html'];
					break;
				case 'engeneer':
					if ($msg['engId'] == '')
						break;
					if (!isset($mails[$msg['engId']][$reqId]))
						$mails[$msg['engId']][$reqId] = array('text' => '', 'html' => '');
					$mails[$msg['engId']][$reqId]['text'] .= $msg['text'];
					$mails[$msg['engId']][$reqId]['html'] .= $msg['html'];
					break;
				case 'engeneers':
				case 'operators':
				case 'admins':
					print_r($userRights[$to]);
					if (!isset($userRights[$to]))
						break;
					foreach ($userRights[$to] as $uid) {
						if (!isset($mails[$uid][$reqId]))
							$mails[$uid][$reqId] = array('text' => '', 'html' => '');
						$mails[$uid][$reqId]['text'] .= $msg['text'];
						$mails[$uid][$reqId]['html'] .= $msg['html'];
					}
					print_r($mails);
					break;
				case 'partners':
					$divId = $msg['divId'];
					if (!$req->execute()) {
						print ("Ошибка MySQL ({$mysqli->errno})  {$mysqli->error}\n");
						exit;
					}
					$req->store_result();
					while ($req->fetch()) {
						if (!isset($mails[$partnerId][$reqId]))
							$mails[$partnerId][$reqId] = array('text' => '', 'html' => '');
						$mails[$partnerId][$reqId]['text'] .= $msg['text'];
						$mails[$partnerId][$reqId]['html'] .= $msg['html'];
					}
					break;
			}
		}
	}
}

$req->close();

foreach ($mails as $uid => $requests) {
	print "----------------------\n";
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
		print "{$users[$uid]['email']} - {$users[$uid]['name']} - {$reqId}\n";
	}	
}
 */
?>