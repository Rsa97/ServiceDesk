<?php
	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	$monthNames = array(1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь', 
						7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь');
	include('../config/db.php');
	session_start();
	if (!isset($_SESSION['user'])) {
		returnJson(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
	if ($_SESSION['user']['rights'] != 'admin') {
		returnJson(array('error' => 'Недостаточно прав для администрирования.', 'redirect' => '/index.html'));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	if (!isset($_REQUEST['call'])) {
		returnJson(array('error' => 'Ошибка в параметрах.'));
		exit;
	}
	// Подключаемся к MySQL
	$mysqli = mysqli_init(); 
	$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, 3306, null, MYSQLI_CLIENT_FOUND_ROWS);
	if ($mysqli->connect_error) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	switch($_REQUEST['call']) {
		case 'init':
			$year = date("Y");
			break;
		case 'setYear':
			if (!isset($_REQUEST['year'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$year = min(max(intval($_REQUEST['year']), 2014), 2029);
			break;
		case 'change':
			if (!isset($_REQUEST['year']) || !isset($_REQUEST['month']) || !isset($_REQUEST['day'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$date = "{$_REQUEST['year']} {$_REQUEST['month']} {$_REQUEST['day']}";
			$req = $mysqli->prepare("UPDATE `workCalendar` SET `type` = IF(`type` = 'work', 'weekend', 'work') WHERE `date` = STR_TO_DATE(?, '%Y %c %e')");
			$req->bind_param('s', $date);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			$req = $mysqli->prepare("SELECT `type` FROM `workCalendar` WHERE `date` = STR_TO_DATE(?, '%Y %c %e')"); 
			$req->bind_param('s', $date);
			$req->bind_result($type);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($req->fetch())
				$ret['ok'] = $type; 
			$req->close();
			returnJson($ret);
			exit;
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$start = "{$year}-01-01";
	$end = "{$year}-12-31";
	$months = array();
	$content = "<div id='calendar' data-year={$year}><span class='shiftYear' data-year='".($year-1)."'>◀</span> {$year} <span class='shiftYear' data-year='".($year+1)."'>▶</span></div>";
	$req = $mysqli->prepare("SELECT MONTH(`date`), DAY(`date`), WEEKDAY(`date`), `type` FROM `workCalendar` WHERE `date` BETWEEN ? AND ? ORDER BY `date`");
	$req->bind_param('ss', $start, $end);
	$req->bind_result($month, $dayofmonth, $weekday, $type);	
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$prevMonth = -1;
	$monthCal = "";
	$lastDoW = 7;
	while ($req->fetch()) {
		if ($prevMonth != $month) {
			if ($monthCal != "") {
				for ($i = $lastDoW; $i < 6; $i++)
					$monthCal .= "<td>";
				$months[] = "<div class='calMonth' data-id='{$prevMonth}'><div class='calMonthName'>$monthNames[$prevMonth]</div>".
							"<table class='monthTbl'><thead><tr><th>Пн<th>Вт<th>Ср<th>Чт<th>Пт<th class='weekend'>Сб<th class='weekend'>Вс".
							"<tbody>{$monthCal}</table></div>";
			}
			$monthCal = "<tr>";
			for ($i = 0; $i < $weekday; $i++)
				$monthCal .= "<td>";
			$monthCal .= "<td class='{$type}'>{$dayofmonth}";
		} else {
			if ($weekday == 0)
				$monthCal .= "<tr>";
			$monthCal .= "<td class='{$type}'>{$dayofmonth}";
		}
		$lastDoW = $weekday;
		$prevMonth = $month;
	}
	for ($i = $lastDoW; $i < 6; $i++)
		$monthCal .= "<td>";
	$months[] = "<div class='calMonth' data-id='{$month}'><div class='calMonthName'>$monthNames[$month]</div>".
				"<table class='monthTbl'><thead><tr><th>Пн<th>Вт<th>Ср<th>Чт<th>Пт<th>Сб<th>Вс<tbody>{$monthCal}</table></div>";
	$content .= join('', $months)."</div>";
	$req->close(); 
	$ret['content'] = $content;
	returnJson($ret);
?>	