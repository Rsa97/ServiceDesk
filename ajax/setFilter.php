<?php
  header('Content-Type: application/json; charset=UTF-8');
  include "../config/db.php";

  session_start();
  if (!isset($_SESSION['user'])) {
    echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }
  
  $slaLevels = array('critical' => 'Критический', 'high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий');

  // Распределение статусов по разделам
  $statusGroup = array('received' => 'received',
                       'accepted' => 'accepted',
                       'fixed' => 'accepted',
                       'repaired' => 'toClose',
                       'closed' => 'closed',
                       'canceled' => 'canceled');
                    

  $statusIcons = array('received' => 'ui-icon-mail-closed',
                       'accepted' => 'ui-icon-mail-open',
                       'fixed' => 'ui-icon-wrench',
                       'repaired' => 'ui-icon-help',
                       'closed' => 'ui-icon-check',
                       'canceled' => 'ui-icon-cancel',
                       'planned' => 'ui-icon-calendar',
                       'onWait' => 'ui-icon-clock');

  $statusNames = array('received' => 'Получена',
                       'accepted' => 'Принята к исполнению',
                       'fixed' => 'Работоспособность восстановлена',
                       'repaired' => 'Работа завершена',
                       'closed' => 'Закрыта',
                       'canceled' => 'Отменена',
                       'planned' => 'Плановая',
                       'onWait' => 'Ожидание комплектующих');




  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    trigger_error("Ошибка подключения к серверу MySQL ({$mysqli->connect_errno})  {$mysqli->connect_error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $mysqli->query("SET NAMES utf8");

  // Строим фильтр, если данных нет, то берём из сессии или по умолчанию
  if (isset($_POST['byDiv']) && preg_match('~^([ngd])(\d+)~', $_POST['byDiv'], $match)) {
    $divFilter = $match[2];
    $byCntrAgent = ($match[1] == 'g' ? 1 : 0);
    $byDiv = ($match[1] == 'd' ? 1 : 0);
  } else if (isset($_SESSION['filter'])) {
    $divFilter = $_SESSION['filter']['divFilter'];
    $byCntrAgent = $_SESSION['filter']['byCntrAgent'];
    $byDiv = $_SESSION['filter']['byDiv'];
  } else {
    $divFilter = 0;
    $byCntrAgent = 1;
    $byDiv = 1;
  }
  if (isset($_POST['bySrv']) && preg_match('~^([ns])(\d+)~', $_POST['bySrv'], $match)) {
    $srvFilter = $match[2];
    $byService = ($match[1] == 's' ? 1 : 0);
  } else if (isset($_SESSION['filter'])) {
    $srvFilter = $_SESSION['filter']['srvFilter'];
    $byService = $_SESSION['filter']['srvFilter'];
  } else {
    $srvFilter = 0;
    $byService = 1;
  }
  $onlyMy = ((isset($_POST['onlyMy']) && preg_match('~^([01])~', $_POST['onlyMy'], $match)) ? $match[1] : (isset($_SESSION['filter']) ? $_SESSION['filter']['onlyMy'] : 0));
  $rights = (isset($_SESSION['user']['rights']) ? $_SESSION['user']['rights'] : 'none');
  $userId =  (isset($_SESSION['user']['myID']) ? $_SESSION['user']['myID'] : '0');
  $partnerId = (isset($_SESSION['user']['partner']) ? $_SESSION['user']['partner'] : 0);
  $byClient = ($rights == 'client' ? 1 : 0);
  $byPartner = ($rights == 'partner' ? 1 : 0);
  $byContrTime = ($rights == 'admin' ? 0 : 1);

  // Сохраняем его в сессии
  $_SESSION['filter'] = array('divFilter' => $divFilter, 
                              'srvFilter' => $srvFilter,
                              'onlyMy' => $onlyMy,
                              'byClient' => $byClient,
                              'byPartner' => $byPartner,
                              'byCntrAgent' => $byCntrAgent,
                              'byDiv' => $byDiv,
                              'byService' => $byService,
                              'byContrTime' => $byContrTime);

  $_SESSION['time'] = time();
  session_commit();

  // Пересчитываем и перестраиваем списки заявок
  // Считаем общее количество заявок
  $req = $mysqli->prepare(
      "SELECT `state`, COUNT(`state`) FROM ".
        "(SELECT DISTINCT `rq`.`id`, `rq`.`currentState` AS `state` ".
          "FROM `request` AS `rq` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            "LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivisions_id` = `rq`.`contractDivisions_id` ".
            "LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `div`.`contracts_id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `rq`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
          "WHERE (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            "AND (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
            "AND (? = 0 OR `ac`.`partner_id` = ?)) AS `t` ".
      "GROUP BY `state`");
  $req->bind_param('iiiiii', $byContrTime, $byClient, $userId, $userId, $byPartner, $partnerId);
  $req->bind_result($state, $count);
  if (!$req->execute()) {
    trigger_error("Ошибка чтения из базы данных ({$mysqli->errno})  {$mysqli->error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  $counts = array();
  $tables = array();
  $result = array();
  foreach($statusGroup as $groupId) {
  	$counts[$groupId] = 0;
	$tables[$groupId] = '';
	$result[$groupId.'Num'] = 0;
  }
  while ($req->fetch()) {
    $result[$statusGroup[$state].'Num'] += $count;
  }

  // Готовим списки заявок по каждому разделу
  $req = $mysqli->prepare(
        "SELECT DISTINCT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangeAt`, `srv`.`shortName`, `srv`.`name`, `rq`.`createdAt`, `rq`.`reactBefore`, ".
                        "`rq`.`fixBefore`, `rq`.`repairBefore`, `div`.`name`, `ca`.`name`, `e`.`secondName`, `e`.`firstName`, `e`.`middleName`, `e`.`email`, ".
                        "`e`.`phone`, `et`.`name`, `est`.`description`, `em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, `eq`.`serialNumber`, ".
                        "`co`.`secondName`, `co`.`firstName`, `co`.`middleName`, `co`.`email`, `co`.`phone`, CAST(`rq`.`problem` AS CHAR(1024)), `rq`.`onWait`, ".
                        "`rq`.`reactedAt`, `rq`.`fixedAt`, `rq`.`repairedAt`, `rq`.`slaLevel`, `rq`.`toReact`, `rq`.`toFix`, `rq`.`toRepair`, calcTime(`rq`.`id`) ".
          "FROM `request` AS `rq` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `div`.`contragents_id` ".
            "LEFT JOIN `userContractDivisions` AS `ucd` ON `rq`.`contractDivisions_id` = `ucd`.`contractDivisions_id` ".
            "LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `div`.`contracts_id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `rq`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
            "LEFT JOIN `services` AS `srv` ON `srv`.`id` = `rq`.`service_id` ".
            "LEFT JOIN `users` AS `e` ON `e`.`id` = `rq`.`engeneer_id` ".
            "LEFT JOIN `users` AS `co` ON `co`.`id` = `rq`.`contactPersons_id` ".
            "LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id` ".
            "LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
            "LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`id` = `em`.`equipmentSubTypes_id` ".
            "LEFT JOIN `equipmentTypes` AS `et` ON `et`.`id` = `est`.`equipmentTypes_id` ".
            "LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`id` = `em`.`equipmentManufacturers_id` ".
            "LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_id` = `c`.`id` AND `dss`.`service_id` = `rq`.`service_id` ".
            		"AND `dss`.`divType_id` = `div`.`type_id` AND `dss`.`slaLevel` = `rq`.`slaLevel` ".
          "WHERE (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            "AND (? = 0 OR `rq`.`contactPersons_id` = ? OR `rq`.`engeneer_id` = ?) ".
            "AND (? = 0 OR `rq`.`contractDivisions_id` = ?) ".
            "AND (? = 0 OR `div`.`contragents_id` = ?) ".
            "AND (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
            "AND (? = 0 OR `ac`.`partner_id` = ?) ".
            "AND (? = 0 OR `rq`.`service_id` = ?) ".
			"ORDER BY `rq`.`id`");
  $req->bind_param('iiiiiiiiiiiiiii', $byContrTime, $onlyMy, $userId, $userId, $byDiv, $divFilter, $byCntrAgent, $divFilter, $byClient, $userId, $userId, $byPartner, $partnerId, $byService, $srvFilter);
  $req->bind_result($id, $state, $stateTime, $srvSName, $srvName, $createdAt, $reactBefore, 
                    $fixBefore, $repairBefore, $div, $contragent, $engLN, $engGN, $engMN, $engEmail, 
                    $engPhone, $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, 
                    $contLN, $contGN, $contMN, $contEmail, $contPhone, $problem, $onWait, 
                    $reactedAt, $fixedAt, $repairedAt, $slaLevel,
					$timeToReact, $timeToFix, $timeToRepair, $times);
  if (!$req->execute()) { 
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  $counts = array();
  $tables = array();
  foreach($statusGroup as $groupId) {
  	$counts[$groupId] = 0;
	$tables[$groupId] = '';
  }
  while ($req->fetch()) {
    $engName = $engLN.($engGN == '' ? '' : (' '.mb_substr($engGN, 0, 1, 'utf-8').'.')).($engMN == '' ? '' : (' '.mb_substr($engMN, 0, 1, 'utf-8').'.'));
	if ($state == 'canceled') {
	  $reactColor = '#808080';
	  $fixColor = '#808080';
	  $repairColor = '#808080';
	  $timeComment = 'Заявка отменена';
	  $sliderColor = '#808080';
	  $reactPercent = 0;
	  $fixPercent = 0;
	  $repairPercent = 0;
	} else {
      $timeToReact *= 60;
      $timeToFix *= 60;
      $timeToRepair *= 60;
	  $times = split(',', $times);
      if (($reactPercent = $times[0]/$timeToReact) > 1)
	    $reactPercent = 1;
      if (($fixPercent = $times[1]/$timeToFix) > 1)
        $fixPercent = 1;
      if (($repairPercent = $times[2]/$timeToRepair) > 1)
        $repairPercent = 1;
      $reactColor = ($reactedAt == '' ? ('rgb('.floor(255*$reactPercent).','.floor(255*(1-$reactPercent)).',0)') : '#808080');
      $fixColor = ($fixedAt == '' ? ('rgb('.floor(255*$fixPercent).','.floor(255*(1-$fixPercent)).',0)') : '#808080');
      $repairColor = ($state == 'closed' ? '#808080' : ($repairedAt == '' ? ('rgb('.floor(255*$repairPercent).','.floor(255*(1-$repairPercent)).',0)') : 'yellow'));
      $timeComment = ($reactedAt == '' ? ("Принять до ".date_format(date_create($reactBefore), 'd.m.Y H:i')) : ("Принято ".date_format(date_create($reactedAt), 'd.m.Y H:i')))."\n".
                     ($fixedAt == '' ? ("Восстановить до ".date_format(date_create($fixBefore), 'd.m.Y H:i')) : ("Восстановлено ".date_format(date_create($fixedAt), 'd.m.Y H:i')))."\n".
                     ($repairedAt == '' ? ("Завершить до ".date_format(date_create($repairBefore), 'd.m.Y H:i')) : ("Завершено ".date_format(date_create($repairedAt), 'd.m.Y H:i')))."\n";
      $sliderColor = ($reactedAt == '' ? $reactColor : ($fixedAt == '' ? $fixColor : $repairColor));
      $reactPercent = floor($reactPercent*100);
      $fixPercent = floor($fixPercent*100);
      $repairPercent = floor($repairPercent*100);
    }
    $tables[$statusGroup[$state]] .= 
      "<tr id='t{$id}'".($repairPercent >= 100 ? " class='timeIsOut'" : "").">".
        "<td><input type='checkbox' class='checkOne'>".
        "<abbr title='{$statusNames[$state]}'><span class='ui-icon {$statusIcons[$state]}'></span></abbr>".
        ($onWait == 1 ? "<abbr title='{$statusNames['onWait']}'><span class='ui-icon {$statusIcons['onWait']}'></span></abbr>" : "").
        "<td>".sprintf('%07d', $id).
        "<td>{$slaLevels[$slaLevel]}".
        "<td><abbr title='{$srvName}\n{$problem}'>{$srvSName}</abbr>".
        "<td>".date_format(date_create($createdAt), 'd.m.Y H:i').
        "<td>".date_format(date_create($repairBefore), 'd.m.Y H:i').
        "<td><abbr title='{$div}\n{$contLN} {$contGN} {$contMN}\nE-mail: {$contEmail}\nТелефон: {$contPhone}\n'>{$contragent}</abbr>".
        "<td><abbr title='{$engLN} {$engGN} {$engMN}\nE-mail: {$engEmail}\nТелефон: {$engPhone}'>{$engName}</abbr>".
        "<td><abbr title='{$eqSubType}\n{$eqMfg} {$eqName}\nСервисный номер: ${servNum}\nSN: {$serial}'>{$eqType}</abbr>".
        "<td><abbr title='{$timeComment}'>".
          "<div class='timeSlider' style='border: 1px solid {$sliderColor};'>".
            "<div class='scale' style='background-color: {$reactColor}; width: {$reactPercent}%';></div>".
            "<div class='scale' style='background-color: {$fixColor}; width: {$fixPercent}%';></div>".
            "<div class='scale' style='background-color: {$repairColor}; width: {$repairPercent}%';></div>".
            "</div>".
 		"</abbr>";
    $counts[$statusGroup[$state]]++;
  }
  $req->close();
  foreach ($tables as $state => $table) {
    if ($table == '')
      $result["{$state}List"] = "<h2>Заявок не найдено</h2>";
    else
      $result["{$state}List"] = "<table><tr id='n0'>".
                                "<th><input type='checkbox' class='checkAll'>".
                                "<th>Номер".
                                "<th>Уровень".
                                "<th>Услуга".
                                "<th>Дата поступления".
                                "<th>Выполнить до".
                                "<th>Заказчик".
                                "<th>Ответственный".
                                "<th>Оборудование".
                                "<th>Осталось".
                                $table.
                                "</table>";
  }
  foreach ($counts as $state => $count) {
    if ($count > 0)
      $result[$state.'Num'] = $count.'/'.$result[$state.'Num'];
  }

  // Готовим таблицу плановых заявок
  $req = $mysqli->prepare(
  		"SELECT DISTINCT `pr`.`id`, `pr`.`slaLevel`, `s`.`shortname`, `s`.`name`, `pr`.`nextDate`, `ca`.`name`, ".
  				"`div`.`name`, `pr`.`problem`, `pr`.`nextDate` <= DATE_ADD(NOW(), INTERVAL `pr`.`preStart` DAY) ".
  			"FROM `plannedRequest` AS `pr` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `pr`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `div`.`contragents_id` ".
            "LEFT JOIN `userContractDivisions` AS `ucd` ON `pr`.`contractDivisions_id` = `ucd`.`contractDivisions_id` ".
            "LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `div`.`contracts_id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `pr`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
            "LEFT JOIN `services` AS `s` ON `s`.`id` = `pr`.`service_id` ".
   			"WHERE (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            	"AND (? = 0 OR `pr`.`contractDivisions_id` = ?) ".
            	"AND (? = 0 OR `div`.`contragents_id` = ?) ".
            	"AND (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
            	"AND (? = 0 OR `ac`.`partner_id` = ?) ".
            	"AND (? = 0 OR `pr`.`service_id` = ?)".
            	"AND `pr`.`nextDate` < DATE_ADD(NOW(), INTERVAL 1 MONTH) ".
            "ORDER BY `pr`.`nextDate`");
  $req->bind_param('iiiiiiiiiiii', $byContrTime, $byDiv, $divFilter, $byCntrAgent, $divFilter, $byClient, $userId, $userId, $byPartner, $partnerId, $byService, $srvFilter);
  $req->bind_result($id, $slaLevel, $srvSName, $srvName, $nextDate, $contragent, $div, $problem, $canPreStart);
  if (!$req->execute()) { 
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  $table = '';
  $count = 0;
  while ($req->fetch()) {
  	$table .= 
      "<tr id='t{$id}'>".
        "<td><input type='checkbox' class='checkOne'".($canPreStart == 0 ? ' disabled' : '').">".
        "<abbr title='".$statusNames['planned']."'><span class='ui-icon ".$statusIcons['planned']."'></span></abbr>".
        "<td>{$slaLevels[$slaLevel]}".
        "<td><abbr title='{$srvName}\n{$problem}'>{$srvSName}</abbr>".
        "<td>".date_format(date_create($nextDate), 'd.m.Y').
		"<td><abbr title='{$div}'>{$contragent}</abbr>";
    $count++;
  }
  if ($table == '')
      $result['plannedList'] = "<h2>Заявок не найдено</h2>";
    else
      $result['plannedList'] = "<table class='planned'><tr id='n0'>".
                                "<th><input type='checkbox' class='checkAll'>".
                                "<th>Уровень".
                                "<th>Услуга".
                                "<th>Дата".
                                "<th>Заказчик".
                                $table.
                                "</table>";
  $result['plannedNum'] = $count;

  echo json_encode($result);
  $req->close();
  $mysqli->close();
?>
