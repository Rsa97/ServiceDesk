<?php
include_once('config.php');
// <meta http-equiv="Content-Language" content="ru">
// <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
// function connectToDB() - Подключение к БД MySQL
// function ($username, $password) - Вход в систему
// function checkLoginStatus() - Проверка успешного входа, если нет, то на страницу авторизации
// function getInterfaceName($rules) - Получение имени интерфейса
// function getTicketMenuInterface($status, $rules) - Прорисовка интерфейса
// function getTicketInfo($id) - Получение сведений по заявке



function connectToDB() {
  global $link, $dbhost, $dbuser, $dbpass, $dbname;
  ($link = mysql_pconnect("$dbhost", "$dbuser", "$dbpass")) || die("Не удалось подключиться к MySQL");
  mysql_select_db("$dbname", $link) || die("Не удалось открыть базу данных: $dbname. Ошибка: ".mysql_error() );
  mysql_set_charset('utf8',$link);
} 

function login($username, $password) {
	global $link, $mainFirmID;
	$default_domain = 'sod.local';
	if (strpos($username, '@', 1)) {
		$parts = explode("@",$username);
		$username = $parts[0];
		$domain = $parts[1];
	} else if (strpos($username, '\\')) {
		$parts = explode('\\', $username);
		$username = $parts[1];
		$domain = $parts[0].".local";
	} else {
		$domain = $default_domain;
	}
	$ldap_username = "$username" . "@" . "$domain";
	$adServer = ldap_connect('10.149.0.209 10.149.0.211');
	if (!$adServer) {
		print 'Не удалось подключиться к серверу Active Directory';
		exit;
	}
	$ldapbind = ldap_bind($adServer, $ldap_username, $password);
	if ($ldapbind) {
		$filter="(samAccountName=$username)";
		$entries= array("displayname");
		$haystack = 'CN=users,DC=sod,DC=local';
		$result = ldap_search($adServer, $haystack, $filter);
		$info = ldap_get_entries($adServer, $result);
	
		if(isset($info)) {
			$_SESSION['username'] = $username;
			$_SESSION['displayName'] = iconv("CP1251", "UTF-8", $info[0]["displayname"][0]);
			$_SESSION['middleName'] = iconv("CP1251", "UTF-8", $info[0]["middlename"][0]);
			$_SESSION['telephoneNumber'] = iconv("CP1251", "UTF-8", $info[0]["telephonenumber"][0]);
			$_SESSION['mail'] = iconv("CP1251", "UTF-8", $info[0]["mail"][0]);
			$_SESSION['loginDB'] = 'ldap';
			$splitName = explode(' ', $_SESSION['displayName']);
			$_SESSION['firstName'] = $splitName[1];
			$_SESSION['secondName'] = $splitName[0];

			$n = 0;
			$group = 0;
			foreach($info[0]['memberof'] as $group) { 
				$group = iconv("CP1251", "UTF-8", $group);
				$group = substr($group, 3, strpos($group, ",") - 3);
				$adGroups[$n] = $group;
				$n++;
			} 
			foreach($adGroups as $userGroup) {
				if ($userGroup == "Администраторы домена") {
					$group = 1;
					$_SESSION['userGroups'] = 'admin';
				}
			}
			if ($group != 1) {
				$group = 1;
				$_SESSION['userGroups'] = 'client';
			}
			if ($group != 1) {
					session_destroy();
					checkLoginStatus();
					return;
			}
		}  
		ldap_unbind($adServer);
		$query = "SELECT * FROM users WHERE login = '" . $username . "';";
		$result = mysql_query($query, $link) or die("Ошибка: ".mysql_error());
		if(mysql_num_rows($result) != 1) {
			mysql_query("INSERT INTO users (firstName, secondName, middleName, login, isDisabled, rights, email, phone, contractDivisions_id, partner_id, loginDB) VALUES ('" . $_SESSION['firstName'] . "', '" . $_SESSION['secondName'] . "', '" . $_SESSION['middleName'] . "', '". $username ."', 0, '". $_SESSION['userGroups'] ."', '". $_SESSION['mail'] ."', '". $_SESSION['telephoneNumber'] ."', $mainFirmID, 0, 'ldap')", $link);
			$_SESSION['myID'] = mysql_insert_id();
		} else {
			$row = mysql_fetch_assoc($result);
			$_SESSION['myID'] = $row['id'];
			if (($_SESSION['firstName'] != $row['firstName']) or ($_SESSION['secondName'] != $row['secondName']) or ($_SESSION['middleName'] != $row['middleName']) or ($_SESSION['mail'] != $row['email']) or ($_SESSION['telephoneNumber'] != $row['phone'])) {
				mysql_query("UPDATE `users` SET  `firstName`='" . $_SESSION['firstName'] . "', `secondName`='" . $_SESSION['secondName'] . "', `middleName`='" . $_SESSION['middleName'] . "', `email`='" . $_SESSION['mail'] . "', `phone`='" . $_SESSION['telephoneNumber'] . "' WHERE id = " . $_SESSION['myID'] . ";", $link);
			}
		}
		} else {
			$query="SELECT id, login, passwordHash, isDisabled, firstName, secondName, middleName, email, phone, rights FROM users WHERE login='".mysql_real_escape_string($username)."' and passwordHash='".md5($password)."' and isDisabled = 0 limit 1";
			$result=mysql_query($query, $link) or die("Ошибка: ".mysql_error());
			if(mysql_num_rows($result) == 1) {
				$row=mysql_fetch_assoc($result);
				
				$_SESSION['myID'] = $row['id'];
				$_SESSION['username'] = $username;
				$_SESSION['firstName'] = $row['firstName'];
				$_SESSION['secondName'] =$row['secondName']; 
				$_SESSION['middleName'] = $row['middleName'];
				$_SESSION['telephoneNumber'] = $row['phone'];
				$_SESSION['mail'] = $row['email'];
				$_SESSION['userGroups'] = $row['rights'];
				$_SESSION['loginDB'] = 'mysql';				
			}
		}
	return false;
}

function checkLoginStatus() { 
	if (!isset($_SESSION['username'])) {
		header( 'Location: /', true, 307 );
	}
}

function getInterfaceName($rules) {
	switch($rules) {
		case 'client':
			$groupName = 'Заказчик';
			break;
		case 'admin':
			$groupName = 'Администратор';
			break;
		case 'operator':
			$groupName = 'Оператор';
			break;
		case 'engeneer':
			$groupName = 'Инженер';
			break;
		case 'partner':
			$groupName = 'Исполнитель';
			break;
	}
	return $groupName;
}

function getTicketMenuInterface($status, $rules) {
	switch($status) {
		case 'received':
			switch($rules) {
				case 'client':
					echo "<ul class='secondMenu'><li><a href='#' class='newTicket'><img src='/img/obsled.gif'/ class='ticket'>Создать</a></li><li><a href='#'><img src='/img/Cancel.gif'/>Отклонить</a></li><li><a href='#'><img src='/img/dload.gif'/>Выгрузить</a></li></ul>";
					break;
				case 'admin':
					echo "<ul class='secondMenu'><li><a href='#' class='newTicket'><img src='/img/obsled.gif'/>Создать</a></li><li><a href='#'><img src='/img/Update.gif'/>Принять</a></li><li><a href='#'><img src='/img/Cancel.gif'/>Отклонить</a></li><li><a href='#'><img src='/img/set.gif'/>Переназначить</a></li><li><a href='#'><img src='/img/dload.gif'/>Выгрузить</a></li></ul>";
					break;
				case 'operator':
					break;
				case 'engeneer':
					break;
				case 'partner':
					break;
			}
			break;
		case 'accepted':
			switch($rules) {
				case 'client':
					break;
				case 'admin':
					echo "<ul class='secondMenu'><li><a href='#'><img src='/img/dload.gif'/>Загрузить форму</a></li><li><a href='#'><img src='/img/Update.gif'/>Выполнено</a></li><li><a href='#'><img src='/img/time.gif'/>Перенести</a></li><li><a href='#'><img src='/img/obsled.gif'/>Форма обследования</a></li><li><a href='#'><img src='/img/set.gif'/>Переназначить</a></li><li><a href='#'><img src='/img/dload.gif'/>Выгрузить</a></li></ul>";
					break;
				case 'operator':
					break;
				case 'engeneer':
					break;
				case 'partner':
					break;
			}
			break;
		case 'planned':
			switch($rules) {
				case 'client':
					break;
				case 'admin':
					echo "<ul class='secondMenu'><li><a href='#'><img src='/img/dload.gif'/>Загрузить форму</a></li><li><a href='#'><img src='/img/Update.gif'/>Выполнено</a></li><li><a href='#'><img src='/img/self.gif'/>Запрос подтверждения</a></li><li><a href='#'><img src='/img/dload.gif'/>Выгрузить</a></li></ul>";
					break;
				case 'operator':
					break;
				case 'engeneer':
					break;
				case 'partner':
					break;
			}
			break;
		case 'closed':
			switch($rules) {
				case 'client':
					break;
				case 'admin':
					echo "<ul class='secondMenu'><li><a href='#'><img src='/img/dload.gif'/>Загрузить форму</a></li><li><a href='#'><img src='/img/Cancel.gif'/>Отменить выполнение</a></li><li><a href='#'><img src='/img/obsled.gif'/>Форма обследования</a></li><li><a href='#'><img src='/img/dload.gif'/>Выгрузить</a></li></ul>";
					break;
				case 'operator':
					break;
				case 'engeneer':
					break;
				case 'partner':
					break;
			}
			break;
		case 'canceled':
			switch($rules) {
				case 'client':
					break;
				case 'admin':
					echo "<ul class='secondMenu'><li><a href='#'><img src='/img/obsled.gif'/>Форма обследования</a></li><li><a href='#'><img src='/img/dload.gif'/>Выгрузить</a></li></ul>";
					break;
				case 'operator':
					break;
				case 'engeneer':
					break;
				case 'partner':
					break;
			}
		break;	
	}
}

function getTicketsNum($status, $divType, $divFilter, $rights, $userid) {
	global $link;
	if ($rights == 'admin') {
		$query = "	SELECT count(`request`.`id`) FROM `request`, `contractDivisions`, `contragents` WHERE `request`.`currentState` = '{$status}' AND
					`contractDivisions`.`id` = `request`.`contractDivisions_id` AND `contragents`.`id` = `contractDivisions`.`contragents_id` AND
					(". (($divType != 'n') && ($divType == 'd')?0:1) ." = 1 OR (`contractDivisions`.`id` = '{$divFilter}')) AND
					(". (($divType != 'n') && ($divType == 'g')?0:1) ." = 1 OR (`contragents`.`id` = '{$divFilter}'));";
	} elseif ($rights == 'client') {
		$query = "	SELECT count(`request`.`id`) FROM `request`, `users` WHERE `request`.`currentState` = '{$status}' AND
					`users`.`id` = '{$userid}' AND `request`.`contractDivisions_id` = `users`.`contractDivisions_id`;";
	}
	$result = mysql_query($query, $link) or die("Ошибка: ".mysql_error());
	
	return mysql_result($result, 0);
}

function dateFormat($d, $format) {
	$date = date_create($d);
	return date_format($date, $format);
}

function getTicketsList($contractDivision, $contragent, $engeneer, $contactPerson, $currentState, $service, $request, $rights, $userid) {
	global $link;
	if ($rights == 'admin') {
	$query = "SELECT `rq`.`id`, `rq`.`problem`, `rq`.`createdAt`, `rq`.`reactedAt`, `rq`.`repairBefore`, `rq`.`repairedAt`, `rq`.`currentState`, `rq`.`stateChangeAt`,
	`rq`.`slaCriticalLevels_id`,`emfg`.`name` AS `manufacturer`, `emod`.`name` as `model`, `est`.`description` as `equipmentSubType`,
	`et`.`name` as `equipmentType`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `eq`.`warrantyEnd`,
	`srv`.`name` as `serviceName`, `srv`.`shortname` as `serviceShortName`, `eng`.`id` as `engeneerID`, `eng`.`firstName` as `engeneerFirstName`, `eng`.`secondName` as `engeneerSecondName`,
	`eng`.`middleName` as `engeneerMiddleName`, `user`.`id` as `contactID`, `user`.`firstName` as `contactFirstName`, `user`.`secondName` as `contactSecondName`,
	`user`.`middleName` as `contactMiddleName`, `cntd`.`name` as `division`, `cag`.`name` as `firm`, `cagd`.`name` as `firmDiv`
	FROM `request` AS `rq`
		LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id`
		LEFT JOIN `equipmentModels` AS `emod` ON `emod`.`id` = `eq`.`equipmentModels_id`
		LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `emod`.`equipmentManufacturers_id`
		LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`id` = `emod`.`equipmentSubTypes_id`
		LEFT JOIN `equipmentTypes` AS `et` ON `et`.`id` = `est`.`equipmentTypes_id`
		LEFT JOIN `services` AS `srv` ON `srv`.`id` = `rq`.`service_id`
		LEFT JOIN `users` AS `eng` ON `eng`.`id` = `rq`.`engeneer_id`
		LEFT JOIN `users` AS `user` ON `user`.`id` = `rq`.`contactPersons_id`
		LEFT JOIN `contractDivisions` AS `cntd` ON `cntd`.`id` = `rq`.`contractDivisions_id`
		LEFT JOIN `contracts` AS `cont` ON `cont`.`id` = `cntd`.`contracts_id`
		LEFT JOIN `contragents` AS `cag` ON `cag`.`id` = `cont`.`contragents_id`
		LEFT JOIN `contragents` AS `cagd` ON `cagd`.`id` = `cntd`.`contragents_id`
	WHERE (". ($contractDivision == 0 ? 1:0) ." = 1 OR `rq`.`contractDivisions_id` = {$contractDivision}) AND
	    (". ($contragent == 0 ? 1:0) ." = 1 OR `cntd`.`contragents_id` = {$contragent} OR `cont`.`contragents_id` = {$contragent}) AND
		(". ($engeneer == 0 ? 1:0) ." = 1 OR `rq`.`engeneer_id` = {$engeneer}) AND 
		`rq`.`currentState` = '{$currentState}' AND 
		(". ($contactPerson == 0 ? 1:0) ." = 1 OR `rq`.`contactPersons_id` = {$contactPerson}) AND 
		(". ($service == 0 ? 1:0) ." = 1 OR `srv`.`id` = {$service}) AND
		(". ($request == 0 ? 1:0) ." = 1 OR `rq`.`id` = {$request}) ORDER BY `rq`.`createdAt` ASC;";
	} elseif ($rights == 'client') {
	$query = "SELECT `rq`.`id`, `rq`.`problem`, `rq`.`createdAt`, `rq`.`reactedAt`, `rq`.`repairBefore`, `rq`.`repairedAt`, `rq`.`currentState`, `rq`.`stateChangeAt`,
	`rq`.`slaCriticalLevels_id`,`emfg`.`name` AS `manufacturer`, `emod`.`name` as `model`, `est`.`description` as `equipmentSubType`,
	`et`.`name` as `equipmentType`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `eq`.`warrantyEnd`,
	`srv`.`name` as `serviceName`, `srv`.`shortname` as `serviceShortName`, `eng`.`id` as `engeneerID`, `eng`.`firstName` as `engeneerFirstName`, `eng`.`secondName` as `engeneerSecondName`,
	`eng`.`middleName` as `engeneerMiddleName`, `user`.`id` as `contactID`, `user`.`firstName` as `contactFirstName`, `user`.`secondName` as `contactSecondName`,
	`user`.`middleName` as `contactMiddleName`, `cntd`.`name` as `division`, `cag`.`name` as `firm`, `cagd`.`name` as `firmDiv`
	FROM `request` AS `rq`
		LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id`
		LEFT JOIN `equipmentModels` AS `emod` ON `emod`.`id` = `eq`.`equipmentModels_id`
		LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `emod`.`equipmentManufacturers_id`
		LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`id` = `emod`.`equipmentSubTypes_id`
		LEFT JOIN `equipmentTypes` AS `et` ON `et`.`id` = `est`.`equipmentTypes_id`
		LEFT JOIN `services` AS `srv` ON `srv`.`id` = `rq`.`service_id`
		LEFT JOIN `users` AS `eng` ON `eng`.`id` = `rq`.`engeneer_id`
		LEFT JOIN `users` AS `user` ON `user`.`id` = `rq`.`contactPersons_id`
		LEFT JOIN `contractDivisions` AS `cntd` ON `cntd`.`id` = `rq`.`contractDivisions_id`
		LEFT JOIN `contracts` AS `cont` ON `cont`.`id` = `cntd`.`contracts_id`
		LEFT JOIN `contragents` AS `cag` ON `cag`.`id` = `cont`.`contragents_id`
		LEFT JOIN `contragents` AS `cagd` ON `cagd`.`id` = `cntd`.`contragents_id`
	WHERE (0 = 1 OR (`rq`.`contractDivisions_id` = (SELECT `contractDivisions`.`id` FROM `contractDivisions`, `users` WHERE `users`.`contractDivisions_id` = `contractDivisions`.`id` AND `users`.`id` = {$userid}))) AND
	    (". ($contragent == 0 ? 1:0) ." = 1 OR `cntd`.`contragents_id` = {$contragent} OR `cont`.`contragents_id` = {$contragent}) AND
		(". ($engeneer == 0 ? 1:0) ." = 1 OR `rq`.`engeneer_id` = {$engeneer}) AND 
		`rq`.`currentState` = '{$currentState}' AND 
		(". ($contactPerson == 0 ? 1:0) ." = 1 OR `rq`.`contactPersons_id` = {$contactPerson}) AND 
		(". ($service == 0 ? 1:0) ." = 1 OR `srv`.`id` = {$service}) AND
		(". ($request == 0 ? 1:0) ." = 1 OR `rq`.`id` = {$request}) ORDER BY `rq`.`createdAt` ASC;";		
	}
		
	$result = mysql_query($query, $link) or die("Ошибка: ".mysql_error());
	if (mysql_num_rows($result) <= 0) {
		echo "<td colspan='9' align='middle'>Нет заявок в этом разделе</td>";
		return;
	}
	while ($row = mysql_fetch_assoc($result)) {
		echo "<tr><td style='text-align: center'><input type='checkbox' id='ticketNumber_".$row['id']."'></td>";
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".sprintf("%07d", $row['id'])."</td>";
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".$row['serviceShortName']."</td>";        
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".dateFormat($row['createdAt'],'d.m.Y H:i')."</td>";
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".dateFormat($row['repairBefore'],'d.m.Y H:i')."</td>";
		echo "<td style='text-align: left' class='ticket' id='".$row['id']."'>".$row['division']."</td>";
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".$row['engeneerSecondName']." ".mb_substr($row['engeneerFirstName'],0,1,'utf-8').". ".mb_substr($row['engeneerMiddleName'],0,1,'utf-8').".</td>";
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".$row['equipmentType']."</td>";
		echo "<td style='text-align: center' class='ticket' id='".$row['id']."'>".progressbar($row['createdAt'], $row['repairBefore'])."</td>";
	echo "</tr>";
	}
}

function getTicketInfo($id) {
	global $link;
	$query = "SELECT `rq`.`id`, `rq`.`problem`, `rq`.`createdAt`, `rq`.`reactedAt`, `rq`.`repairBefore`, `rq`.`repairedAt`, `rq`.`currentState`, `rq`.`stateChangeAt`,
	`rq`.`slaCriticalLevels_id`,`emfg`.`name` AS `manufacturer`, `emod`.`name` as `model`, `est`.`description` as `equipmentSubType`,
	`et`.`name` as `equipmentType`, `eq`.`serviceNumber`, `eq`.`serialNumber`, `eq`.`warrantyEnd`,
	`srv`.`name` as `serviceName`, `srv`.`shortname` as `serviceShortName`, `eng`.`id` as `engeneerID`, `eng`.`firstName` as `engeneerFirstName`, `eng`.`secondName` as `engeneerSecondName`,
	`eng`.`middleName` as `engeneerMiddleName`, `user`.`id` as `contactID`, `user`.`firstName` as `contactFirstName`, `user`.`secondName` as `contactSecondName`,
	`user`.`middleName` as `contactMiddleName`, `cntd`.`name` as `division`, `cag`.`name` as `firm`, `cagd`.`name` as `firmDiv`,
	`user`.`email` as `contactEmail`, `user`.`phone` as `contactPhone`, `cntd`.`address` as `contactAddress`
	FROM `request` AS `rq`
		LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `rq`.`equipment_id`
		LEFT JOIN `equipmentModels` AS `emod` ON `emod`.`id` = `eq`.`equipmentModels_id`
		LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `emod`.`equipmentManufacturers_id`
		LEFT JOIN `equipmentSubTypes` AS `est` ON `est`.`id` = `emod`.`equipmentSubTypes_id`
		LEFT JOIN `equipmentTypes` AS `et` ON `et`.`id` = `est`.`equipmentTypes_id`
		LEFT JOIN `services` AS `srv` ON `srv`.`id` = `rq`.`service_id`
		LEFT JOIN `users` AS `eng` ON `eng`.`id` = `rq`.`engeneer_id`
		LEFT JOIN `users` AS `user` ON `user`.`id` = `rq`.`contactPersons_id`
		LEFT JOIN `contractDivisions` AS `cntd` ON `cntd`.`id` = `rq`.`contractDivisions_id`
		LEFT JOIN `contracts` AS `cont` ON `cont`.`id` = `cntd`.`contracts_id`
		LEFT JOIN `contragents` AS `cag` ON `cag`.`id` = `cont`.`contragents_id`
		LEFT JOIN `contragents` AS `cagd` ON `cagd`.`id` = `cntd`.`contragents_id`
	WHERE `rq`.`id` = {$id};";
	$result = mysql_query($query, $link) or die("Ошибка: ".mysql_error());
	return mysql_fetch_array($result);
}

function progressbar($createdAt, $repairBefore) {
    $dateNow = time();
	$dateCreatedAt = strtotime($createdAt);
	$dateRepairBefore = strtotime($repairBefore);
	$dateDiff = ($dateRepairBefore - $dateCreatedAt);
	
	if ($dateNow < $dateCreatedAt) $status = 'empty';
	if ($dateNow > $dateRepairBefore) $status = 'full';
	if (($dateCreatedAt <= $dateNow) && ($dateNow <= $dateRepairBefore)) $status = 'progress';
	switch($status) {
		case 'empty':
			$barwidth = 0;
			$barcolor = 'green';
			$bordercolor = 'green';
		break;
		case 'full':
			$barwidth = 98;
			$barcolor = 'red';
			$bordercolor = 'red';
		break;
		case 'progress':
			$percent = $dateDiff/100;
			$datelast = ($dateNow - $dateCreatedAt);
			$percentlast = floor($datelast*100)/$dateDiff;
			$barwidth = floor((98*$percentlast)/100);
			if ((1 <= $barwidth) && ($barwidth <= 49)) {
				$barcolor = 'green';
				$bordercolor = 'green';
			}
			if ((50 <= $barwidth) && ($barwidth <= 97)) {
				$barcolor = 'orange';
				$bordercolor = 'orange';
			}
		break;
	}
	return "<div style='width:90%;height:8px;background-color:white; border:1px solid {$bordercolor};'><div style='margin:1px; width:{$barwidth}%;height:6px;background-color:{$barcolor};'></div>	</div>";
}

function getFilterOptions($userid, $limit, $filterType) {
	global $link;	
	$group = '';
	switch($filterType) {
		case 'division':
			$query = 	"SELECT `contractDivisions`.`id` AS `division_id`, `contragents`.`id` as `contragents_id`, `contractDivisions`.`name` AS `division`, `contragents`.`name` AS `contragent`,
						(SELECT count(`division`.`id`) FROM `contractDivisions` AS `division` WHERE `division`.`contragents_id` = `contragents`.`id`)  AS `division_num`
						FROM `contractDivisions`, `contragents`, `contracts`, `users`
						WHERE `contractDivisions`.`contracts_id` = `contracts`.`id` AND `contragents`.`id` = `contracts`.`contragents_id`
						AND (".($limit == 0?0:1)." = 1 OR (`users`.`id` = {$userid} AND`users`.`contractDivisions_id` = `contractDivisions`.`id`))
						AND (1 = 1 OR (SELECT NOW()) BETWEEN (`contracts`.`contractStart`) AND `contracts`.`contractEnd`)
						GROUP BY `contragent` ASC, `division` ASC;";
			$result = mysql_query($query, $link) or die("Ошибка: ".mysql_error());
			echo "<select class='dropMenu' id='selectDivision'> onchange='getval(this);'";
			echo "<option value='n0' selected>&#160;&#160;&#160;&#160;--- Все --- </option>";
			while ($row = mysql_fetch_assoc($result)) {
				if (($row['contragent'] != $group) && ($row['division_num'] > 1)) {
					echo "<option value='g{$row['contragents_id']}'>[{$row['contragent']}]</option>";
					echo "<option value='d{$row['division_id']}'>&#160;&#160;&#160;&#160;{$row['division']}</option>";
					$group = $row['contragent'];
				} elseif (($row['contragent'] != $group) && ($row['division_num'] == 1)) {
					echo "<option value='d{$row['contragents_id']}'>[{$row['contragent']}]</option>";
				} elseif (($row['contragent'] == $group) && ($row['division_num'] > 1)) {
					echo "<option value='d{$row['division_id']}'>&#160;&#160;&#160;&#160;{$row['division']}</option>";
				}
			}
			break;
		case 'services':
			$query = "";
			break;
	}	
}

function getEquipmentInfo($equipment) {
	global $link;
	$query = 	"SELECT equipment.serialNumber AS `sn`, equipment.onService AS `onSrv`, equipment.contractDivisions_id AS `div`, equipmentManufacturers.name AS `brand`,
				equipmentModels.name AS `model`, equipmentSubTypes.description AS `type`
				FROM equipment, equipmentManufacturers, equipmentModels, equipmentSubTypes
				WHERE equipmentModels.id = equipment.equipmentModels_id AND equipmentManufacturers.id = equipmentModels.equipmentManufacturers_id AND
				equipmentSubTypes.id = equipmentModels.equipmentSubTypes_id AND equipment.serviceNumber = {$equipment};";
	$result = mysql_query($query, $link) or die("Ошибка: ".mysql_error());
	return mysql_fetch_assoc($result);
}

?>