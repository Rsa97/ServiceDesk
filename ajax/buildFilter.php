<?php
  header('Content-Type: application/json; charset=UTF-8');

  include('../config/db.php');
  session_start();
  if (!isset($_SESSION['user'])) {
    echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }

  // Описываем кнопки, доступные с разными правами в разных разделах
  $btnText = array('New' => 'Создать', 'Accept' => 'Принять', 'Cancel' => 'Отменить', 'SetTo' => 'Назначить', 'Dload' => 'Выгрузить',
                   'UpForm' => 'Загрузить форму', 'Close' => 'Закрыть', 'CheckForm' => 'Форма обследования', 'UnCancel' => 'Открыть повторно',
                   'Delete' => 'Удалить', 'Edit' => 'Изменить', 'Fixed' => 'Восстановлено', 'Repaired' => 'Завершено', 'Wait' => 'Ожидание',
                   'UnClose' => 'Отказать', 'DoNow' => 'Выполнить сейчас', 'AddProblem' => 'Добавить примечание');
  $btnIco = array('New' => 'ui-icon-document', 'Accept' => 'ui-icon-plus', 'Cancel' => 'ui-icon-cancel', 'SetTo' => 'ui-icon-seek-next', 
                  'Dload' => 'ui-icon-circle-arrow-s', 'UpForm' => 'ui-icon-circle-arrow-n', 'Close' => 'ui-icon-closethick', 
                  'CheckForm' => 'ui-icon-clipboard', 'UnCancel' => 'ui-icon-notice', 'Delete' => 'ui-icon-trash', 'Edit' => 'ui-icon-pencil',
                  'Fixed' => 'ui-icon-wrench', 'Repaired' => 'ui-icon-check', 'Wait' => 'ui-icon-clock', 'UnClose' => 'ui-icon-alert',
				  'DoNow' => 'ui-icon-extlink', 'AddProblem' => 'ui-icon-info');

  $buttons = array('received' => array('admin' => array('New', 'Accept', 'Cancel'),
                                       'client' => array('New', 'Cancel'),
                                       'operator' => array('New', 'Cancel'),
                                       'engeneer' => array('New', 'Accept', 'Cancel'),
                                       'partner' => array('Accept')),
                   'accepted' => array('admin' => array('Fixed', 'Repaired', 'Wait'),
                                       'client' => array('Cancel'),
                                       'operator' => array('Cancel'),
                                       'engeneer' => array('Fixed', 'Repaired', 'Wait'),
                                       'partner' => array('Fixed', 'Repaired','Wait')),
                   'toClose' => array('admin' => array('UnClose', 'Close'),
                                       'client' => array('UnClose', 'Close'),
                                       'operator' => array('UnClose', 'Close'),
                                       'engeneer' => array(),
                                       'partner' => array()),
                   'planned' => array( 'admin' => array('DoNow', 'AddProblem'),
                                       'client' => array(),
                                       'operator' => array('AddProblem'),
                                       'engeneer' => array('DoNow', 'AddProblem'),
                                       'partner' => array('DoNow', 'AddProblem')),
                   'closed' => array(  'admin' => array(),
                                       'client' => array(),
                                       'operator' => array(),
                                       'engeneer' => array(),
                                       'partner' => array()),
                   'canceled' => array('admin' => array('UnCancel'),
                                       'client' => array(),
                                       'operator' => array(),
                                       'engeneer' => array(),
                                       'partner' => array()));

  $byUser = 1;
  $byActive = 1;
  $byAllowed = 0;
  $rights = $_SESSION['user']['rights'];
  $userId = $_SESSION['user']['myID'];
  $partnerId = $_SESSION['user']['partner'];
  $userName = "{$_SESSION['user']['lastName']} {$_SESSION['user']['firstName']} {$_SESSION['user']['middleName']}";

  $_SESSION['time'] = time();
  session_commit();

	switch($rights) {
 		case 'admin':
      $byActive = 0;
		case 'engeneer':
     	case 'operator':
			$byUser = 0;
			break;
		case 'partner':
       		$byAllowed = 1;
		case 'client':
			break;
		}
  // Подключаемся к MySQL
  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
    trigger_error("Ошибка подключения к серверу MySQL ({$mysqli->connect_errno})  {$mysqli->connect_error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $mysqli->query("SET NAMES utf8");

  // Строим фильтр по контрагентам и подразделениям
	$req = $mysqli->prepare("SELECT DISTINCT `ca`.`id`, `div`.`id`, `ca`.`name`, `div`.`name`, `n`.`num` ".
                            "FROM `contragents` AS `ca` ".
							"LEFT JOIN `contractDivisions` AS `div` ON `div`.`contragents_id` = `ca`.`id` ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivisions_id` = `div`.`id` ".
							"LEFT JOIN `contracts` AS `c` ON `ca`.`id` = `c`.`contragents_id` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contracts_id` = `c`.`id` ".
							"LEFT JOIN `allowedContracts` AS `a` ON `div`.`id` = `a`.`contractDivisions_id` ".
							"LEFT JOIN (SELECT `contracts_id`, COUNT(`contracts_id`) AS `num` ".
                                            "FROM `contractDivisions` ".
                                            "GROUP BY `contracts_id`) ".
                                "AS `n` ON `n`.`contracts_id` = `c`.`id` ".
                            "WHERE (? = 0 OR `ucd`.`users_id` = ? OR `uc`.`users_id` = ?) ".
                        		"AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
								"AND (? = 0 OR `a`.`partner_id` = ?) ".
                            "ORDER BY `ca`.`name`, `div`.`name`");
  $req->bind_param('iiiiii', $byUser, $userId, $userId, $byActive, $byAllowed, $partnerId);
  $req->bind_result($contragentId, $divisionId, $contragentName, $divisionName, $divNum);
  if (!$req->execute()) {
    trigger_error("Ошибка чтения из базы данных ({$mysqli->errno})  {$mysqli->error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $prevContragent = "";
  $byDiv = "<select class='ui-widget ui-corner-all ui-widget-content' id='selectDivision'>\n".
		          "<option value='n0' selected>&#160;&#160;&#160;&#160;--- Все --- </option>\n";
  $contragentIds = array();
  while ($req->fetch()) {
    if ($contragentId != $prevContragent) {
      $byDiv .= "<option value='g{$contragentId}'>[{$contragentName}]\n";
      $prevContragent = $contragentId;
      $contragentIds[] = $contragentId;
    }
    if ($divNum > 1)
    	$byDiv .= "<option value='d{$divisionId}'>&#160;&#160;&#160;&#160;{$divisionName}\n";
  }
  $byDiv .= "</select>";
  $contragentIds = implode(',',$contragentIds);
  if ($contragentIds != '')
  	$contragentIds = "AND `c`.`contragents_id` IN ({$contragentIds}) ";
  // Строим фильтр по типам сервисов
	$req = $mysqli->prepare("SELECT DISTINCT `s`.`id`, `s`.`name` ".
                            "FROM `contracts` AS `c` ".
                              "LEFT JOIN `contractServices` AS `cs` ON `cs`.`contract_id` = `c`.`id` ".
                              "LEFT JOIN `services` AS `s` ON `s`.`id` = `cs`.`services_id` ".
                            "WHERE (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
                            	$contragentIds);
  $req->bind_param('i', $byActive);
  $req->bind_result($id, $name);
  if (!$req->execute()) {
    trigger_error("Ошибка чтения из базы данных ({$mysqli->errno})  {$mysqli->error}");
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $bySrv = "<select class='ui-widget ui-corner-all ui-widget-content' id='selectService'>\n".
           "<option value='n0'>&#160;&#160;&#160;&#160;--- Все --- </option>\n";
  while ($req-> fetch()) {
    $bySrv .= "<option value='s{$id}'>{$name}</option>";
  }
  $bySrv .= "</select>\n";

  $result = array('fltByDivPlace' => $byDiv, 'fltBySrvPlace' => $bySrv);

  // Формируем доступные кнопки
  foreach ($buttons as $state => $operLists) {
    $op = "{$state}Opers";
    $result[$op] = '';
    foreach($operLists[$rights] as $oper)
      if ($oper != '')
        $result[$op] .= "<button class='btn{$oper}' data-icon='{$btnIco[$oper]}' data-cmd='{$oper}'>{$btnText[$oper]}</button>";
  }

  $result['name'] = $userName;

  // Готовим вывод
  echo json_encode($result);

?>

