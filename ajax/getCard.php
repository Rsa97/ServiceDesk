<?php
  header('Content-Type: application/json; charset=UTF-8');
  include "../config/db.php";
  include "../config/files.php";

  $states = array('received' => 'Получена',
                  'accepted' => 'Принята к исполнению',
                  'partsWait' => 'Ожидание комплектующих',
                  'fixed' => 'Работоспособность восстановлена',
                  'repaired' => 'Работа завершена',
                  'closed' => 'Закрыта',
                  'canceled' => 'Отменена',
                  'planned' => 'Плановая');

  session_start();
  if (!isset($_SESSION['user']) || !isset($_SESSION['filter'])) {
    echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }

  if (isset($_POST['n']) && preg_match('~^(\d+)~', $_POST['n'], $match))
    $requestNum = $match[1];
  else {
    echo json_encode(array('error' => 'Ошибка в передаче параметров.'));
    exit;
  }

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    trigger_error("Ошибка подключения к серверу MySQL ({$mysqli->connect_errno})  {$mysqli->connect_error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
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

  // Получаем заявку с проверкой прав на просмотр
  $req = $mysqli->prepare(
        "SELECT DISTINCT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangeAt`, `srv`.`shortName`, `srv`.`name`, `rq`.`createdAt`, ".
                        "`rq`.`repairBefore`, `div`.`name`, `ca`.`name`, `e`.`secondName`, `e`.`firstName`, `e`.`middleName`, `e`.`email`, ".
                        "`e`.`phone`, `et`.`name`, `est`.`description`, `em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, `eq`.`serialNumber`, ".
                        "`co`.`secondName`, `co`.`firstName`, `co`.`middleName`, `co`.`email`, `co`.`phone`, `rq`.`fixBefore`, `rq`.`repairBefore`, ".
                        "CAST(`rq`.`problem` AS CHAR(8192)), `rq`.`slaCriticalLevels_id`, `sla`.`name`, `co`.`address` ".
          "FROM `request` AS `rq` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `div`.`contragents_id` ".
            "LEFT JOIN `users` AS `u` ON `rq`.`contractDivisions_id` = `u`.`contractDivisions_id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `rq`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
            "LEFT JOIN `services` AS `srv` ON `srv`.`id` = `rq`.`service_id` ".
            "LEFT JOIN `users` AS `e` ON `e`.`id` = `rq`.`engeneer_id` ".
            "LEFT JOIN `users` AS `co` ON `co`.`id` = `rq`.`contactPersons_id` ".
            "LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id` ".
            "LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
            "LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`id` = `em`.`equipmentSubTypes_id` ".
            "LEFT JOIN `equipmentTypes` AS `et` ON `et`.`id` = `est`.`equipmentTypes_id` ".
            "LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`id` = `em`.`equipmentManufacturers_id` ".
            "LEFT JOIN `slaCriticalLevels` AS `sla` ON `sla`.`id` = `rq`.`slaCriticalLevels_id` ".
          "WHERE (`rq`.`id` = ?) ".
            "AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            "AND (? = 0 OR `rq`.`contactPersons_id` = ? OR `rq`.`engeneer_id` = ?) ".
            "AND (? = 0 OR `rq`.`contractDivisions_id` = ?) ".
            "AND (? = 0 OR `div`.`contragents_id` = ?) ".
            "AND (? = 0 OR `u`.`id` = ?) ".
            "AND (? = 0 OR `ac`.`partner_id` = ?) ".
            "AND (? = 0 OR `rq`.`service_id` = ?)");
  $req->bind_param('iiiiiiiiiiiiiii', $requestNum, $byContrTime, $onlyMy, $userId, $userId, $byDiv, $divFilter, $byCntrAgent, $divFilter, $byClient, $userId, 
                                      $byPartner, $partnerId, $byService, $srvFilter);
  $req->bind_result($id, $state, $stateTime, $srvSName, $srvName, $createdAt, $repairBefore, $div, $contragent, $engLN, $engGN, $engMN, $engEmail, $engPhone, 
                    $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $contLN, $contGN, $contMN, $contEmail, $contPhone, $fixBefore, $repairBefore, $problem, 
                    $servLevel, $slaName, $contAddress);
  if (!$req->execute()) { 
    trigger_error("Ошибка чтения из базы данных ({$mysqli->errno})  {$mysqli->error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  if (!$req->fetch()) {
    echo json_encode(array('error' => 'Нет такой заявки или недостаточно прав для просмотра.'));
    exit;
  }

  if (isset($_POST['op']))
    switch ($_POST['op']) {
      case 'addComment':
		  break;
    }
  if (!$req->execute()) { 
    trigger_error("Ошибка чтения из базы данных ({$mysqli->errno})  {$mysqli->error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  if (!$req->fetch()) {
    echo json_encode(array('error' => 'Нет такой заявки или недостаточно прав для просмотра.'));
    exit;
  }

  $engName = $engLN.($engGN == '' ? '' : (' '.mb_substr($engGN, 0, 1, 'utf-8').'.')).($engMN == '' ? '' : (' '.mb_substr($engMN, 0, 1, 'utf-8').'.'));
  $createTime = date_timestamp_get(date_create($createdAt));
  $passedTime = date_timestamp_get(date_create('now'))-$createTime;
  $timeToFix = date_timestamp_get(date_create($fixBefore))-$createTime;
  $timeToRepair = date_timestamp_get(date_create($repairBefore))-$createTime;
  $result = array('_servNum' => $servNum,
                  '_SN' => $serial,
                  '_eqType' => "{$eqType} / {$eqSubType}",
                  '_manufacturer' => $eqMfg,
                  '_model' => $eqName,
                  '_problem' => $problem,
                  'service' => "<option value='n0' selected>{$srvName}",
                  'level' => "<option value='n0' selected>{$servLevel} - {$slaName}",
                  '_createdAt' => date_format(date_create($createdAt), 'd.m.Y H:i'),
                  '_repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
                  'division' => "<option value='n0' selected>{$div}",
                  '_contact' => "{$contLN} {$contGN} {$contMN}",
                  '_email' => $contEmail,
                  '_phone' => $contPhone,
                  '_address' => $contAddress
            );
  $req->close();

  // Получаем все события и документы по заявке
  $req = $mysqli->prepare("SELECT `log`.`timestamp`, `log`.`event`, CAST(`log`.`text` AS CHAR(1024)), `log`.`newState`, ".
                                 "`u`.`secondName`, `u`.`firstName`, `u`.`middleName`, `u`.`email`, `u`.`phone`, ".
                                 "`d`.`id`, `d`.`name`, `d`.`uniqueName` ".
                            "FROM `requestEvents` AS `log` ".
                              "LEFT JOIN `users` AS `u` ON `u`.`id` = `log`.`users_id` ".
                              "LEFT JOIN `documents` AS `d` ON `d`.`requestEvents_id` = `log`.`id` ".
                            "WHERE `log`.`request_id` = ? ".
                            "ORDER BY `log`.`timestamp`");
  $req->bind_param('i', $requestNum);
  $req->bind_result($time, $event, $text, $newState, $ln, $gn, $mn, $email, $phone, $docId, $docName, $docUName);
  if (!$req->execute()) {
    echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
    exit;
  }
  $log = '';
  $files = '';
  while ($req->fetch()) {
    $date = date_format(date_create($time), 'd.m.Y H:i');
    $name = $ln.($gn == '' ? '' : (' '.mb_substr($gn, 0, 1, 'utf-8').'.')).($mn == '' ? '' : (' '.mb_substr($mn, 0, 1, 'utf-8').'.'));
    if ($ln == '') 
        $log .= "<p class='".($event == 'comment' ? 'logDateComm' : 'logDate')."'>{$date}: Автоматически";
    else
        $log .= "<p class='".($event == 'comment' ? 'logDateComm' : 'logDate')."'>{$date}: <abbr title='$ln $gn $mn\nE-mail: {$email}\nТелефон: ${phone}'>$name</abbr>";
    switch ($event) {
      case 'open':
        $log .= "<p class='logMain'>Заявка создана";
        break;
      case 'changeState':
        $log .= "<p class='logMain'>Статус заявки изменён на '{$states[$newState]}'";
        break;
      case 'changeDate':
        $log .= "<p class='logMain'>Срок завершения перенесён на ".date_format(date_create($text), 'd.m.Y H:i');
        break;
      case 'comment':
        $log .= "<p class='logComment'>{$text}";
        break;
      case 'addDocument':
        if (file_exists("{$fileStorage}/{$requestNum}/{$docUName}")) {
          $log .= "<p class='logMain'>Добавлен документ '<a class='docRef' data-id='{$docId}'>{$docName}</a>'";
          $files = "<tr class='docRef' data-id='{$docId}'><td><td>{$docName}<td>".filesize("{$fileStorage}/{$requestNum}/{$docUName}");
        } else {
          $log .= "<p class='logMain'>Добавлен документ '{$docName}' (потерян)";
        }
        break;
    }
  }
  if ($files != '')
    $files = "<tr><th><th>Имя файла<th>Размер{$files}";
  $result['comments'] = $log;
  $result['cardDocTbl'] = $files;
  
  echo json_encode($result);
  $req->close();
  $mysqli->close();
?>
