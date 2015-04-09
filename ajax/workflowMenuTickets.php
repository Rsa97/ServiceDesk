<?php
  header('Content-Type: application/json; charset=UTF-8');
  include "../config/db.php";

  session_start();
  if (!isset($_SESSION['user'])) {
    echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    trigger_error("Ошибка подключения к серверу MySQL ({$mysqli->connect_errno})  {$mysqli->connect_error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $mysqli->query("SET NAMES utf8");

  $divFilter = (isset($_POST['divFilter']) ?  $_POST['divFilter'] : 0);
  $divType = (isset($_POST['divType']) ? $_POST['divType'] : 'n');
  $onlyMy = (isset($_POST['onlyMy']) ? $_POST['onlyMy'] : 0);
  $srvType = (isset($_POST['srvType']) ? $_POST['srvType'] : 0);
  $rights = (isset($_SESSION['user']['rights']) ? $_SESSION['user']['rights'] : 'none');
  $userId =  (isset($_SESSION['user']['myID']) ? $_SESSION['user']['myID'] : '0');
  $byClient = ($rights == 'client' ? 1 : 0);
  $byPartner = ($rights == 'partner' ? 1 : 0);
  $byCntrAgent = ($divType == 'g' ? 1 : 0);
  $byDiv = ($divType == 'd'  ? 1 : 0);
  $req = $mysqli->prepare(
      "SELECT `state`, COUNT(`state`) FROM ".
        "(SELECT DISTINCT `rq`.`id`, `rq`.`currentState` AS `state` ".
          "FROM `request` AS `rq` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `users` AS `u` ON `rq`.`contractDivisions_id` = `u`.`contractDivisions_id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `rq`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
          "WHERE (? = 0 OR `rq`.`contactPersons_id` = ? OR `rq`.`engeneer_id` = ?) ".
            "AND (? = 0 OR `rq`.`contractDivisions_id` = ?) ".
            "AND (? = 0 OR `div`.`contracts_id` = ?) ".
            "AND (? = 0 OR `u`.`id` = ?) ".
            "AND (? = 0 OR (`ac`.`partner_id` = `u`.`partner_id` AND `u`.`id` = ?)) ".
            "AND (? = 0 OR `rq`.`service_id` = ?)) AS `t` ".
      "GROUP BY `state`");
  $req->bind_param('iiiiiiiiiiiii', $onlyMy, $userId, $userId, $byDiv, $divFilter, $byCntrAgent, $divFilter, $byClient, $userId, $byPartner, $userId, $srvType, $srvType);
  $req->bind_result($state, $count);
  $states = array('receivedNum' => 0, 'acceptedNum' => 0, 'plannedNum' => 0, 'closedNum' => 0, 'canceledNum' => 0);
  if (!$req->execute()) {
    trigger_error("Ошибка чтения из базы данных ({$mysqli->connect_errno})  {$mysqli->connect_error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  while ($req->fetch()) {
    $states[$state] = $count;
  }
	$wf = "
  echo json_encode(array("workflowMenuTicketsDiv" => $wf));
  $req->close();
  $mysqli->close();
?>
