<?php
  include "../config/db.php";
  include "../config/files.php";

$allowedOps = array('client' => array('Cancel', 'Close', 'UnClose'),
                    'operator' => array('Cancel', 'Close', 'UnClose'),
                    'engeneer' => array('Accept', 'Cancel', 'Fixed', 'Repaired', 'Wait'),
                    'admin' => array('Accept', 'Cancel', 'Fixed', 'Repaired', 'Wait', 'Close', 'UnClose', 'UnCancel'),
                    'partner' => array('Accept', 'Fixed', 'Repaired', 'Wait'));

  
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
                        'UnCancel' => "'canceled'");
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
    $req->close();
    $mysqli->close();
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
    $mysqli->close();
    sendJson(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
      
  sendJson(array('ok' => 'ok'));
  $mysqli->close();
?>
