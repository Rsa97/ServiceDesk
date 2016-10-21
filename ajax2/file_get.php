<?php
include '../config/db.php';
include '../config/files.php';

session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['filter'])) {
   	echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
	exit;
}

// Строим фильтр разрешения доступа
$rights = $_SESSION['user']['rights'];
$userGuid =  $_SESSION['user']['myID'];
$partnerGuid = $_SESSION['user']['partner'];

$_SESSION['time'] = time();
session_commit();

// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass);
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
$db->exec("SET NAMES utf8");

$byClient = ($rights == 'client' ? 1 : 0);
$byPartner = ($rights == 'partner' ? 1 : 0);
$byActive = ($rights == 'admin' ? 0 : 1);

// Получаем документ с проверкой прав
try {
	$req = $db->prepare("SELECT `d`.`name`, `d`.`uniqueName`, `rq`.`id` ".
							"FROM `documents` AS `d` ".
							"JOIN `requestEvents` AS `ev` ON `ev`.`id` = `d`.`requestEvent_id` ".
							"JOIN `requests` AS `rq` ON `rq`.`guid` = `ev`.`request_guid` ".
							"LEFT JOIN `contractDivisions` AS `div` ON `div`.`guid` = `contractDivision_guid` ".
							"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ".
							"LEFT JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
							"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `c`.`guid` ".
							"LEFT JOIN `partnerDivisions` AS `ac` ON `ac`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
							"WHERE `d`.`guid` = UNHEX(REPLACE(:docGuid, '-', '')) AND `rq`.`id` = :reqNum ".
//	            				"AND (:byActive = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
    	        				"AND (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
        	    					"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `ac`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
	$req->execute(array('docGuid' => $paramValues['docGuid'], 'reqNum' => $paramValues['n'], 'byClient' => $byClient, 
						'userGuid' => $userGuid, 'byPartner' => $byPartner, 'partnerGuid' => $partnerGuid));
	if (!($row = $req->fetch(PDO::FETCH_NUM))) {
		header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
		exit;
	}
} catch (PDOException $e) {
	header("{$_SERVER['SERVER_PROTOCOL']} Internal Server Error");
	exit;
}
list($fileName, $fileUName, $reqNum) = $row;

$file = "{$fileStorage}/{$reqNum}/{$fileUName}";
if ($fileUName == '' || !file_exists($file) || ($fileSize = filesize($file)) == 0) {
	header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
	exit;
}
$fileName = str_replace('+', '%20', urlencode($fileName));
$contentType = mime_content_type($file);
header("Content-Type: {$contentType}");
header("Content-Transfer-Encoding: binary");
header("Content-Disposition: attachment; filename*=UTF-8''{$fileName}");
header("Content-Length: {$fileSize}");
header("X-Unique-File-Name: {$file}");
ob_clean();
flush();
readfile($file);
exit;

?>