<?php
  include "../config/db.php";
  include "../config/files.php";

$allowedOps = array('client' => array('Cancel', 'Close', 'UnClose'),
                    'operator' => array('Cancel', 'Close', 'UnClose', 'DoNow'),
                    'engeneer' => array('Accept', 'Cancel', 'Fixed', 'Repaired', 'Wait', 'DoNow'),
                    'admin' => array('Accept', 'Cancel', 'Fixed', 'Repaired', 'Wait', 'Close', 'UnClose', 'UnCancel', 'DoNow'),
                    'partner' => array('Accept', 'Fixed', 'Repaired', 'Wait'));
					
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
					
function sendJson($data) {
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($data);
}

  session_start();

// Определение переходов состояний 
  $allowedTrans = array('Accept' => "'received'", 
                        'Cancel' => "'received','accepted'",
                        'Fixed' => "'accepted'",
                        'Repaired' => "'accepted','fixed'",
                        'Wait' => "'accepted','fixed'",
                        'Close' => "'repaired'",
                        'UnClose' => "'repaired'",
                        'UnCancel' => "'canceled'",
						'DoNow' => "");
  switch ($_SESSION['user']['rights']) {
    case 'engeneer':
      $allowedTrans['Cancel'] = "'received'";
      break;
  }

  if (!isset($_SESSION['user']) || !isset($_SESSION['filter'])) {
    sendJson(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }

  if (!isset($_REQUEST['list']) || !isset($allowedTrans[$_REQUEST['op']]) || !isset($_REQUEST['op'])) {
    sendJson(array('error' => 'Ошибка в передаче параметров.'));
    exit;
  }

  if (!in_array($_REQUEST['op'], $allowedOps[$_SESSION['user']['rights']])) {
    sendJson(array('error' => 'Недостаточно прав.'));
    exit;
  }

// Проверка наличия дополнительных параметров
  $err = '';
  $onWait = 1;
  switch ($_REQUEST['op']) {
    case 'Wait':
      $onWait = 0;
    case 'Cancel':
    case 'UnClose':
    case 'UnCancel':
      if (!isset($_REQUEST['cause']) || ($cause = trim($_REQUEST['cause'])) == '')
        $err = 'Ошибка в передаче параметров.';
      break;
	case 'Repaired':
	  if (!isset($_REQUEST['solProblem'], $_REQUEST['sol'], $_REQUEST['solRecomend'])) 
        $err = 'Ошибка в передаче параметров.';
      break;
  }
  if ($err != '') {
    sendJson(array('error' => $err));
    exit;
  }

  $nums = array();
  foreach (explode(',', $_REQUEST['list']) as $num)
    if (preg_match('~^t(\d+)~', $num, $match))
      $nums[] = $match[1];
  if (count($nums) == 0) {
    sendJson(array('ok' => 'ok'));
    exit;
  }
  $list = implode(',', $nums);

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    sendJson(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $mysqli->query("SET NAMES utf8");

  // Читаем фильтр из сессии или по умолчанию
  $divFilter = $_SESSION['filter']['divFilter'];
  $byCntrAgent = $_SESSION['filter']['byCntrAgent'];
  $byDiv = $_SESSION['filter']['byDiv'];
  $srvFilter = $_SESSION['filter']['srvFilter'];
  $byService = $_SESSION['filter']['srvFilter'];
  $onlyMy = $_SESSION['filter']['onlyMy'];
  $rights = $_SESSION['user']['rights'];
  $userId =  $_SESSION['user']['myID'];
  $partnerId = $_SESSION['user']['partner'];
  $byClient = ($rights == 'client' ? 1 : 0);
  $byPartner = ($rights == 'partner' ? 1 : 0);
  $byContrTime = ($rights == 'admin' ? 0 : 1);

  $_SESSION['time'] = time();
  session_commit();


  if ($_REQUEST['op'] == 'DoNow') {
	$req = $mysqli->prepare(
    	   "SELECT DISTINCT `pr`.`id`, `pr`.`contractDivisions_id`, `pr`.`service_id`, `pr`.`slaLevel`, `pr`.`problem`, `u`.`users_id`, ".
    	   					" `div`.`addProblem`, `cu`.`users_id` ".
        	"FROM `plannedRequest` AS `pr` ".
            	"LEFT JOIN `contractDivisions` AS `div` ON `pr`.`contractDivisions_id` = `div`.`id` ".
            	"LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            	"LEFT JOIN ( ".
            	  "	SELECT `contractDivisions_id`, `users_id` FROM `userContractDivisions` GROUP BY `contractDivisions_id` ".
            	") AS `u` ON `u`.`contractDivisions_id` = `div`.`id` ".
            	"LEFT JOIN ( ".
            	  "	SELECT `contracts_id`, `users_id` FROM `userContracts` GROUP BY `contracts_id` ".
            	") AS `cu` ON `cu`.`contracts_id` = `c`.`id` ".
          	"WHERE `pr`.`id` IN ({$list}) ".
          		"AND `pr`.`nextDate` <= DATE_ADD(NOW(), INTERVAL `pr`.`preStart` DAY) ".
            	"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)");
	$req->bind_result($id, $divId, $srvId, $slaLevel, $problem, $clientId, $divProblem, $contUser);
	$req1 = $mysqli->prepare(
		"INSERT INTO `request` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
				"`currentState`, `contactPersons_id`, `contractDivisions_id`, `slaLevel`, ".
				"`equipment_id`, `service_id`, `toReact`, `toFix`, `toRepair`) ".
			"VALUES (?, ?, ?, ?, ?, 'received', ?, ?, ?, NULL, ?, ?, ?, ?)");
	$req2 = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `request_id`, `users_id`) VALUES('open', ?, ?)");
    $req2->bind_param('ii', $reqId, $userId);
    $req3 = $mysqli->prepare("UPDATE `plannedRequest` SET `nextDate` = `nextDate` + INTERVAL `intervalYears` YEAR + ".
    								"INTERVAL `intervalMonths` MONTH + INTERVAL `intervalWeeks` WEEK + INTERVAL `intervalDays` DAY ".
									"WHERE `id` = ?");
	$req3->bind_param('i', $id);
	$req4 = $mysqli->prepare("UPDATE `contractDivisions` SET `addProblem` = '' WHERE `id` = ?"); 
	$req4->bind_param('i', $divId);
		if (!$req->execute()) { 
    	sendJson(array('error' => 'Внутренняя ошибка сервера'));
		exit;
    }
	$req->store_result();
	while ($req->fetch()) {
		if ($clientId == '')
			$clientId = $contUser;
		if ($clientId != '') {
			$time = calcTime($divId, $srvId, $slaLevel, 1);
			$mysqli->query("START TRANSACTION");
			$req1->bind_param('sssssiisiiii', $problem, $time['createdAt'], $time['reactBefore'], $time['fixBefore'], 
										$time['repairBefore'], $clientId, $divId, $slaLevel, $srvId, 
										$time['toReact'], $time['toFix'], $time['toRepair']);
			$problem .= "\n".$divProblem;
			if (!$req1->execute()) {
    			sendJson(array('error' => 'Внутренняя ошибка сервера '.$mysqli->error));
				$mysqli->query("ROLLBACK");
				exit;
    		}
    		$reqId = $mysqli->insert_id;
			if (!$req2->execute() || !$req3->execute() || !$req4->execute()) {
				$mysqli->query("ROLLBACK");
    			sendJson(array('error' => 'Внутренняя ошибка сервера'));
				exit;
    		}
			$mysqli->query("COMMIT");
		}
	}
    sendJson(array('ok' => 'ok'));
	exit;
  }

  // Получаем список заявок с проверкой прав доступа
  $req = $mysqli->prepare(
       "SELECT DISTINCT `rq`.`id`, `rq`.`onWait`, `rq`.`currentState` ".
          "FROM `request` AS `rq` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            "LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivisions_id` = `rq`.`contractDivisions_id` ".
            "LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `div`.`contracts_id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `rq`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
          "WHERE `rq`.`id` IN ({$list}) ".
            "AND `rq`.`currentState` IN ({$allowedTrans[$_REQUEST['op']]}) ".
            "AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` OR `rq`.`currentState` NOT IN ('closed', 'canceled')) ".
            "AND (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
            "AND (? = 0 OR `ac`.`partner_id` = ?) ".
            "AND (? = 0 OR `rq`.`onWait` = 0)");
  	$req->bind_param('iiiiii', $byClient, $userId, $userId, $byPartner, $partnerId, $onWait);
  	$req->bind_result($id, $wait, $curState);
  if (!$req->execute()) { 
    sendJson(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $reqList = array();
  while ($req->fetch()) {
    $reqList[] = $id;
  }
  $req->close();

  if (count($reqList) == 0) {
    sendJson(array('ok' => 'ok'));
	$mysqli->close();
    exit;
  }

  $reqs = implode(',', $reqList);

  switch ($_REQUEST['op']) {
    case 'Accept':
      $events = array();
      foreach ($reqList as $reqId)
        $events[] = "(NOW(), 'changeState', 'accepted', {$reqId}, {$userId})";
      $eventList = implode(',', $events);
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = 'accepted', `stateChangeAt` = NOW(), `reactedAt` = NOW(), `engeneer_id` = {$userId} WHERE `id` IN ({$reqs})");
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`) VALUES {$eventList}");
      break;
    case 'Cancel':
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = 'canceled', `stateChangeAt` = NOW() WHERE `id` = ?");
      $query1->bind_param('i', $reqList[0]);
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`, `text`) VALUES (NOW(), 'changeState', 'canceled', ?, ?, ?)");
      $query2->bind_param('iis', $reqList[0], $userId, $cause);
      break;
    case 'Fixed':
      $events = array();
      foreach ($reqList as $reqId)
        $events[] = "(NOW(), 'changeState', 'fixed', {$reqId}, {$userId})";
      $eventList = implode(',', $events);
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = 'fixed', `stateChangeAt` = NOW(), `fixedAt` = NOW() WHERE `id` IN ({$reqs})");
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`) VALUES {$eventList}");
      break;
    case 'Repaired':
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = 'repaired', `stateChangeAt` = NOW(), `repairedAt` = NOW(), ".
                                 ($curState == 'fixed' ? "" : "`fixedAt` = NOW(), ")."`solutionProblem` = ?, `solution` = ?, ".
                                 "`solutionRecomendation` = ? WHERE `id` = ?");
      $query1->bind_param('sssi', $_REQUEST['solProblem'], $_REQUEST['sol'], $_REQUEST['solRecomend'], $reqList[0]);
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`) VALUES (NOW(), 'changeState', 'repaired', ?, ?)");
	  $query2->bind_param('ii', $reqList[0], $userId);
      break;
    case 'Wait':
      $wait = 1-$wait;
      $query1 = $mysqli->prepare("UPDATE `request` SET `onWait` = ? WHERE `id` = ?");
      $query1->bind_param('ii', $wait, $reqList[0]);
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `request_id`, `users_id`, `text`) VALUES (NOW(), '".
                                 ($wait == 1 ? "onWait" : "offWait")."', ?, ?, ?)");
      $query2->bind_param('iis', $reqList[0], $userId, $cause);
      break;
    case 'Close':
      $events = array();
      foreach ($reqList as $reqId)
        $events[] = "(NOW(), 'changeState', 'closed', {$reqId}, {$userId})";
      $eventList = implode(',', $events);
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = 'closed', `stateChangeAt` = NOW() WHERE `id` IN ({$reqs})");
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`) VALUES {$eventList}");
      break;
    case 'UnClose':
      $req = $mysqli->prepare("SELECT `newState` ".
                                "FROM `requestEvents` ".
                                "WHERE `request_id` = ? AND `event` = 'changeState' AND `newState` IN ('accepted', 'fixed') ".
                                "ORDER BY `timestamp` DESC ".
                                "LIMIT 1");
      $req->bind_param('i', $reqList[0]);
      $req->bind_result($oldState);
      if (!$req->execute()) { 
        sendJson(array('error' => 'Внутренняя ошибка сервера'));
        $req->close();
        $mysqli->close();
        exit;
      }
      $req->fetch();
      $req->close();
      if ($oldState == '')
        $oldState = 'accepted';
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = ?, `stateChangeAt` = NOW(), `repairedAt` = NULL".
                                 ($oldState == 'fixed' ? "" : ", `fixedAt` = NULL")." WHERE `id` = ?");
      $query1->bind_param('si', $oldState, $reqList[0]);
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`, `text`) VALUES (NOW(), 'unClose', ?, ?, ?, ?)");
      $query2->bind_param('siis', $oldState, $reqList[0], $userId, $cause);
      break;
    case 'UnCancel':
      $query1 = $mysqli->prepare("UPDATE `request` SET `currentState` = 'received', `stateChangeAt` = NOW(), `repairedAt` = NULL WHERE `id` = ?");
      $query1->bind_param('i', $reqList[0]);
      $query2 = $mysqli->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`, `text`) VALUES (NOW(), 'unCancel', 'accepted', ?, ?, ?)");
      $query2->bind_param('iis', $reqList[0], $userId, $cause);
      break;
  }
  if (!$mysqli->query("START TRANSACTION") ||
      !$query1->execute() ||
      !$query2->execute() ||
      !$mysqli->query("COMMIT")) {
    $mysqli->query("ROLLBACK");
    sendJson(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  sendJson(array('ok' => 'ok'));
  $mysqli->close();
?>
