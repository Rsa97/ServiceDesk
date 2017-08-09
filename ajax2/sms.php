<?php
define('DUTYDB_HOST', '10.149.0.204');
define('DUTYDB_NAME', 'asterisk');
define('DUTYDB_LOGIN', 'asterisk');
define('DUTYDB_PASS', 'grand8');

function send_sms($message, $cellphone) {
	global $dbName;
	if ('sd' != $dbName) {
		error_log("Send SMS '{$message}' to {$cellphone}");
		return;
	}
	$curl = curl_init('http://10.149.0.204/sms.php');
    curl_setopt_array($curl, array(
		CURLOPT_HEADER         => false,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_ENCODING       => "gzip,deflate",
		CURLOPT_USERAGENT      => "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:50.0) Gecko/20100101 Firefox/50.0",
		CURLOPT_AUTOREFERER    => true,
		CURLOPT_CUSTOMREQUEST  => "POST",
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POSTFIELDS	   => array('num' => $cellphone, 'pass' => 'rhzrjpz,hf', 'msg' => $message)
    ));
    $ret = curl_exec($curl);
}

function sms_to_duty($message) {
	try {
		$db = new PDO('mysql:host='.DUTYDB_HOST.';dbname='.DUTYDB_NAME.';charset=UTF8;', DUTYDB_LOGIN, DUTYDB_PASS,
				  	array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	} catch (PDOException $e) {
		print("Ошибка подключения к MySQL ".$e->getMessage()."\n");
		exit;
	}
	$db->exec("SET NAMES utf8");
	
	$cellphone = '';
	try {
		$req = $db->prepare("SELECT `CellPhone` ".
								"FROM `Mobiles` ".
								"WHERE `Internal` = '711'");
		$req->execute();
	} catch (PDOException $e) {
		return("Ошибка MySQL ".$e->getMessage()."\n");
	}
	if ($row = $req->fetch(PDO::FETCH_ASSOC))
		$cellphone = $row['CellPhone'];
	
	try {
		$req = $db->prepare("SELECT `CellPhone`, `CellPhone2` ".
								"FROM `ShiftEngeneer` ".
								"WHERE NOW() BETWEEN `FromDate` AND DATE_ADD(`ToDate`, INTERVAL 1 DAY) ".
								"ORDER BY `FromDate` DESC ".
								"LIMIT 1");
		$req->execute();
	} catch (PDOException $e) {
		return("Ошибка MySQL ".$e->getMessage()."\n");
	} 
	$time = localtime(time(), true);
	if ($row = $req->fetch(PDO::FETCH_ASSOC))
		$cellphone = ($time['tm_hour'] < 11 ? $row['CellPhone'] : $row['CellPhone2']);
	if ('' == $cellphone)
		return null;
	$cellphone = preg_replace('/^8/', '7', $cellphone);
	send_sms($message, $cellphone);
	return;
}
?>