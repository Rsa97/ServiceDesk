<?php
	include "../config/db.php";
	
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


function calcTime($div, $serv, $sla, $sql, $date) {
  global $mysqli; 
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
  	print "{$toReact}, {$toFix}, {$toRepair}, {$startDayTime}, {$endDayTime}, {$day}\n";
	if ($created != $day || $dayStart < $startDayTime)
	  $dayStart = $startDayTime;
	if ($endDayTime == '00:00:00' || $endDayTime == '23:59:59')
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

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
  sendJson(array('error' => 'Внутренняя ошибка сервера'));
  exit;
}
$mysqli->query("SET NAMES utf8");

$date = new DateTime('06/05/2015');
$div = 12;
$serv = 21;
$sla = 'medium';
$sql = 1;

for ($i = 0; $i < 288; $i++) {
	print date_format($date, 'Y-m-d H:i:s')."\n";
	$res = calcTime($div, $serv, $sla, $sql, $date);
	print "{$res['createdAt']}\t{$res['reactBefore']}\t{$res['fixBefore']}\t{$res['repairBefore']}\n";
	$date->add(new DateInterval('PT300S'));
}
?>