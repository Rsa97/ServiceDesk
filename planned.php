<?php
  include "config/db.php";

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
  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error)
    die ('Внутренняя ошибка сервера');
  $mysqli->query("SET NAMES utf8");

	$req = $mysqli->prepare(
    	   "SELECT DISTINCT `pr`.`id`, `pr`.`contractDivisions_id`, `pr`.`service_id`, `pr`.`slaLevel`, `pr`.`problem`, `u`.`users_id`, ".
    	   					" `div`.`addProblem` ".
        	"FROM `plannedRequest` AS `pr` ".
            	"JOIN `contractDivisions` AS `div` ON `pr`.`contractDivisions_id` = `div`.`id` AND `div`.`isDisabled` = 0 ".
            	"JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
            	"LEFT JOIN ( ".
            	  "	SELECT `contractDivisions_id`, `users_id` FROM `userContractDivisions` GROUP BY `contractDivisions_id` ".
            	") AS `u` ON `u`.`contractDivisions_id` = `div`.`id` ".
          	"WHERE `pr`.`nextDate` <= NOW() ".
            	"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)");
	$req->bind_result($id, $divId, $srvId, $slaLevel, $problem, $clientId, $divProblem);
	$req1 = $mysqli->prepare(
		"INSERT INTO `request` (`problem`, `createdAt`, `reactBefore`, `fixBefore`, `repairBefore`, ".
				"`currentState`, `contactPersons_id`, `contractDivisions_id`, `slaLevel`, ".
				"`equipment_id`, `service_id`, `toReact`, `toFix`, `toRepair`) ".
			"VALUES (?, ?, ?, ?, ?, 'received', ?, ?, ?, NULL, ?, ?, ?, ?)");
	$req2 = $mysqli->prepare("INSERT INTO `requestEvents` (`event`, `request_id`, `users_id`) VALUES('open', ?, 1)");
    $req2->bind_param('i', $reqId);
    $req3 = $mysqli->prepare("UPDATE `plannedRequest` SET `nextDate` = `nextDate` + INTERVAL `intervalYears` YEAR + ".
    								"INTERVAL `intervalMonths` MONTH + INTERVAL `intervalWeeks` WEEK + INTERVAL `intervalDays` DAY ".
									"WHERE `id` = ?");
	$req3->bind_param('i', $id);
	$req4 = $mysqli->prepare("UPDATE `contractDivisions` SET `addProblem` = '' WHERE `id` = ?"); 
	$req4->bind_param('i', $divId);
		if (!$req->execute()) 
	    die ('Внутренняя ошибка сервера');
	$req->store_result();
	while ($req->fetch()) {
		$time = calcTime($divId, $srvId, $slaLevel, 1);
		$mysqli->query("START TRANSACTION");
		$req1->bind_param('sssssiisiiii', $problem, $time['createdAt'], $time['reactBefore'], $time['fixBefore'], 
										$time['repairBefore'], $clientId, $divId, $slaLevel, $srvId, 
										$time['toReact'], $time['toFix'], $time['toRepair']);
		$problem .= "\n".$divProblem;
		if (!$req1->execute()) { 
			$mysqli->query("ROLLBACK");
		    die ('Внутренняя ошибка сервера');
    	}
    	$reqId = $mysqli->insert_id;
		if (!$req2->execute() || !$req3->execute() || !$req4->execute()) {
			$mysqli->query("ROLLBACK");
		    die ('Внутренняя ошибка сервера');
    	}
		$mysqli->query("COMMIT");
	}
	$mysqli->close();
?>
