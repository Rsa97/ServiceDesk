<?php
  include "../config/db.php";
  include "../config/files.php";
  
  $requests = array('getDivisions', 'getServices', 'newRequest', 'help');
  $formats = array('json', 'xml');

  function formatDateTime($date, $dayStart, $daysecs, $sql) {
  	preg_match('~(\d\d\d\d)-(\d\d)-(\d\d)~', $date, $day);
  	$sec = $dayStart[3]+$daysecs%60;
  	$min = $dayStart[2]+($daysecs/60)%60;
  	$hr = $dayStart[1]+$daysecs/3600;
  	if ($sec >= 60) {
		$sec -= 60;
		$min++;
  	}
  	if ($min >= 60) {
		$min -= 60;
		$hr++;
  	}
  	if ($sql == 0)
  		return sprintf("%02d.%02d.%04d %02d:%02d", $day[3], $day[2], $day[1], $hr, $min);
  	else
  		return sprintf("%04d-%02d-%02d %02d:%02d:%02d", $day[1], $day[2], $day[3], $hr, $min, $sec);
  }
 
  function xml_encode($mixed, $domElement=null, $DOMDocument=null) {
    if (is_null($DOMDocument)) {
        $DOMDocument =new DOMDocument;
        $DOMDocument->formatOutput = true;
        xml_encode($mixed, $DOMDocument, $DOMDocument);
        echo $DOMDocument->saveXML();
    }
    else {
        if (is_array($mixed)) {
            foreach ($mixed as $index => $mixedElement) {
                if (is_int($index)) {
                    if ($index === 0) {
                        $node = $domElement;
                    }
                    else {
                        $node = $DOMDocument->createElement($domElement->tagName);
                        $domElement->parentNode->appendChild($node);
                    }
                }
                else {
                    $plural = $DOMDocument->createElement($index);
                    $domElement->appendChild($plural);
                    $node = $plural;
                    if (!(rtrim($index, 's') === $index)) {
                        $singular = $DOMDocument->createElement(rtrim($index, 's'));
                        $plural->appendChild($singular);
                        $node = $singular;
                    }
                }
 
                xml_encode($mixedElement, $node, $DOMDocument);
            }
        }
        else {
            $domElement->appendChild($DOMDocument->createTextNode($mixed));
        }
    }
  }  

  function return_format($format, $answer) {
	switch ($format) {
		case 'xml':
			header('Content-Type: application/xml; charset=UTF-8');
			$dom = new DomDocument('1.0');
			$dom->formatOutput = true;
			$root = $dom->appendChild($dom->createElement('answer'));
			xml_encode($answer, $root, $dom);
			echo $dom->saveXML();
			break;
		case 'json':
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode(array('answer' => $answer));
			break;
	} 
  }
  
  function calcTime($div, $serv, $sla, $sql) {
  	global $mysqli, $format; 
  	$date = date_create();
  	$created = date_format($date, 'Y-m-d');
  	$dayStart = date_format($date, 'H:i:s');
  	$req = $mysqli->prepare("SELECT DISTINCT `dss`.`toReact`, `dss`.`toFix`, `dss`.`toRepair`, `dss`.`startDayTime`, `dss`.`endDayTime`, `wc`.`date` ".
  								"FROM `contractDivisions` AS `cd` ".
  								"LEFT JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
  								"LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_id` = `c`.`id` ".
  								"LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `c`.`id` AND `cd`.`type_id` = `dss`.`divType_id` ".
  								"LEFT JOIN `workCalendar` AS `wc` ON FIND_IN_SET(`wc`.`type`, `dss`.`dayType`) ".
  								"WHERE `cd`.`id` = ? AND `dss`.`service_id` = ? AND `dss`.`slaLevel` = ? AND `wc`.`date` >= CURDATE() ".
								"ORDER BY `wc`.`date` ");
	if (!$mysqli->error)
	  	$req->bind_param('iis', $div, $serv, $sla);
	if (!$mysqli->error)
	  	$req->bind_result($toReact, $toFix, $toRepair, $startDayTime, $endDayTime, $day);
	if (!$mysqli->error)
	  	$req->execute();
	if ($mysqli->error) {
		return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
   		exit;
	}
	$secs = 0;
  	$okReact = 0;
  	$okFix = 0;
  	$okRepair = 0;
  	$reactBefore = '';
  	$fixBefore = '';
  	$repairBefore = '';
  	while ($req->fetch()) {
		if ($created != $day || $dayStart < $startDayTime)
	  		$dayStart = $startDayTime;
		if ($endDayTime == '00:00:00')
	  		$endDayTime = '24:00:00';
		preg_match('~(\d\d):(\d\d):(\d\d)~', $dayStart, $start);
		preg_match('~(\d\d):(\d\d):(\d\d)~', $endDayTime, $end);
		$daysecs = $end[1]*3600+$end[2]*60+$end[3]-$start[1]*3600-$start[2]*60-$start[3];
		if ($secs+$daysecs > $toReact*60 && $okReact == 0) {
	  		$reactBefore = formatDateTime($day, $start, $toReact*60-$secs, $sql);
	  		$okReact = 1;
		}
		if ($secs+$daysecs > $toFix*60 && $okFix == 0) {
	  		$fixBefore = formatDateTime($day, $start, $toFix*60-$secs, $sql);
	  		$okFix = 1;
		}
		if ($secs+$daysecs > $toRepair*60 && $okRepair == 0) {
	  		$repairBefore = formatDateTime($day, $start, $toRepair*60-$secs, $sql);
	  		$okRepair = 1;
		}
		if ($okReact == 1 && $okFix == 1 && $okRepair == 1)
	  		break;
		$secs += $daysecs;
		$dayStart = '00:00:00';
  	}
  	$req->close();
  	return array('createdAt' => ($sql == 0 ? date_format($date, 'd.m.Y H:i') : date_format($date, 'Y-m-d H:i:s')), 'reactBefore' => $reactBefore, 
  				 'fixBefore' => $fixBefore, 'repairBefore' => $repairBefore, 'toReact' => $toReact, 'toFix' => $toFix, 'toRepair' => $toRepair);
}
  
  
  $format = ((isset($_REQUEST['fmt']) && in_array($_REQUEST['fmt'], $formats)) ? $_REQUEST['fmt'] : 'json');  
  if (!isset($_REQUEST['request']) || !in_array($_REQUEST['request'], $requests)) {
  	return_format($format, array('state' => 'error', 'text' => 'Неизвестный метод "'.(isset($_REQUEST['request']) ? $_REQUEST['request'] : '').'"'));
	exit;
  }
  if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
  	return_format($format, array('state' => 'error', 'text' => 'Доступ к api возможен только по протоколу HTTPS'));
	exit;
  }
  
  if ($_REQUEST['request'] != 'help') {
  	if (!isset($_REQUEST['token'])) {
  		return_format($format, array('state' => 'error', 'text' => 'Не указан токен, для получения обратитесь к администратору'));
		exit;
  	}

  	$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  	if ($mysqli->connect_error) {
    	return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    	exit;
  	}
  	$mysqli->query("SET NAMES utf8");
  
  	$req = $mysqli->prepare('SELECT `id` FROM `users` WHERE `token` = ?');
  	if (!$mysqli->error)
  		$req->bind_param('s', $_REQUEST['token']);
  	if (!$mysqli->error)
  	  	$req->bind_result($userId);
  	if (!$mysqli->error)
  	  	$req->execute();
  	if ($mysqli->error) {
		return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    	exit;
  	}
  	if (!$req->fetch()) {
		return_format($format, array('state' => 'error', 'text' => 'Токен недействителен, для получения обратитесь к администратору'));
    	exit;
  	}
  	$req->close();
  }

  switch($_REQUEST['request']) {
	case 'getDivisions':
		if (!isset($_REQUEST['agrNumber'])) {
			return_format($format, array('state' => 'error', 'text' => 'Не указан номер договора'));
    		exit;
		}
		$req = $mysqli->prepare('SELECT `cd`.`id`, `cd`.`name` '.
									'FROM `contracts` AS `c` '.
									'JOIN `contractDivisions` AS `cd` ON `cd`.`contracts_id` = `c`.`id` '.
									'WHERE `c`.`number` = ? AND `cd`.`isDisabled` = 0');
		if (!$mysqli->error)
  	  		$req->bind_param('s', $_REQUEST['agrNumber']);
		if (!$mysqli->error)
  	  		$req->bind_result($divId, $divName);
		if (!$mysqli->error)
  	  		$req->execute();
		if ($mysqli->error) {
			return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    		exit;
  		}
		$divs = array();
		$divs1 = array();
  		while ($req->fetch())
  			$divs[] = array('id' => $divId, 'name' => $divName);
		$req->close();
		if (count($divs) == 0)
			return_format($format, array('state' => 'error', 'text' => "Договор '{$_REQUEST['agrNumber']}' не найден"));
		else
			return_format($format, array('state' => 'ok', 'divisions' => $divs));
		break;
	case 'getServices':
		if (!isset($_REQUEST['divId'])) {
			return_format($format, array('state' => 'error', 'text' => 'Не указан идентификатор филиала'));
    		exit;
		}
		$req = $mysqli->prepare("SELECT `s`.`id`, `s`.`name`, `s`.`shortname`, ". 
										"GROUP_CONCAT(DISTINCT `dss`.`slaLevel` SEPARATOR ',') AS `sla` ".
									"FROM `contractDivisions` AS `cd` ".
    								"JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `cd`.`contracts_id` ". 
										"AND `dss`.`divType_id` = `cd`.`type_id` ".
    								"JOIN `services` AS `s` ON `s`.`id` = `dss`.`service_id`".
    								"WHERE `cd`.`id` = ? ".
									"GROUP BY `s`.`id`");
		if (!$mysqli->error)
  	  		$req->bind_param('i', $_REQUEST['divId']);
		if (!$mysqli->error)
  	  		$req->bind_result($servId, $servName, $servCode, $sla);
		if (!$mysqli->error)
  	  		$req->execute();
		if ($mysqli->error) {
			return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    		exit;
  		}
		$divs = array();
		$divs1 = array();
  		while ($req->fetch()) {
  			$divs[] = array('id' => $servId, 'name' => $servName, 'code' => $servCode, 'sla' => $sla);
  		}
		$req->close();
		if (count($divs) == 0)
			return_format($format, array('state' => 'error', 'text' => "Филиал не найден"));
		else
			return_format($format, array('state' => 'ok', 'services' => $divs));
		break;
	case 'newRequest':
		if (!isset($_REQUEST['divId']) || ($divId = intval($_REQUEST['divId'])) != $_REQUEST['divId'] ||
			!isset($_REQUEST['serviceId']) || ($serviceId = intval($_REQUEST['serviceId'])) != $_REQUEST['serviceId'] ||
			!isset($_REQUEST['sla']) || 
			!isset($_REQUEST['problem']) || $_REQUEST['problem'] == '') {
			return_format($format, array('state' => 'error', 'text' => 'Не указаны обязательные параметры'));
    		exit;
		}

		// Контроль параметров
		$req = $mysqli->prepare("SELECT COUNT(*) ".
								"FROM `contractDivisions` AS `cd` ".
								"JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `cd`.`contracts_id` ".
									"AND `dss`.`divType_id` = `cd`.`type_id` ".
								"WHERE `cd`.`id` = ? ".
									"AND `dss`.`service_id` = ? AND `dss`.`slaLevel` = ? ".
									"AND `cd`.`isDisabled` = 0");
		if (!$mysqli->error)
  	  		$req->bind_param('iis', $divId, $serviceId, $_REQUEST['sla']);
		if (!$mysqli->error)
  	  		$req->bind_result($ok);
		if (!$mysqli->error)
  	  		$req->execute();
		if ($mysqli->error) {
			return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    		exit;
  		}
		$req->fetch();
		if ($ok == 0) {
			return_format($format, array('state' => 'error', 'text' => 'Недопустимая комбинация параметров'));
    		exit;
  		}
		$req->close();
				
		// Расчёт времени
		$time = calcTime($divId, $serviceId, $_REQUEST['sla'], 1);
		
		// Запись заявки
		$req = $mysqli->prepare("INSERT INTO `request` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
														"`currentState`, `contactPersons_id`, `contractDivisions_id`, `slaLevel`, ".
														"`equipment_id`, `service_id`, `toReact`, `toFix`, `toRepair`) ".
														"VALUES (?, ?, ?, ?, ?, 'received', 1, ?, ?, NULL, ?, ?, ?, ?)");
		$req->bind_param('sssssisiiii', $_REQUEST['problem'], $time['createdAt'], $time['reactBefore'], $time['fixBefore'], 
										  $time['repairBefore'], $divId, $_REQUEST['sla'], $serviceId, $time['toReact'], 
										  $time['toFix'], $time['toRepair']);
		if (!$req->execute() || $mysqli->affected_rows == 0) {
			return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    		exit;
		}
		$req->close();
		$id = $mysqli->insert_id;
        $req = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `request_id`, `users_id`) VALUES('open', ?, ?)");
		if (!$mysqli->error)
	        $req->bind_param('ii', $id, $userId);
		if (!$req->execute() || $mysqli->affected_rows == 0) {
			return_format($format, array('state' => 'error', 'text' => 'Внутренняя ошибка сервера (MySQL)'));
    		exit;
		}
		$req->close();
		return_format($format, array('state' => 'ok'));
		break;
	case 'help':
		header('Content-Type: text/html; charset=UTF-8');
		echo "<html><body>";
		echo "<h1>API</h1>";
		echo "<h3>Общие параметры:</h3>";
		echo "<p><b>fmt=[xml | json]</b> - формат ответа";
		echo "<p><b>token=&lt;токен&gt;</b> - токен авторизации";
		echo "<p>Ответ при ошибке:<pre>";
		echo "&lt;answer&gt;\n";
		echo " &lt;state&gt;ok&lt;/state&gt;\n";
		echo " &lt;text&gt;Договор '5983/3' не найден&lt;/text&gt;\n";
		echo "&lt;/answer&gt;\n\n";
		echo '{"answer":{"state":"error","text":"\u0414\u043e\u0433\u043e\u0432\u043e\u0440 \'5983\/3\' \u043d\u0435 \u043d\u0430\u0439\u0434\u0435\u043d"}}';
		echo "<h3>Филиалы по договору:</h3>";
		echo "<p>/api/getDivisions";
		echo "<p><b>agrNumber=&lt;номер договора&gt;</b> - номер договора с клиентом";
		echo "<p>Пример:<br>https://sd-dev.sodrk.ru/api/getDivisions&fmt=xml&token=2f251f8795f385205cbb256dd259740c5d7e3999&agrNumber=5983/3Р";
		echo "<p>Ответ xml:<pre>";
		echo "&lt;answer&gt;\n";
		echo " &lt;state&gt;ok&lt;/state&gt;\n";
		echo " &lt;divisions&gt;\n";
		echo "  &lt;division&gt;\n";
		echo "   &lt;id&gt;58&lt;/id&gt;\n";
		echo "   &lt;name&gt;ГБУЗ РК \"Патологоанатомическое бюро\"&lt;/name&gt;\n";
		echo "  &lt;/division&gt;\n";
		echo " &lt;/divisions&gt;\n";
		echo "&lt;/answer&gt;\n";
		echo "</pre><p>Ответ json:<br>";
		echo '{"answer":{"state":"ok","divisions":[{"id":58,"name":"\u0413\u0411\u0423\u0417 \u0420\u041a \"\u041f\u0430\u0442\u043e\u043b\u043e\u0433\u043e\u0430\u043d\u0430\u0442\u043e\u043c\u0438\u0447\u0435\u0441\u043a\u043e\u0435 \u0431\u044e\u0440\u043e\""}]}}<br>';
		echo "<h3>Услуги по филиалу:</h3>";
		echo "<p>/api/getServices";
		echo "<p><b>divId=&lt;id филиала&gt;</b> - идентификатор филиала";
		echo "<p>Пример:<br>https://sd-dev.sodrk.ru/api/getDivisions&fmt=xml&token=2f251f8795f385205cbb256dd259740c5d7e3999&divId=58";
		echo "<p>Ответ xml:<pre>";
		echo "&lt;answer&gt;\n";
		echo " &lt;state&gt;ok&lt;/state&gt;\n";
		echo "  &lt;services&gt;\n";
		echo "   &lt;service&gt;\n";
		echo "    &lt;id&gt;14&lt;/id&gt;\n";
		echo "    &lt;name&gt;Рабочее место пользователя&lt;/name&gt;\n";
		echo "    &lt;code&gt;РМП&lt;/code&gt;\n";
		echo "    &lt;sla&gt;medium&lt;/sla&gt;\n";
		echo "   &lt;/service&gt;\n";
		echo "   &lt;service&gt;\n";
		echo "    &lt;id&gt;15&lt;/id&gt;\n";
		echo "    &lt;name&gt;Печать, сканирование, копирование&lt;/name&gt;\n";
		echo "    &lt;code&gt;ПЕЧАТЬ&lt;/code&gt;\n";
		echo "    &lt;sla&gt;medium&lt;/sla&gt;\n";
		echo "   &lt;/service&gt;\n";
		echo "  &lt;/services&gt;\n";
		echo "&lt;/answer&gt;\n";
		echo "</pre><p>Ответ json:<br>";
		echo '{"answer":{"state":"ok","services":[{"id":14,"name":"\u0420\u0430\u0431\u043e\u0447\u0435\u0435 \u043c\u0435\u0441\u0442\u043e \u043f\u043e\u043b\u044c\u0437\u043e\u0432\u0430\u0442\u0435\u043b\u044f","code":"\u0420\u041c\u041f","sla":"medium"},{"id":15,"name":"\u041f\u0435\u0447\u0430\u0442\u044c, \u0441\u043a\u0430\u043d\u0438\u0440\u043e\u0432\u0430\u043d\u0438\u0435, \u043a\u043e\u043f\u0438\u0440\u043e\u0432\u0430\u043d\u0438\u0435","code":"\u041f\u0415\u0427\u0410\u0422\u042c","sla":"medium"}]}}<br>';
		echo "<p>Возможные значения sla - critical, high, medium, low";
		echo "<h3>Создание новой заявки:</h3>";
		echo "<p>/api/newRequest";
		echo "<p><b>divId=&lt;id филиала&gt;</b> - идентификатор филиала";
		echo "<p><b>serviceId=&lt;id сервиса&gt;</b> - идентификатор сервиса";
		echo "<p><b>sla=[critical | high | medium | low]</b> - уровень критичности";
		echo "<p><b>problem=&lt;описание&gt;</b> - описание проблемы";
		echo "<p>Пример:<br>https://sd-dev.sodrk.ru/api/getDivisions&fmt=xml&token=2f251f8795f385205cbb256dd259740c5d7e3999&divId=58&serviceId=14&sla=medium&problem=Всё пропало!!!!";
		echo "<p>Ответ xml:<pre>";
		echo "&lt;answer&gt;\n";
		echo " &lt;state&gt;ok&lt;/state&gt;\n";
		echo "&lt;/answer&gt;\n";
		echo "</pre><p>Ответ json:<br>";
		echo '{"answer":{"state":"ok"}}<br>';
		break;
  }
    
?>