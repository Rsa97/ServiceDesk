<?php
header('Content-Type: application/json; charset=UTF-8');

include '../config/db.php';
include 'common.php';

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
									 'engineer' => array('New', 'Accept', 'Cancel'),
									 'partner' => array('Accept')),
				 'accepted' => array('admin' => array('Fixed', 'Repaired', 'Wait'),
									 'client' => array('Cancel'),
									 'operator' => array('Cancel'),
									 'engineer' => array('Fixed', 'Repaired', 'Wait'),
									 'partner' => array('Fixed', 'Repaired','Wait')),
				 'toClose'  => array('admin' => array('UnClose', 'Close'),
									 'client' => array('UnClose', 'Close'),
									 'operator' => array('UnClose', 'Close'),
									 'engineer' => array(),
									 'partner' => array()),
				 'planned'  => array('admin' => array('DoNow', 'AddProblem'),
									 'client' => array(),
									 'operator' => array('AddProblem'),
									 'engineer' => array('DoNow', 'AddProblem'),
									 'partner' => array('DoNow', 'AddProblem')),
				 'closed'   => array('admin' => array(),
									 'client' => array(),
									 'operator' => array(),
									 'engineer' => array(),
									 'partner' => array()),
				 'canceled' => array('admin' => array('UnCancel'),
									 'client' => array(),
									 'operator' => array(),
									 'engineer' => array(),
									 'partner' => array()));

$rights = $_SESSION['user']['rights'];
$userGuid = $_SESSION['user']['myID'];
$partnerGuid = $_SESSION['user']['partner'];
$userName = "{$_SESSION['user']['lastName']} {$_SESSION['user']['firstName']} {$_SESSION['user']['middleName']}";

$_SESSION['time'] = time();
session_commit();

$byUser = ('client' == $rights ? 1 : 0);
$byActive = ('admin' == $rights ? 0 : 1);
$byPartner = ('partner' == $rights ? 1 : 0);

// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass);
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
$db->exec("SET NAMES utf8");

// Строим фильтр по контрагентам и подразделениям
try {
	$req = $db->prepare("SELECT DISTINCT `ca`.`name`, `c`.`guid`, `c`.`number`, `div`.`guid`, `div`.`name` ".
							"FROM `contragents` AS `ca` ".
								"JOIN `contracts` AS `c` ON `ca`.`guid` = `c`.`contragent_guid` ".
								"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
								"LEFT JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` ".
								"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ".
								"LEFT JOIN `partnerDivisions` AS `a` ON `div`.`guid` = `a`.`contractDivision_guid` ".
        	                    "WHERE (:byUser = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
            	                			"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
                	        		"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
									"AND (:byPartner = 0 OR `a`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', ''))) ".
                        	    "ORDER BY `ca`.`name`, `c`.`number`, `div`.`name`");
	$req->execute(array('byUser' => $byUser, 'userGuid' => $userGuid, 'byActive' => $byActive, 'byPartner' => $byPartner, 
						'partnerGuid' => $partnerGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$prevContragent = "";
$prevContract = "";
$byDiv = "<select class='ui-widget ui-corner-all ui-widget-content' id='selectDivision'>".
		 "<option value='n0' selected>&#160;&#160;&#160;&#160;--- Все --- </option>";
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($contragentName, $contractGuid, $contractNumber, $divisionGuid, $divisionName) = $row;
	$contractGuid = formatGuid($contractGuid);
	$divisionGuid = formatGuid($divisionGuid);
	if ($contragentName != $prevContragent) {
		$byDiv .= "<optgroup label='".htmlspecialchars($contragentName)."'>";
		$prevContragent = $contragentName;
	}
	if ($contractGuid != $prevContract) {
		$byDiv .= "<option value='C{$contractGuid}'>Договор ".htmlspecialchars($contractNumber);
		$prevContract = $contractGuid;
	}
	$byDiv .= "<option value='D{$divisionGuid}'>&#160;&#160;&#160;&#160;&#160;&#160;".htmlspecialchars($divisionName);
}
$byDiv .= "</select>";

// Строим фильтр по типам сервисов
try {
	$req = $db->prepare("SELECT DISTINCT `s`.`guid`, `s`.`name` ".
						    "FROM `contracts` AS `c` ".
    						"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ". 
							"LEFT JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` ". 
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ". 
    						"LEFT JOIN `partnerDivisions` AS `a` ON `div`.`guid` = `a`.`contractDivision_guid` ". 
    						"JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid` ".
    						"JOIN `services` AS `s` ON `s`.`guid` = `cs`.`service_guid` ".
       	                    "WHERE (:byUser = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
           	                			"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
               	        		"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
								"AND (:byPartner = 0 OR `a`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
	$req->execute(array('byUser' => $byUser, 'userGuid' => $userGuid, 'byActive' => $byActive, 'byPartner' => $byPartner, 
						'partnerGuid' => $partnerGuid));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}
$bySrv = "<select class='ui-widget ui-corner-all ui-widget-content' id='selectService'>\n".
		 "<option value='n0'>&#160;&#160;&#160;&#160;--- Все --- </option>\n";
while ($row = $req->fetch(PDO::FETCH_NUM)) {
	list($serviceGuid, $serviceName) = $row;
	$serviceGuid = formatGuid($serviceGuid);
	$bySrv .= "<option value='S{$serviceGuid}'>{$serviceName}</option>";
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

echo json_encode($result);
?>