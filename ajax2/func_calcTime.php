<?php
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

function calcTime($db, $div, $serv, $sla, $sql, $date = null) {
	if (null == $date)
		$date = date_create();
	$created = date_format($date, 'Y-m-d');
	$dayStart = date_format($date, 'H:i:s');
	try {
		$req = $db->prepare("SELECT DISTINCT `dss`.`toReact`, `dss`.`toFix`, `dss`.`toRepair`, `dss`.`startDayTime`, ".
											"`dss`.`endDayTime`, `wc`.`date` ".
								"FROM `contractDivisions` AS `cd` ".
								"JOIN `contracts` AS `c` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
									"AND `c`.`guid` = `cd`.`contract_guid` AND `cd`.`isDisabled` = 0 ".
								"JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `cd`.`contract_guid` ".
								"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')) ".
									"AND `dss`.`contract_guid` = `c`.`guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
									"AND `dss`.`slaLevel` = :slaLevel ".
								"JOIN `workCalendar` AS `wc` ON `wc`.`date` >= :created AND FIND_IN_SET(`wc`.`type`, `dss`.`dayType`) ".
								"ORDER BY `wc`.`date` ");
		$req->execute(array('divisionGuid' => $div, 'serviceGuid' => $serv, 'slaLevel' => $sla, 'created' => $created));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	$secs = 0;
	$okReact = 0;
	$okFix = 0;
	$okRepair = 0;
	$reactBefore = '';
	$fixBefore = '';
	$repairBefore = '';
	while (($row = $req->fetch(PDO::FETCH_NUM))) {
		list($toReact, $toFix, $toRepair, $startDayTime, $endDayTime, $day) = $row;
		if ($created != $day || $dayStart < $startDayTime)
			$dayStart = $startDayTime;
		if ($endDayTime == '00:00:00')
			$endDayTime = '24:00:00';
		preg_match('~(\d\d):(\d\d):(\d\d)~', $dayStart, $start);
		preg_match('~(\d\d):(\d\d):(\d\d)~', $endDayTime, $end);
		$daysecs = $end[1]*3600+$end[2]*60+$end[3]-$start[1]*3600-$start[2]*60-$start[3];
		if ($daysecs < 0)
			$daysecs = 0;
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
	return array('createdAt' => ($sql == 0 ? date_format($date, 'd.m.Y H:i') : date_format($date, 'Y-m-d H:i:s')), 
				 'reactBefore' => $reactBefore, 'fixBefore' => $fixBefore, 'repairBefore' => $repairBefore, 'toReact' => $toReact, 
				 'toFix' => $toFix, 'toRepair' => $toRepair);
}

function calcTime2($db, $div, $serv, $sla, $createdAt, $slaTime) {
	preg_match('/(\d{4}-\d\d-\d\d)\s+(\d\d:\d\d:\d\d)/', $createdAt, $matches);
	$createdDay = $matches[1];
	$dayStart = $matches[2];
	try {
		$req = $db->prepare("SELECT DISTINCT `dss`.`startDayTime`, `dss`.`endDayTime`, `wc`.`date` ".
								"FROM `contractDivisions` AS `cd` ".
								"JOIN `contracts` AS `c` ON `cd`.`guid` = UNHEX(REPLACE(:divisionGuid, '-', '')) ".
									"AND `c`.`guid` = `cd`.`contract_guid` AND `cd`.`isDisabled` = 0 ".
								"JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `cd`.`contract_guid` ".
								"JOIN `divServicesSLA` AS `dss` ON `dss`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', '')) ".
									"AND `dss`.`contract_guid` = `c`.`guid` AND `dss`.`divType_guid` = `cd`.`type_guid` ".
									"AND `dss`.`slaLevel` = :slaLevel ".
								"JOIN `workCalendar` AS `wc` ON `wc`.`date` >= :created AND FIND_IN_SET(`wc`.`type`, `dss`.`dayType`) ".
								"ORDER BY `wc`.`date` ");
		$req->execute(array('divisionGuid' => $div, 'serviceGuid' => $serv, 'slaLevel' => $sla, 'created' => $createdDay));
	} catch (PDOException $e) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	$slaTime *= 60;
	$secs = 0;
	while (($row = $req->fetch(PDO::FETCH_NUM))) {
		list($startDayTime, $endDayTime, $day) = $row;
		if ($createdDay != $day || $dayStart < $startDayTime)
			$dayStart = $startDayTime;
		if ($endDayTime == '00:00:00')
			$endDayTime = '24:00:00';
		preg_match('~(\d\d):(\d\d):(\d\d)~', $dayStart, $start);
		preg_match('~(\d\d):(\d\d):(\d\d)~', $endDayTime, $end);
		$daysecs = $end[1]*3600+$end[2]*60+$end[3]-$start[1]*3600-$start[2]*60-$start[3];
		if ($daysecs < 0)
			$daysecs = 0;
		if ($secs+$daysecs > $slaTime) {
			$doBefore = formatDateTime($day, $start, $slaTime-$secs, true);
			break;
		}
		$secs += $daysecs;
		$dayStart = '00:00:00';
	}
	return $doBefore;
}
?>