<?php
  include "../config/db.php";
  include "../config/files.php";
  
  $slaLevels = array('critical' => 'Критический', 'high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий');
  
function sendJson($data) {
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($data);
}

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

function calcTime($div, $serv, $sla, $sql) {
  global $mysqli; 
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
  $req->bind_param('iis', $div, $serv, $sla);
  $req->bind_result($toReact, $toFix, $toRepair, $startDayTime, $endDayTime, $day);
  if (!$req->execute()) {
	return array('error' => 'Внутренняя ошибка сервера');
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

  session_start();
  if (!isset($_SESSION['user']) || !isset($_SESSION['filter'])) {
    sendJson(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }

/*  if (!isset($_REQUEST['srvNum']) || !preg_match('~^(\d+)~', $_REQUEST['srvNum'], $match)) {
    sendJson(array('error' => 'Ошибка в параметрах запроса'));
    exit;
  }

  $servNum = $match[1]; */
  
  if ($_SESSION['user']['rights'] == 'partner') {
    sendJson(array('error' => 'Недостаточно прав'));
    exit;
  }

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    sendJson(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $mysqli->query("SET NAMES utf8");

  // Читаем фильтр из сессии
  $rights = $_SESSION['user']['rights'];
  $userId =  $_SESSION['user']['myID'];
  $byUser = ($rights == 'client' ? 1 : 0);
  $byEngeneer = (($rights == 'engeneer' || $rights == 'partner' || $rights == 'operator') ? 1 : 0);
  $byActive = ($rights == 'admin' ? 0 : 1);

  $_SESSION['time'] = time();
  session_commit();
  
  if (!isset($_REQUEST['op'])) {
    sendJson(array('error' => 'Ошибка в параметрах'));
	exit;
  }
  if ($_REQUEST['op'] != 'divsList') {
	// Проверяем права
	if ($_REQUEST['op'] == 'changeEq' || $_REQUEST['op'] == 'isChangeAllowed') {
      if (isset($_REQUEST['n']) && preg_match('~(\d+)~', $_REQUEST['n'], $reqMatch) && $reqMatch[1] != 0) {
	    $req = $mysqli->prepare("SELECT COUNT(*) ".
	    						"FROM `request` AS `rq` ".
	    						"LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`id` = `rq`.`contractDivisions_id` ".
	    						"LEFT JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
    	                        "WHERE (? = 0 OR (`rq`.`currentState` = 'received' AND `rq`.`contactPersons_id` = ?)) ".
    	                        	"AND (? = 0 OR (`rq`.`currentState` IN ('accepted','fixed') AND `rq`.`engeneer_id` = ?)) ".
    	                        	"AND `rq`.`id` = ? ".
        	                		"AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`))");
	    $req->bind_param('iiiiii', $byUser, $userId, $byEngeneer, $userId, $reqMatch[1], $byActive);
	  } else {
		sendJson(array('error' => 'Ошибка в параметрах'));
	  	exit;
	  }
	} else {
      if (isset($_REQUEST['div']) && preg_match('~(\d+)~', $_REQUEST['div'], $divMatch) && $divMatch[1] != 0) {
	    $req = $mysqli->prepare("SELECT COUNT(*) ".
								"FROM `contractDivisions` AS `cd` ".
								"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivisions_id` = `cd`.`id` ".
								"LEFT JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
								"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `c`.`id` ".
    	                        "WHERE (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
    	                        	"AND `cd`.`id` = ? ".
        	                		"AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`))");
	    $req->bind_param('iiiii', $byUser, $userId, $userId, $divMatch[1], $byActive);
	  } else {
		sendJson(array('error' => 'Ошибка в параметрах'));
	  	exit;
	  }
	}
    $req->bind_result($ok);
	if (!$req->execute()) { 
	  sendJson(array('error' => 'Внутренняя ошибка сервера'));
	  exit;
	}
	$ok = 0;
	$req->fetch();
	$req->close();
	if ($_REQUEST['op'] == 'isChangeAllowed') {
	  sendJson(array('!lookServNum' => ($ok > 0 ? 1 : 0)));
	  exit;
	}
	if ($ok == 0) {
	  sendJson(array('error' => 'Ошибка в параметрах или недостаточно прав'));
	  exit;
	}
  }
  switch($_REQUEST['op']) {
	case 'divsList': 
	  $req = $mysqli->prepare("SELECT DISTINCT `ca`.`id`, `div`.`id`, `ca`.`name`, `div`.`name`, `n`.`num` ".
        	                    "FROM `contragents` AS `ca` ".
								"LEFT JOIN `contractDivisions` AS `div` ON `div`.`contragents_id` = `ca`.`id` ".
								"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivisions_id` = `div`.`id` ".
								"LEFT JOIN `contracts` AS `c` ON `ca`.`id` = `c`.`contragents_id` ".
								"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `c`.`id` ".
								"LEFT JOIN (SELECT `contracts_id`, COUNT(`contracts_id`) AS `num` ".
                                	        "FROM `contractDivisions` ".
                                            "GROUP BY `contracts_id`) ".
	                                "AS `n` ON `n`.`contracts_id` = `c`.`id` ".
    	                        "WHERE (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
        	                		"AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
                	            "ORDER BY `ca`.`name`, `div`.`name`");
 	  $req->bind_param('iiii', $byUser, $userId, $userId, $byActive);
	  $req->bind_result($contragentId, $divisionId, $contragentName, $divisionName, $divNum);
	  if (!$req->execute()) { 
		sendJson(array('error' => 'Внутренняя ошибка сервера'));
		exit;
	  }
	  $prevContragent = 0;
	  $divOk = 0;
	  $curDiv = "";
	  $byDiv = "";
	  $numDivs = 0;
	  while ($req->fetch()) {
		if ($contragentId != $prevContragent) {
		  $byDiv .= "<optgroup label='{$contragentName}'>";
		  $prevContragent = $contragentId;
		}
		if ($curDiv == $divisionId)
		  $divOk = 1;
		$byDiv .= "<option value='{$divisionId}'".($curDiv == $divisionId ? " selected" : "").">{$divisionName}";
		$numDivs++;
	  }
	  $req->close();
	  if (($numDivs > 1) && ($divOk == 0))
		$byDiv = "<option value='0' selected>Выберите организацию".$byDiv;
	  sendJson(array('division' => $byDiv));
	  break;
	case 'fillNewCard1':
	  $date = date_create();
	  $result = array(
			'_createdAt' => date_format($date, 'd.m.Y H:i'),
            '_createTime' => date_format($date, 'm/d/Y H:i'));
	// Получаем список услуг
  	  $req = $mysqli->prepare("SELECT DISTINCT `srv`.`id`, `srv`.`name` ".
  								"FROM `contractDivisions` AS `cd` ".
  								"JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `cd`.`contracts_id` AND `dss`.`divType_id` = `cd`.`type_id` ".
  								"JOIN `services` AS `srv` ON `srv`.`id` = `dss`.`service_id` ".
  								"WHERE `cd`.`id` = ? ".
  								"ORDER BY `srv`.`name`");
	  $req->bind_param('i', $divMatch[1]);
	  $req->bind_result($servId, $servName);
	  if (!$req->execute()) { 
		sendJson(array('error' => 'Внутренняя ошибка сервера'));
		exit;
	  }
	  $services = "";
	  while ($req->fetch())
	  	$services .= "<option value='{$servId}'>{$servName}";
	  if ($services == "")
	  	$services = "<option value='0'>";
	  $result['service'] = $services;
	  $req->close();
	// Получаем список ответственных лиц
	  $req = $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, `u`.`address` ".
								"FROM `users` AS `u` ".
								"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`users_id` = `u`.`id` ".
								"WHERE `ucd`.`contractDivisions_id` = ? ".
								  "AND `u`.`rights` = 'client' ".
							  "UNION SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, `u`.`address` ".
								"FROM `users` AS `u` ".
								"LEFT JOIN `userContracts` AS `uc` ON `uc`.`users_id` = `u`.`id` ".
								"LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`contracts_id` = `uc`.`contracts_id` ".
								"WHERE `cd`.`id` = ? ".
								  "AND `u`.`rights` = 'client'"); 
	  $req->bind_param('ii', $divMatch[1], $divMatch[1]);
	  $req->bind_result($contId, $contGN, $contLN, $contMN, $contEmail, $contPhone, $contAddress);
	  if (!$req->execute()) { 
 		sendJson(array('error' => 'Внутренняя ошибка сервера'));
    	exit;
	  }
	  $contacts = '';
	  $idOk = 0;
	  $num = 0;
	  while ($req->fetch()) {
   		$contacts .= "<option value='{$contId}' data-email='".htmlspecialchars($contEmail)."' data-phone='".htmlspecialchars($contPhone).
                     "' data-address='".htmlspecialchars($contAddress)."'";
		if ($userId == $contId) {
	 	  $contacts .= " selected";
		  $idOk = 1;
	  	}
       	$contacts .= ">{$contLN} {$contGN} {$contMN}";
       	$num++;
	  }
	  if ($idOk == 0) {
	  	if ($contacts == "")
  		  $contacts = "<option value='0' data-email='' data-phone='' data-address=''>";
		else if ($num > 1)
  		  $contacts = "<option value='0' data-email='' data-phone='' data-address=''>Выберите ответственного".$contacts;
	  }
  	  $req->close();
	  $result['contact'] = $contacts;
	  sendJson($result);
	  break; 
	case 'fillNewCard2':
		$result = array();
		if (isset($_REQUEST['serv']) && preg_match('~(\d+)~', $_REQUEST['serv'], $servMatch) && $servMatch[1] != 0) {
		// Получаем список уровней критичности для услуги
		  $req = $mysqli->prepare("SELECT DISTINCT `dss`.`slaLevel`, `dss`.`isDefault` ".
  		  							"FROM `contractDivisions` AS `cd` ".
  		  							"LEFT JOIN `contracts` AS `c` ON `c`.`id` = `cd`.`contracts_id` ".
  		  							"LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `c`.`id` AND `cd`.`type_id` = `dss`.`divType_id` ".
  		  							"WHERE `cd`.`id` = ? AND `dss`.`service_id` = ? ".
									"ORDER BY `dss`.`slaLevel` ");
		  $req->bind_param('ii', $divMatch[1], $servMatch[1]);
		  $req->bind_result($slaLevel, $isDefault);
		  if (!$req->execute()) { 
			sendJson(array('error' => 'Внутренняя ошибка сервера'));
			exit;
		  }
		  $levels = "";
		  $level = "";
		  $isSel = 0;
  		  while ($req->fetch()) {
  		  	$levels .= $level;
  		  	if ($isDefault == 1)
			  $isSel = 1;
  		  	$level = "<option value='{$slaLevel}'".($isDefault == 1 ? " selected" : "").">{$slaLevels[$slaLevel]}";
		  }
		  $levels .= "<option value='{$slaLevel}'".($isDefault != 1 && $isSel == 1 ? "" : " selected").">{$slaLevels[$slaLevel]}";
		  $result['level'] = $levels;
		  sendJson($result);
		} else 
		  sendJson(array('error' => 'Ошибка в параметрах'));
		break;
	  case 'calcTime':
		if (isset($_REQUEST['serv']) && preg_match('~(\d+)~', $_REQUEST['serv'], $servMatch) && $servMatch[1] != 0 &&
			isset($_REQUEST['sla']) && isset($slaLevels[$_REQUEST['sla']])) {
		  $ret = calcTime($divMatch[1], $servMatch[1], $_REQUEST['sla'], 0);
		  if (isset($ret['error'])) {
		  	sendJson(array('error' => $ret['error']));
		  	exit;
		  }
		  sendJson(array('_createdAt' => $ret['createdAt'], '_repairBefore' => $ret['repairBefore']));
		} else 
		  sendJson(array('error' => 'Ошибка в параметрах'));
		break;
	  case 'getEqData':
		if (isset($_REQUEST['num']) && preg_match('~(\S+)~', $_REQUEST['num'], $numMatch) && $numMatch[1] != '') {
		  $req = $mysqli->prepare("SELECT `eq`.`serialNumber`, `em`.`name`, `emfg`.`name`, `eqst`.`description`, `eqt`.`name` ".
									"FROM `equipment` AS `eq` ".
									"LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
									"LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `em`.`equipmentManufacturers_id` ".
									"LEFT JOIN `equipmentSubTypes` AS `eqst` ON `eqst`.`id` = `em`.`equipmentSubTypes_id` ".
									"LEFT JOIN `equipmentTypes` AS `eqt` ON `eqt`.`id` = `eqst`.`equipmentTypes_id` ".
									"WHERE `eq`.`contractDivisions_id` = ? ".
										"AND `eq`.`onService` = 1 ".
										"AND `eq`.`serviceNumber` = ? ");
		  $req->bind_param('is', $divMatch[1], $numMatch[1]);
		  $req->bind_result($eqSerial, $eqModel, $eqMfg, $eqSubType, $eqType);
		  if (!$req->execute()) {
			sendJson(array('error' => 'Внутренняя ошибка сервера'));
			exit;
		  }
		  if ($req->fetch()) {
			$result = array('_SN' => $eqSerial,
							'_eqType' => "{$eqType} / {$eqSubType}",
							'_manufacturer' => $eqMfg,
							'_model' => $eqModel);
			sendJson($result);
		  } else 
		    sendJson(array('error' => 'Ошибка в параметрах'));
		  $req->close();
		} else 
		  sendJson(array('error' => 'Ошибка в параметрах'));
		break;
	case 'newCard':
	  if (isset($_REQUEST['srvNum']) &&  isset($_REQUEST['problem']) && $_REQUEST['problem'] != '' &&
	      isset($_REQUEST['serv']) && preg_match('~(\d+)~', $_REQUEST['serv'], $servMatch) &&
	      isset($_REQUEST['sla']) && isset($slaLevels[$_REQUEST['sla']]) &&
	      isset($_REQUEST['contact']) && preg_match('~(\d+)~', $_REQUEST['contact'], $contMatch)) {
	  // Проверяем корректность данных
		$req = $mysqli->prepare("SELECT COUNT(*) ".
								"FROM `contractDivisions` AS `cd` ".
								"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivisions_id` = `cd`.`id` ".
								"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `cd`.`contracts_id` ".
								"LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `cd`.`contracts_id` ".
									"AND `dss`.`divType_id` = `cd`.`type_id` ".
								"LEFT JOIN `equipment` AS `eq` ON `eq`.`contractDivisions_id` = `cd`.`id` ".
								"WHERE (`ucd`.`users_id` = ? OR `uc`.`users_id` = ?) AND `cd`.`id` = ? ".
									"AND `dss`.`service_id` = ? AND `dss`.`slaLevel` = ? ".
									"AND (? = '' OR (`eq`.`serviceNumber` = ? AND `eq`.`onService` = 1))");
		$req->bind_param('iiiisss', $contMatch[1], $contMatch[1], $divMatch[1], $servMatch[1], $_REQUEST['sla'], $_REQUEST['srvNum'], $_REQUEST['srvNum']);
		$req->bind_result($ok);
		$ok = 0;
		if (!$req->execute()) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера'));
		  exit;
		}
		$req->fetch();
		$req->close();
		if ($ok == 0) {
		  sendJson(array('error' => 'Ошибка в параметрах'));
		  exit;
		}
	// Считаем время
		$time = calcTime($divMatch[1], $servMatch[1], $_REQUEST['sla'], 1);
		$req = $mysqli->prepare("INSERT INTO `request` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
														"`currentState`, `contactPersons_id`, `contractDivisions_id`, `slaLevel`, ".
														"`equipment_id`, `service_id`, `toReact`, `toFix`, `toRepair`) ".
														"VALUES (?, ?, ?, ?, ?, 'received', ?, ?, ?, IF(? = '',NULL,?), ?, ?, ?, ?)");
		$req->bind_param('sssssiisssiiii', $_REQUEST['problem'], $time['createdAt'], $time['reactBefore'], $time['fixBefore'], 
										$time['repairBefore'], $contMatch[1], $divMatch[1], $_REQUEST['sla'], $_REQUEST['srvNum'], 
										$_REQUEST['srvNum'], $servMatch[1], $time['toReact'], $time['toFix'], $time['toRepair']);
		if (!$req->execute() || $mysqli->affected_rows == 0) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера '));
		  exit;
		}
		$req->close();
		$id = $mysqli->insert_id;
        $req = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `request_id`, `users_id`) VALUES('open', ?, ?)");
        $req->bind_param('ii', $id, $userId);
		if (!$req->execute() || $mysqli->affected_rows == 0) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера '));
		  exit;
		}
		$req->close();
		sendJson(array('ok' => 'ok'));
	  } else {
	  	sendJson(array('error' => 'Ошибка в параметрах'));
	  }
	  break;
	case 'changeEq':
	  if (isset($_REQUEST['srvNum']) && $_REQUEST['srvNum'] != '') {
	  	$req1 = $mysqli->prepare("SELECT `rq`.`equipment_id`, `eq`.`serialNumber`, `em`.`name`, `emfg`.`name` ". 
	  							  "FROM `request` AS `rq` ".
								  "LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id` ".
								  "LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
								  "LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `em`.`equipmentManufacturers_id` ".
								  "WHERE `rq`.`id` = ?");
		$req1->bind_param('i', $reqMatch[1]);
		$req1->bind_result($servNum, $SN, $model, $mfg);
		if (!$req1->execute()) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера'));
		  exit;
		}
		$req1->store_result();
		$req1->fetch();
		$from = ($servNum == '' ? 'не указано' : "{$servNum} - {$mfg} ${model} (SN:{$SN})");
		$req = $mysqli->prepare("UPDATE `request` SET `equipment_id` = ? WHERE `id` = ?");
		$req->bind_param('si', $_REQUEST['srvNum'], $reqMatch[1]);
		if (!$req->execute() || $mysqli->affected_rows == 0) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера или ошибка в параметрах'));
		  exit;
		}
		$req->close();
		$req1->bind_param('i', $reqMatch[1]);
		if (!$req1->execute()) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера'));
		  exit;
		}
		$req1->fetch();
		$to = ($servNum == '' ? 'не указано' : "{$servNum} - {$mfg} ${model} (SN:{$SN})");
		$text = "Было: {$from}\nСтало: {$to}";
		$req1->close();
		$req = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `text`, `request_id`, `users_id`) VALUES('eqChange', ?, ?, ?)");
        $req->bind_param('sii', $text, $reqMatch[1], $userId);
		if (!$req->execute() || $mysqli->affected_rows == 0) {
		  sendJson(array('error' => 'Внутренняя ошибка сервера '.$mysqli->error));
		  exit;
		}
		$req->close();
		sendJson(array('ok' => 'ok'));
	  } else {
	  	sendJson(array('error' => 'Ошибка в параметрах'));
	  }
	default:
	  break;
  }
?>
