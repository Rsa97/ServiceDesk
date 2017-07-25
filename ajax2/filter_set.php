<?php
header('Content-Type: application/json; charset=UTF-8');
include '../config/db.php';
include 'common.php';

session_start();
if (!isset($_SESSION['user'])) {
	echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
	exit;
}
  
// Распределение статусов по разделам
$statusGroup = array('received' => 'received',
					 'preReceived' => 'received',
					 'accepted' => 'accepted',
					 'fixed' => 'accepted',
					 'repaired' => 'toClose',
					 'closed' => 'closed',
					 'canceled' => 'canceled');

$statusIcons = array('received' => 'ui-icon-mail-closed',
					 'preReceived' => 'ui-icon-mail-closed',
					 'accepted' => 'ui-icon-mail-open',
					 'fixed' => 'ui-icon-wrench',
					 'repaired' => 'ui-icon-help',
					 'closed' => 'ui-icon-check',
					 'canceled' => 'ui-icon-cancel',
					 'planned' => 'ui-icon-calendar',
					 'onWait' => 'ui-icon-clock');

$sortOrder = array('received' => 'ASC',
					 'accepted' => 'ASC',
					 'repaired' => 'DESC',
					 'closed' => 'DESC',
					 'canceled' => 'DESC');


// Строим фильтр, если данных нет, то берём из сессии или по умолчанию
if (isset($paramValues['byDiv'])) {
	$divGuid = substr($paramValues['byDiv'], 1);
	$byContract = ($paramValues['byDiv'][0] == 'С' ? 1 : 0);
	$byDiv = ($paramValues['byDiv'][0] == 'D' ? 1 : 0);
} else if (isset($_SESSION['filter'])) {
	$divGuid = $_SESSION['filter']['divGuid'];
	$byContract = $_SESSION['filter']['byContract'];
	$byDiv = $_SESSION['filter']['byDiv'];
} else {
	$divGuid = NULL;
	$byContract = 0;
	$byDiv = 0;
}
if (isset($paramValues['bySrv'])) {
	$srvGuid = substr($paramValues['bySrv'], 1);
	$byService = ($paramValues['bySrv'][0] == 'S' ? 1 : 0);
} else if (isset($_SESSION['filter'])) {
	$srvGuid = $_SESSION['filter']['srvGuid'];
	$byService = $_SESSION['filter']['byService'];
} else {
	$srvGuid = NULL;
	$byService = 0;
}
if (isset($paramValues['byText'])) {
	$text = trim($paramValues['byText']);
	$byText = ('' == $text ? 0 : 1);
} else if (isset($_SESSION['filter'])) {
	$text = $_SESSION['filter']['text'];
	$byText = $_SESSION['filter']['byText'];
} else {
	$text = '';
	$byText = 0;
}
if (isset($paramValues['byFrom']))
	$byFrom = $paramValues['byFrom'].' 00:00:00';
else if (isset($_SESSION['filter']))
	$byFrom = $_SESSION['filter']['byFrom'];
else 
	$byFrom = date('Y-m-d 00:00:00', strtotime("-3 months"));
if (isset($paramValues['byTo']))
	$byTo = $paramValues['byTo'].' 23:59:59';
else if (isset($_SESSION['filter']))
	$byTo = $_SESSION['filter']['byTo'];
else 
	$byTo = date('Y-m-d 23:59:59', strtotime("now"));

$onlyMy = (isset($paramValues['onlyMy']) ? $paramValues['onlyMy'] : (isset($_SESSION['filter']) ? $_SESSION['filter']['onlyMy'] : 0));
$rights = (isset($_SESSION['user']['rights']) ? $_SESSION['user']['rights'] : 'none');
$userGuid =  (isset($_SESSION['user']['myID']) ? $_SESSION['user']['myID'] : '0');
$partnerGuid = (isset($_SESSION['user']['partner']) ? $_SESSION['user']['partner'] : 0);

// Сохраняем его в сессии
$_SESSION['filter'] = array('divGuid' => $divGuid, 
							'srvGuid' => $srvGuid,
							'onlyMy' => $onlyMy,
							'byContract' => $byContract,
							'byDiv' => $byDiv,
							'byService' => $byService,
							'byFrom' => $byFrom,
							'byTo' => $byTo,
							'byText' => $byText,
							'text' => $text);

$_SESSION['time'] = time();
session_commit();

$byClient = ($rights == 'client' ? 1 : 0);
$byPartner = ($rights == 'partner' ? 1 : 0);
$byActive = ($rights == 'admin' ? 0 : 1);

// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass);
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
$db->exec("SET NAMES utf8");

// Пересчитываем и перестраиваем списки заявок
// Считаем общее количество заявок
try {
	$req = $db->prepare("SELECT `state`, COUNT(`state`) AS `count` ".
							"FROM (".
        						"SELECT DISTINCT `rq`.`id`, `rq`.`currentState` AS `state` ".
          							"FROM `requests` AS `rq` ".
            						"LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
            						"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            						"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            						"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
	          						"WHERE (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
    	        						"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
        	    							"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            							"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', ''))) ".
							") AS `t` ".
      						"GROUP BY `state`");
	$req->execute(array('byActive' =>  $byActive, 'byClient' => $byClient, 'userGuid' => $userGuid, 'byPartner' => $byPartner, 
						'partnerGuid' => $partnerGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
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
while ($row = $req->fetch(PDO::FETCH_ASSOC))
    $result[$statusGroup[$row['state']].'Num'] += $row['count'];
  
// Готовим списки заявок по каждому разделу
try {
	$req = $db->prepare("SELECT DISTINCT `rq`.`id`, `rq`.`currentState`, `rq`.`stateChangedAt`, `srv`.`shortName`, `srv`.`name`, `srv`.`autoOnly`, ". 
										"`rq`.`createdAt`, `rq`.`reactBefore`, `rq`.`fixBefore`, `rq`.`repairBefore`, `div`.`name`, ".
										"`ca`.`name`, `e`.`lastName`, `e`.`firstName`, `e`.`middleName`, `e`.`email`, `e`.`phone`, ".
										"`et`.`name`, `est`.`name`, `em`.`name`, `emf`.`name`, `eq`.`serviceNumber`, ".
										"`eq`.`serialNumber`, `c`.`number`, `co`.`lastName`, `co`.`firstName`, `co`.`middleName`, `co`.`email`, ".
										"`co`.`phone`, CAST(`rq`.`problem` AS CHAR(1024)), `rq`.`onWait`, `rq`.`reactedAt`, ".
										"IFNULL(`rq`.`fixedAt`, `rq`.`repairedAt`), `rq`.`repairedAt`, `rq`.`slaLevel`, `rq`.`toReact`, `rq`.`toFix`, ".
										"`rq`.`toRepair`, `p`.`name`, `rq`.`guid`, `ow`.`onWaitAt`, ".
										"IFNULL(`rq`.`reactRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`reactedAt`, NOW())))/`rq`.`toReact`), ".
										"IFNULL(`rq`.`fixRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`fixedAt`, IFNULL(`rq`.`repairedAt`, NOW()))))/`rq`.`toFix`), ".
    									"IFNULL(`rq`.`repairRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`repairedAt`, NOW())))/`rq`.`toRepair`) ".
	            			"FROM `requests` AS `rq` ".
    	        			"LEFT JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` ".
        	    			"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            				"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `rq`.`contractDivision_guid` = `ucd`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
							"LEFT JOIN `partners` AS `p` ON `p`.`guid` = `rq`.`partner_guid` ".
            				"LEFT JOIN `services` AS `srv` ON `srv`.`guid` = `rq`.`service_guid` ".
	            			"LEFT JOIN `users` AS `e` ON `e`.`guid` = `rq`.`engineer_guid` ".
    	        			"LEFT JOIN `users` AS `co` ON `co`.`guid` = `rq`.`contactPerson_guid` ".
        	    			"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `rq`.`equipment_guid` ".
            				"LEFT JOIN `equipmentModels` AS `em` ON `em`.`guid` = `eq`.`equipmentModel_guid` ".
            				"LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`guid` = `em`.`equipmentSubType_guid` ".
            				"LEFT JOIN `equipmentTypes` AS `et` ON `et`.`guid` = `est`.`equipmentType_guid` ".
            				"LEFT JOIN `equipmentManufacturers` AS `emf` ON `emf`.`guid` = `em`.`equipmentManufacturer_guid` ".
	            			"LEFT JOIN `divServicesSLA` AS `dss` ON `dss`.`contract_guid` = `c`.`guid` ".
        	    				"AND `dss`.`service_guid` = `rq`.`service_guid` ".
    	        				"AND `dss`.`divType_guid` = `div`.`type_guid` AND `dss`.`slaLevel` = `rq`.`slaLevel` ".
    	        			"LEFT JOIN (".
    							"SELECT MAX(`timestamp`) AS `onWaitAt`, `request_guid` ".
    								"FROM `requestEvents` ".
    								"WHERE `event` = 'onWait' ".
    								"GROUP BY `request_guid`".
    						") AS `ow` ON `ow`.`request_guid` = `rq`.`guid` ".
          					"WHERE (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            					"AND (:onlyMy = 0 OR `rq`.`contactPerson_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
            						"OR `rq`.`engineer_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byDiv = 0 OR `rq`.`contractDivision_guid` = UNHEX(REPLACE(:divGuid, '-', ''))) ".
            					"AND (:byContract = 0 OR `div`.`contract_guid` = UNHEX(REPLACE(:divGuid, '-', ''))) ".
	            				"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
    	        					"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
        	    				"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', ''))) ".
            					"AND (:byService = 0 OR `rq`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', ''))) ".
            					"AND (:byText = 0 OR `rq`.`problem` LIKE :text) ".
            					"AND `rq`.`createdAt` BETWEEN :byFrom AND :byTo ".
							"ORDER BY `rq`.`id`");
	$req->execute(array('byActive' => $byActive, 'onlyMy' => $onlyMy, 'userGuid' => $userGuid, 'byDiv' => $byDiv, 
						'divGuid' => $divGuid, 'byContract' => $byContract, 'byClient' => $byClient, 'byPartner' => $byPartner, 
						'partnerGuid' => $partnerGuid, 'byService' => $byService, 'serviceGuid' => $srvGuid, 'byText' => $byText, 
						'text' => '%'.$text.'%', 'byFrom' => $byFrom, 'byTo' => $byTo));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}

$counts = array();
$tables = array();
foreach($statusGroup as $groupId) {
	$counts[$groupId] = 0;
	$tables[$groupId] = '';
}
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($id, $state, $stateTime, $srvSName, $srvName, $srvAutoOnly, $createdAt, $reactBefore, $fixBefore, $repairBefore, $div, $contragent, 
		 $engLN, $engGN, $engMN, $engEmail, $engPhone, $eqType, $eqSubType, $eqName, $eqMfg, $servNum, $serial, $contractNumber, 
		 $contLN, $contGN, $contMN, $contEmail, $contPhone, $problem, $onWait, $reactedAt, $fixedAt, $repairedAt, $slaLevel, 
		 $timeToReact, $timeToFix, $timeToRepair, $partnerName, $requestGuid, $onWaitAt, $reactPercent, $fixPercent, $repairPercent) = $row;
	$engName = $engLN.($engGN == '' ? '' : (' '.mb_substr($engGN, 0, 1, 'utf-8').'.')).
			   ($engMN == '' ? '' : (' '.mb_substr($engMN, 0, 1, 'utf-8').'.'));
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
		if ($reactPercent > 1)
			$reactPercent = 1;
		if ($fixPercent > 1)
			$fixPercent = 1;
		if ($repairPercent > 1)
        	$repairPercent = 1;
		$reactColor = ($reactedAt == '' ? ('rgb('.floor(255*$reactPercent).','.floor(255*(1-$reactPercent)).',0)') : '#808080');
		$fixColor = ($fixedAt == '' ? ('rgb('.floor(255*$fixPercent).','.floor(255*(1-$fixPercent)).',0)') : '#808080');
		$repairColor = ($state == 'closed' ? '#808080' : 
						($repairedAt == '' ? ('rgb('.floor(255*$repairPercent).','.floor(255*(1-$repairPercent)).',0)') : 'yellow'));
		$timeComment = ($reactedAt == '' ? (1 == $onWait ? ("Приостановлено ".date_format(date_create($onWaitAt), 'd.m.Y H:i')) :
						("Принять до ".date_format(date_create($reactBefore), 'd.m.Y H:i'))) : 
						("Принято ".date_format(date_create($reactedAt), 'd.m.Y H:i')))."\n".
                     	($fixedAt == '' ? (1 == $onWait ? ('' == $reactedAt ? '' : ("Приостановлено ".date_format(date_create($onWaitAt), 'd.m.Y H:i'))) : 
                     	 ("Восстановить до ".date_format(date_create($fixBefore), 'd.m.Y H:i'))) : 
                     	 ("Восстановлено ".date_format(date_create($fixedAt), 'd.m.Y H:i')))."\n".
                     	($repairedAt == '' ? (1 == $onWait ? ('' == $fixedAt ? '' : ("Приостановлено ".date_format(date_create($onWaitAt), 'd.m.Y H:i'))) : 
                     	 ("Завершить до ".date_format(date_create($repairBefore), 'd.m.Y H:i'))) : 
                     	 ("Завершено ".date_format(date_create($repairedAt), 'd.m.Y H:i')))."\n";
		$sliderColor = ($reactedAt == '' ? $reactColor : ($fixedAt == '' ? $fixColor : $repairColor));
		$reactPercent = floor($reactPercent*100);
		$fixPercent = floor($fixPercent*100);
		$repairPercent = floor($repairPercent*100);
	}
	$str = 
		"<tr id='{$id}' data-autoonly={$srvAutoOnly}".($repairPercent >= 100 ? " class='timeIsOut'" : "").">".
		"<td><input type='checkbox' class='checkOne'>".
		"<abbr title='{$statusNames[$state]}'><span class='ui-icon {$statusIcons[$state]}'></span></abbr>".
		(1 == $onWait ? "<abbr title='{$statusNames['onWait']}'><span class='ui-icon {$statusIcons['onWait']}'></span></abbr>" : "").
		(null == $requestGuid ? "<abbr title='Нет синхронизации с 1С'><span class='ui-icon ui-icon-alert'></span></abbr>" : "").
		('' == $partnerName ? "" : "<abbr title='{$partnerName}'><span class='ui-icon ui-icon-arrowthick-1-e'></span></abbr>").
		"<td>".sprintf('%07d', $id).
		"<td>{$slaLevels[$slaLevel]}".
		"<td><abbr title='{$srvName}\n{$problem}'>{$srvSName}</abbr>".
		"<td>".date_format(date_create($createdAt), 'd.m.Y H:i').
		"<td>".date_format(date_create($repairBefore), 'd.m.Y H:i').
		"<td><abbr title='Договор {$contractNumber}'>{$contragent}</abbr>".
		"<td><abbr title='Контактное лицо: {$contLN} {$contGN} {$contMN}\nE-mail: {$contEmail}\nТелефон: {$contPhone}\n'>{$div}</abbr>".
		"<td><abbr title='{$engLN} {$engGN} {$engMN}\nE-mail: {$engEmail}\nТелефон: {$engPhone}'>{$engName}</abbr>".
//		"<td><abbr title='{$eqSubType}\n{$eqMfg} {$eqName}\nСервисный номер: ${servNum}\nSN: {$serial}'>{$eqType}</abbr>".
		"<td><abbr title='{$timeComment}'>".
        	"<div class='timeSlider' style='border: 1px solid {$sliderColor};'>".
				"<div class='scale' style='background-color: {$reactColor}; width: {$reactPercent}%';></div>".
				"<div class='scale' style='background-color: {$fixColor}; width: {$fixPercent}%';></div>".
				"<div class='scale' style='background-color: {$repairColor}; width: {$repairPercent}%';></div>".
			"</div>".
		"</abbr>";
	if ('DESC' == $sortOrder[$statusGroup[$state]])
		$tables[$statusGroup[$state]] = $str.$tables[$statusGroup[$state]];
	else
		$tables[$statusGroup[$state]] .= $str;
	$counts[$statusGroup[$state]]++;
}

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
								  	"<th>Филиал".
								  	"<th>Ответственный".
//								  	"<th>Оборудование".
								  	"<th>Осталось".
								  $table.
								  "</table>";
}
foreach ($counts as $state => $count) {
	if ($count > 0 || $result[$state.'Num'] > 0)
		$result[$state.'Num'] = $count.'/'.$result[$state.'Num'];
}

// Готовим таблицу плановых заявок
try {
	$req = $db->prepare("SELECT DISTINCT `pr`.`id`, `pr`.`slaLevel`, `s`.`shortname`, `s`.`name`, `pr`.`nextDate`, `ca`.`name`, ".
  										"`div`.`name`, `pr`.`problem`, `pr`.`nextDate` <= DATE_ADD(NOW(), INTERVAL `pr`.`preStart` DAY), ".
  										"`div`.`addProblem` ".
  							"FROM `plannedRequests` AS `pr` ".
            				"LEFT JOIN `contractDivisions` AS `div` ON `pr`.`contractDivision_guid` = `div`.`guid` ".
            					"AND `div`.`isDisabled` = 0 ".
            				"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
	            			"LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` ".
    	        			"LEFT JOIN `userContractDivisions` AS `ucd` ON `pr`.`contractDivision_guid` = `ucd`.`contractDivision_guid` ".
        	    			"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
            				"LEFT JOIN `services` AS `s` ON `s`.`guid` = `pr`.`service_guid` ".
   							"WHERE (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            					"AND (:byDiv = 0 OR `pr`.`contractDivision_guid` = UNHEX(REPLACE(:divGuid, '-', ''))) ".
	            				"AND (:byContract = 0 OR `div`.`contract_guid` = UNHEX(REPLACE(:divGuid, '-', ''))) ".
    	        				"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
        	    					"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `pr`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', ''))) ".
            					"AND (:byService = 0 OR `pr`.`service_guid` = UNHEX(REPLACE(:serviceGuid, '-', ''))) ".
            					"AND `pr`.`nextDate` < DATE_ADD(NOW(), INTERVAL 1 MONTH) ".
            				"ORDER BY `pr`.`nextDate`");
	$req->execute(array('byActive' => $byActive, 'userGuid' => $userGuid, 'byDiv' => $byDiv, 'divGuid' => $divGuid, 
						'byContract' => $byContract, 'byClient' => $byClient, 'byPartner' => $byPartner, 'partnerGuid' => $partnerGuid, 
						'byService' => $byService, 'serviceGuid' => $srvGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}

$table = '';
$count = 0;
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($id, $slaLevel, $srvSName, $srvName, $nextDate, $contragent, $div, $problem, $canPreStart, $divProblem) = $row;
  	$table .= 
		"<tr id='{$id}'>".
			"<td><input type='checkbox' class='checkOne'".(0 == $canPreStart ? " disabled" : "").">".
				"<abbr title='".$statusNames['planned']."'><span class='ui-icon ".$statusIcons['planned']."'></span></abbr>".
			"<td>{$slaLevels[$slaLevel]}".
			"<td>{$srvSName}".
			"<td>".date_format(date_create($nextDate), 'd.m.Y').
			"<td>".($div == $contragent ? "" : "{$div}, ").$contragent.
			"<td>{$problem}".($divProblem == '' ? '' : "<br>".preg_replace('/\n/', '<br>', $divProblem));
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
                                "<th>Задача".
                                $table.
                             "</table>";
$result['plannedNum'] = $count;

echo json_encode($result);
?>