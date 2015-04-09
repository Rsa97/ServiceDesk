<?php
  header('Content-Type: application/json; charset=UTF-8');

  include('../config/db.php');
  session_start();
  if (!isset($_SESSION['user'])) {
    echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }
  $byUser = 1;
  $byActive = 1;
  $byAllowed = 0;
  if (isset($_SESSION['user']['rights'])) 
  	switch($_SESSION['user']['rights']) {
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
                              "LEFT JOIN `users` AS `u` ON `u`.`contractDivisions_id` = `div`.`id` ".
                              "LEFT JOIN `contracts` AS `c` ON `ca`.`id` = `c`.`contragents_id` ".
                              "LEFT JOIN `allowedContracts` AS `a` ON `a`.`partner_id` = `u`.`partner_id` ".
                              "LEFT JOIN (SELECT `contracts_id`, COUNT(`contracts_id`) AS `num` ".
                                            "FROM `contractDivisions` ".
                                            "GROUP BY `contracts_id`) ".
                                "AS `n` ON `n`.`contracts_id` = `ca`.`id` ".
                            "WHERE (? = 0 OR `u`.`id` = ?) ".
                        		  "AND (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
                              "AND (? = 0 OR `a`.`contractDivisions_id` = `div`.`id`) ".
                            "ORDER BY `ca`.`name`, `div`.`name`");
  $req->bind_param('iiii', $byUser, $_SESSION['user']['myID'], $byActive, $byAllowed);
  $req->bind_result($contragentId, $divisionId, $contragentName, $divisionName, $divNum);
  if (!$req->execute()) {
    echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
    exit;
  }
  $byDiv = "<select class='dropMenu' id='selectDivision' onchange='getval(this);'>\n".
		          "<option value='n0' selected>&#160;&#160;&#160;&#160;--- Все --- </option>\n";
  $prevContragent = "";
  while ($req->fetch()) {
    if ($contragentId != $prevContragent) {
      $byDiv .= "<option value='g{$contragentId}'>[{$contragentName}]\n";
      $prevContragent = $contragentId;
    }
	  if ($divNum > 1)
      $byDiv .= "<option value='d{$divisionId}'>&#160;&#160;&#160;&#160;{$divisionName}\n";
	}
  $byDiv .= "</select>";

$bySrv = "<select class='dropMenu' id='selectService' disabled>\n".
				 "<option id='filter_work_all'>--- Все группы ---</option>\n".
				 "<option id='filter_work_1'>Восстановление работоспособности</option>\n".
				 "<option id='filter_work_2'>Настройка ПО</option>\n".
		     "</select>\n";

  // Готовим вывод
  $wf = 
  "<div class='titleTickets'>Перечень заявок</div>\n".
  "<div id='ticketFilter'>\n".
 		"<label for='selectDivision'>Заказчик:</label><br>{$byDiv}<br>\n".
    "<label for='selectService'>Группы работ:</label><br>{$bySrv}<br>\n".
    "<div id='chkMyTickets'><input type='radio' id='ticketsAll' name='chkMyTickets' value='0' checked><label for='ticketsAll'>Все</label>\n".
    "<input type='radio' id='ticketsMy' name='chkMyTickets' value='1'><label for='ticketsMy'>Только мои</label></div>\n".
  "</div>\n";
  echo json_encode(array('workflowDiv' => $wf));
?>

