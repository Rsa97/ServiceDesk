<?php
  include "../config/db.php";
  include "../config/files.php";
  
  $slaLevels = array('critical' => 'Критический', 'high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий');
  
function sendJson($data) {
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($data);
}

  $states = array('received' => 'Получена',
                  'accepted' => 'Принята к исполнению',
                  'partsWait' => 'Ожидание',
                  'fixed' => 'Работоспособность восстановлена',
                  'repaired' => 'Работа завершена',
                  'closed' => 'Закрыта',
                  'canceled' => 'Отменена',
                  'planned' => 'Плановая');

  session_start();
  if (!isset($_SESSION['user']) || !isset($_SESSION['filter'])) {
    sendJson(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }

  if (isset($_REQUEST['n']) && preg_match('~^(\d+)~', $_REQUEST['n'], $match))
    $requestNum = $match[1];
  else {
    sendJson(array('error' => 'Ошибка в передаче параметров.'));
    exit;
  }

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

  // Получаем заявку с проверкой прав на просмотр
  $req = $mysqli->prepare(
        "SELECT DISTINCT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangeAt`, `srv`.`shortName`, `srv`.`name`, `rq`.`createdAt`, ".
                        "`rq`.`repairBefore`, `div`.`name`, `ca`.`name`, `e`.`secondName`, `e`.`firstName`, `e`.`middleName`, `e`.`email`, ".
                        "`e`.`phone`, `et`.`name`, `est`.`description`, `em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, `eq`.`serialNumber`, ".
                        "`co`.`secondName`, `co`.`firstName`, `co`.`middleName`, `co`.`email`, `co`.`phone`, `rq`.`fixBefore`, `rq`.`repairBefore`, ".
                        "CAST(`rq`.`problem` AS CHAR(8192)), `rq`.`slaLevel`, `co`.`address`, ".
                        "`rq`.`solutionProblem`, `rq`.`solution`,  `rq`.`solutionRecomendation`, `div`.`id` ".
          "FROM `request` AS `rq` ".
            "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivisions_id` = `div`.`id` ".
            "LEFT JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            "LEFT JOIN `contragents` AS `ca` ON `ca`.`id` = `div`.`contragents_id` ".
            "LEFT JOIN `userContractDivisions` AS `ucd` ON `rq`.`contractDivisions_id` = `ucd`.`contractDivisions_id` ".
            "LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `c`.`id` ".
            "LEFT JOIN `allowedContracts` AS `ac` ON `rq`.`contractDivisions_id` =`ac`.`contractDivisions_id` ".
            "LEFT JOIN `services` AS `srv` ON `srv`.`id` = `rq`.`service_id` ".
            "LEFT JOIN `users` AS `e` ON `e`.`id` = `rq`.`engeneer_id` ".
            "LEFT JOIN `users` AS `co` ON `co`.`id` = `rq`.`contactPersons_id` ".
            "LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id` ".
            "LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
            "LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`id` = `em`.`equipmentSubTypes_id` ".
            "LEFT JOIN `equipmentTypes` AS `et` ON `et`.`id` = `est`.`equipmentTypes_id` ".
            "LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`id` = `em`.`equipmentManufacturers_id` ".
          "WHERE (`rq`.`id` = ?) ".
            "AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            "AND (? = 0 OR `rq`.`contactPersons_id` = ? OR `rq`.`engeneer_id` = ?) ".
            "AND (? = 0 OR `rq`.`contractDivisions_id` = ?) ".
            "AND (? = 0 OR `div`.`contragents_id` = ?) ".
            "AND (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
            "AND (? = 0 OR `ac`.`partner_id` = ?) ".
            "AND (? = 0 OR `rq`.`service_id` = ?)");
  $req->bind_param('iiiiiiiiiiiiiiii', $requestNum, $byContrTime, $onlyMy, $userId, $userId, $byDiv, $divFilter, $byCntrAgent, $divFilter, $byClient, $userId, 
                                      $userId, $byPartner, $partnerId, $byService, $srvFilter);
  $req->bind_result($id, $state, $stateTime, $srvSName, $srvName, $createdAt, $repairBefore, $div, $contragent, $engLN, $engGN, $engMN, $engEmail, $engPhone, 
                    $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $contLN, $contGN, $contMN, $contEmail, $contPhone, $fixBefore, $repairBefore, $problem, 
                    $slaLevel, $contAddress, $solProblem, $sol, $solRecomend, $divId);
  if (!$req->execute()) { 
    sendJson(array('error' => 'Внутренняя ошибка сервера'));
    $req->close();
    $mysqli->close();
    exit;
  }
  if (!$req->fetch()) {
    sendJson(array('error' => 'Нет такой заявки или недостаточно прав для просмотра.'));
    exit;
  }
  $req->close();

  if (isset($_REQUEST['op']))
    switch ($_REQUEST['op']) {
	  case 'getSolution':
		sendJson(array('_solProblem' => $solProblem, '_solSolution' => $sol, '_solRecomendation' => $solRecomend));
		exit;
		break; 
      case 'addComment':
        if (isset($_REQUEST['comment']) && $_REQUEST['comment'] != '') {
          $req = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `text`, `request_id`, `users_id`) VALUES('comment', ?, ?, ?)");
          $req->bind_param('sii', $_REQUEST['comment'], $id, $userId);
          if (!$req->execute()) { 
            sendJson(array('error' => 'Внутренняя ошибка сервера'));
            $req->close();
            $mysqli->close();
            exit;
          }
          $req->close();
        }
        break;
      case 'addFile':
        $err = '';
        if (!isset($_FILES['file']))
          $err = 'Ошибка в параметрах';
        else {
          $error = (is_array($_FILES['file']['error']) ? $_FILES['file']['name'][0] : $_FILES['file']['name']);
          if ($error != 0)
            $err = 'Ошибка передачи файла';
          else {
            $size = (is_array($_FILES['file']['size']) ? $_FILES['file']['size'][0] : $_FILES['file']['size']);
            if ($size > 10*1024*1024)
              $err = 'Слишком большой файл, ограничение - 10Mb';
            else {
              $fileName = stripslashes(is_array($_FILES['file']['name']) ? $_FILES['file']['name'][0] : $_FILES['file']['name']);
              $fileTName = (is_array($_FILES['file']['tmp_name']) ? $_FILES['file']['tmp_name'][0] : $_FILES['file']['tmp_name']);
              if (!file_exists("{$fileStorage}/{$requestNum}"))
                mkdir("{$fileStorage}/{$requestNum}", 0755);
              $tempName = tempnam("{$fileStorage}/{$requestNum}", md5($fileName));
              if (preg_match('~/([^/]+)$~', $tempName, $match))
                $tempName = $match[1];
              if (!move_uploaded_file($fileTName, "{$fileStorage}/{$requestNum}/{$tempName}"))
                $err = "Внутренняя ошибка сервера {$fileTName}";
              else {  
                $req = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `text`, `request_id`, `users_id`) VALUES('addDocument', ?, ?, ?)");
                $req->bind_param('sii', $_REQUEST['comment'], $id, $userId);
                if (!$req->execute()) { 
                  $err = 'Внутренняя ошибка сервера';
                } else
                  $reqId = $mysqli->insert_id;
                $req->close();
                if ($err == '') {
                  $req = $mysqli->prepare("INSERT INTO `documents` (`name`, `uniqueName`, `requestEvents_id`) VALUES(?, ?, ?)");
                  $req->bind_param('ssi', $fileName, $tempName, $reqId);
                  if (!$req->execute()) { 
                    $err = 'Внутренняя ошибка сервера';
                  }
                  $req->close();
                }
              }
            }
          }
        }
        if ($err != '')
          sendJson(array('error' => $err));
        else 
          sendJson(array('ok' => 'ok'));
        $mysqli->close();
        exit;
        break;
      case 'getFile':
        $fileUName = '';
        if (isset($_REQUEST['file']) && preg_match('~^(\d+)~', $_REQUEST['file'], $match)) {
          $req = $mysqli->prepare("SELECT `d`.`name`, `d`.`uniqueName` ".
                                    "FROM `requestEvents` AS `re` ".
                                      "LEFT JOIN `documents` AS `d` ON `d`.`requestEvents_id` = `re`.`id` ".
                                  "WHERE `re`.`request_id` = ? ".
                                    "AND `d`.`id` = ?");
          $req->bind_param('ii', $requestNum, $match[1]);
          $req->bind_result($fileName, $fileUName);
          if ($req->execute())
            $req->fetch();
          $req->close();
        }
        if ($fileUName == '' || 
            !file_exists(($file = "{$fileStorage}/{$requestNum}/{$fileUName}")) || 
            ($fileSize = filesize($file)) == 0)
          exit;
        header("Content-Type: application/file");
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename={$fileName}");
        header("Content-Length: {$fileSize}");
        ob_clean();
        flush();
        readfile($file);
        exit;
        break;
    }


  $engName = $engLN.($engGN == '' ? '' : (' '.mb_substr($engGN, 0, 1, 'utf-8').'.')).($engMN == '' ? '' : (' '.mb_substr($engMN, 0, 1, 'utf-8').'.'));
  $createTime = date_timestamp_get(date_create($createdAt));
  $passedTime = date_timestamp_get(date_create('now'))-$createTime;
  $timeToFix = date_timestamp_get(date_create($fixBefore))-$createTime;
  $timeToRepair = date_timestamp_get(date_create($repairBefore))-$createTime;
  $result = array('_servNum' => $servNum,
                  '_SN' => $serial,
                  '_eqType' => (($eqType == "" || $eqSubType == "") ? "{$eqType}{$eqSubType}" : "{$eqType} / {$eqSubType}"),
                  '_manufacturer' => $eqMfg,
                  '_model' => $eqName,
                  '_problem' => htmlspecialchars($problem, ENT_COMPAT, 'UTF-8'),
                  'service' => "<option value='n0' selected>{$srvName}",
                  'level' => "<option value='{$slaLevel}' selected>{$slaLevels[$slaLevel]}",
                  '_createdAt' => date_format(date_create($createdAt), 'd.m.Y H:i'),
                  '_repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
                  'division' => "<option value='{$divId}'>{$div}",
                  'contact' => "<option value='n0'>{$contLN} {$contGN} {$contMN}",
                  '_email' => $contEmail,
                  '_phone' => $contPhone,
                  '_address' => $contAddress,
                  '_cardSolProblem' => $solProblem,
                  '_cardSolSolution' => $sol,
                  '_cardSolRecomendation' => $solRecomend,
                  '!lookServNum' => (($state == 'received' || ($rights != 'client' && $state == 'accepted')) ? 1 : 0)
            );

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
    sendJson(array('error' => 'Внутренняя ошибка сервера.'));
    exit;
  }
  $log = '';
  $files = '';
  while ($req->fetch()) {
    $date = date_format(date_create($time), 'd.m.Y H:i');
    $name = $ln.($gn == '' ? '' : (' '.mb_substr($gn, 0, 1, 'utf-8').'.')).($mn == '' ? '' : (' '.mb_substr($mn, 0, 1, 'utf-8').'.'));
    $log .= "<p class='".($event == 'comment' ? 'logDateComm' : 'logDate')."'>{$date}: <abbr title='$ln $gn $mn\nE-mail: {$email}\nТелефон: ${phone}'>$name</abbr>";
    switch ($event) {
      case 'open':
        $log .= "<p class='logMain'>Заявка создана";
        break;
      case 'changeState':
        $log .= "<p class='logMain'>Статус заявки изменён на '{$states[$newState]}'";
        if ($newState == 'canceled')
          $log .= "\n<span class='logComment'>Причина отмены: ".htmlspecialchars($text);
        break;
      case 'changeDate':
        $log .= "<p class='logMain'>Срок завершения перенесён на ".date_format(date_create($text), 'd.m.Y H:i');
        break;
      case 'comment':
        $log .= "<p class='logComment'>".htmlspecialchars($text, ENT_COMPAT, 'UTF-8');
        break;
      case 'onWait':
        $log .= "<p class='logMain'>Заявка поставлена в ожидание\n<span class='logComment'>Причина: ".htmlspecialchars($text);
        break;
      case 'offWait':
        $log .= "<p class='logMain'>Заявка снята с ожидания\n<span class='logComment'>Комментарий: ".htmlspecialchars($text);
        break;
      case 'unClose':
        $log .= "<p class='logMain'>Отказано в закрытии заявки!\n<span class='logComment'>Причина: ".htmlspecialchars($text);
        break;
      case 'unCancel':
        $log .= "<p class='logMain'>Отмена заявки отменена!\n<span class='logComment'>Причина: ".htmlspecialchars($text);
        break;
      case 'eqChange':
        $log .= "<p class='logMain'>Изменено оборудование по заявке\n<span class='logComment'>".htmlspecialchars($text);
        break;
      case 'addDocument':
        if (file_exists("{$fileStorage}/{$requestNum}/{$docUName}")) {
          $log .= "<p class='logMain'>Добавлен документ '<a href='/ajax/cardOps.php?op=getFile&n={$requestNum}&file={$docId}'>".
                  htmlspecialchars($docName, ENT_COMPAT, 'UTF-8')."</a>'";
          $files .= "<tr><td><td>{$date}<td>".htmlspecialchars($docName, ENT_COMPAT, 'UTF-8')."<td>".filesize("{$fileStorage}/{$requestNum}/{$docUName}").
                    "<td><a href='/ajax/cardOps.php?op=getFile&n={$requestNum}&file={$docId}'>Скачать</a>"  ;
        } else {
          $log .= "<p class='logMain'>Добавлен документ '".htmlspecialchars($docName, ENT_COMPAT, 'UTF-8')."' (потерян)";
        }
        break;
    }
  }
  if ($files != '')
    $files = "<tr><th><th>Дата<th>Имя файла<th>Размер<th>{$files}";
  $result['comments'] = $log;
  $result['cardDocTbl'] = $files;
  
  sendJson($result);
  $req->close();
  $mysqli->close();
?>
