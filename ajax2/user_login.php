<?php
header('Content-Type: application/json; charset=UTF-8');

include_once '../config/db.php';
include_once '../config/ldap.php';
include_once 'common.php';
session_start();

if (!isset($paramValues['name']) || $paramValues['name'] == '') {
	echo json_encode(array('error' => 'Не указано имя пользователя.'));
	exit;
}
$pass = '';
if (isset($paramValues['pass']))
	openssl_private_decrypt(base64_decode($paramValues['pass']), $pass, $_SESSION['private']);
if ($pass == '') {
	echo json_encode(array('error' => 'Не указан пароль.'));
	exit;
}
$newpass = '';
if (isset($paramValues['change']) && $paramValues['change'] == 1 && isset($paramValues['newpass'])) {
	openssl_private_decrypt(base64_decode($paramValues['newpass']), $newpass, $_SESSION['private']);
	if ($newpass == '' || strlen($newpass) < 6) {
		echo json_encode(array('error' => 'Ошибка в запросе.'));
		exit;
	}
}
$user = $paramValues['name'];

unset($_SESSION['private']);
unset($_SESSION['public']);

// Подключаемся к MySQL
try {
	$db = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass);
} catch (PDOException $e) {
	ldap_close($ldap);
	echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
							'orig' => "MySQL connection error".$e->getMessage()));
	exit;
}
$db->exec("SET NAMES utf8");

if (preg_match('~(\S+)@(\S+)~', $user, $match)) {
	$user = $match[1];
	$domain = $match[2];
} elseif (preg_match('~(\S+)\x5C(\S+)~', $user, $match)) {
	$user = $match[2];
	$domain = $match[1];
} else
	$domain = $defaultDomain;
$ldap_username = "{$user}@{$domain}";

// Пытаемся найти пользователя в LDAP
$fromLdap = 0;
foreach ($ldapServers as $ldapServer)
	if (($ldap = ldap_connect($ldapServer)))
		break;
if (!$ldap) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
							'orig' => 'LDAP connection error'));
	exit;
}
ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

$errno = 0;
if (!($ldapbind = ldap_bind($ldap, $ldap_username, $pass)) && ($errno = ldap_errno($ldap)) != 49) {
	$errstr = ldap_error($ldap);
	ldap_close($ldap);
	echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
							'orig' => "LDAP error ({$errno}) {$errstr}"));
	exit;
}

if ($errno != 49) {
	$fromLdap = 1;
	if (!($res = ldap_search($ldap, $userBase, "({$userAttr}={$user})", array_values($attrMapping)))) {
		$errno = ldap_errno($ldap);
		$errstr = ldap_error($ldap);
		ldap_close($ldap);
		echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
								'orig' => "LDAP search error ({$errno}) {$errstr}"));
		exit;
	}
	if (!($info = ldap_first_entry($ldap, $res)) || !($attrs = ldap_get_attributes($ldap, $info))) {
		ldap_close($ldap);
		echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
								'orig' => "User '{$user}' not found in LDAP"));
		exit;
	}
	$ret = array();
	$ret['rights'] = 'client';
	foreach ($attrMapping as $key => $val)
		$ret[$key] = $attrs[$val][0];

	foreach ($filters as $rights => $filter) {
		if (!($res = ldap_search($ldap, $userBase, "(&({$filter})({$userAttr}={$user}))", array($userAttr)))) {
			$errno = ldap_errno($ldap);
			$errstr = ldap_error($ldap);
			ldap_close($ldap);
			echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
									'orig' => "LDAP search error ({$errno}) {$errstr}"));
			exit;
		}
		if (ldap_count_entries($ldap, $res) > 0)
			$ret['rights'] = $rights;
	}

	$phone = explode(',', $ret['phone']);
	if (isset($phone[0])) {
		if (mb_strlen($phone[0]) == 3 && mb_substr($phone[0], -1) != '0')
       		$phone = "8(8212)202974, доб.{$phone[0]}";
		else 
			$phone = $phone[0];
	} else
		$phone = "8(8212)214808";

// Заносим найденного пользователя в MySQL
	$newHash = md5($pass.$user."reppep");
	try {
		$req = $db->prepare("UPDATE `users` SET `firstName` = :fName, `lastName` = :lName, `middleName` = :mName, ".
												"`passwordHash` = :hash, `isDisabled` = 0, `rights` = :rights, ".
												"`email` = :email, `phone` = :phone, `partner_guid` = NULL, `loginDB` = 'ldap' ".
								"WHERE `login` = :user");
		$req->execute(array('fName' => $ret['firstName'], 'lName' => $ret['lastName'], 'mName' => $ret['middleName'],
							'user' => $user, 'hash' => $newHash, 'rights' => $ret['rights'], 'email' => $ret['mail'], 
							'phone' => $phone));
	} catch (PDOException $e) {
		ldap_close($ldap);
		echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}
	$fromLdap = 1;
}
ldap_close($ldap);

// Выбираем пользователя из базы
$oldHash = md5($pass);
$newHash = md5($pass.$user."reppep");

try {
	$req = $db->prepare("SELECT `guid`, `lastName`, `firstName`, `middleName`, `rights`, `email`, `phone`, `address`, ".
								"`partner_guid`, `passwordHash` ".
							"FROM `users` WHERE `login` = :user AND `isDisabled` = 0 ".
								"AND (`passwordHash` = :pass OR `passwordHash` = :oldHash OR `passwordHash` = :newHash)");
	$req->execute(array('user' => $user, 'pass' => $pass, 'oldHash' => $oldHash, 'newHash' => $newHash));
} catch (PDOException $e) {
	echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
							'orig' => "MySQL error".$e->getMessage()));
	exit;
}

if (!($row = $req->fetch(PDO::FETCH_ASSOC))) {
	echo json_encode(array('error' => 'Неверное имя пользователя или пароль'));
	exit;
}

$guid = formatGuid($row['guid']);

if ($fromLdap == 0 && $newpass != '')
 	$newHash = md5($newpass.$user.'reppep');
if ($row['passwordHash'] != $newHash)
	$db->exec("UPDATE `users` SET `passwordHash` = '{$newHash}' WHERE `guid` = UNHEX(REPLACE({$row['guid']}, '-', ''))");
$_SESSION['user'] = array('username'   => $user,
						  'myID'       => $guid,
						  'firstName'  => $row['firstName'],
						  'lastName'   => $row['lastName'],
						  'middleName' => $row['middleName'],
						  'phone'      => $row['phone'],
						  'mail'       => $row['email'],
						  'rights'     => $row['rights'],
						  'address'    => $row['address'],
						  'partner'    => formatGuid($row['partner_guid']),
						  'fromLdap'   => $fromLdap);
echo json_encode(array('redirect' => '/desktop.html'));
?>