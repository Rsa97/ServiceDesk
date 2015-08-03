<?php
header('Content-Type: application/json; charset=UTF-8');

include '../config/db.php';
include '../config/ldap.php';
session_start();

//=====================================================================
// Вход пользователя в систему
//=====================================================================
function login($user, $pass, $newpass) {
  global $dbHost, $dbUser, $dbPass, $dbName, $mainFirmID;                   // Параметры базы данных
  global $defaultDomain, $ldapServers, $userBase, $attrMapping, $userAttr;  // Параметры ldap
  global $adminFilter, $operFilter, $engeneerFilter, $userFilter;
  // Формируем доменное имя
  if (preg_match('~(\S+)@(\S+)~', $user, $match)) {
    $user = $match[1];
    $domain = $match[2];
	} elseif (preg_match('~(\S+)\x5C(\S+)~', $user, $match)) {
    $user = $match[2];
    $domain = $match[1];
  } else
	$domain = $defaultDomain;
  $ldap_username = "{$user}@{$domain}";

  // Подключаемся к MySQL
  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error)
    return array('error' => "MySQL Connection error ({$mysqli->connect_errno})  {$mysqli->connect_error}");
  $mysqli->query("SET NAMES utf8");
  $fromLdap = 0;
  // Пытаемся найти пользователя в LDAP
  foreach ($ldapServers as $ldapServer)
    if (($ldap = ldap_connect($ldapServer)))
      break;
  if (!$ldap) {
    $mysqli->close();
    return array('error' => "LDAP Connection error");
  }
  ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
  $errno = 0;
	if (!($ldapbind = ldap_bind($ldap, $ldap_username, $pass)) && ($errno = ldap_errno($ldap)) != 49) {
    $mysqli->close();
    $errstr = ldap_error($ldap);
    ldap_close($ldap);
    return array('error' => "LDAP errop ({$errno}) {$errstr}");
  }
  if ($errno != 49) {
  	$fromLdap = 1;
    if (!($res = ldap_search($ldap, $userBase, "({$userAttr}={$user})", array_values($attrMapping)))) {
      $mysqli->close();
      $errno = ldap_errno($ldap);
      $errstr = ldap_error($ldap);
      ldap_close($ldap);
      return array('error' => "LDAP search error ({$errno}) {$errstr}");
    }
    if (!($info = ldap_first_entry($ldap, $res)) || !($attrs = ldap_get_attributes($ldap, $info))) {
      $mysqli->close();
      ldap_close($ldap);
      return array('error' => "User {$user} not found in LDAP");
    }
    $ret = array();
    $ret['rights'] = 'client';
    foreach ($attrMapping as $key => $val) {
      $ret[$key] = $attrs[$val][0];
    }
    if (!($res = ldap_search($ldap, $userBase, "(&({$userFilter})({$userAttr}={$user}))", array($userAttr)))) {
      $mysqli->close();
      $errno = ldap_errno($ldap);
      $errstr = ldap_error($ldap);
      ldap_close($ldap);
      return array('error' => "LDAP search error ({$errno}) {$errstr}");
    }
    if (ldap_count_entries($ldap, $res) > 0) {
       $ret['rights'] = 'client';
    }
    if (!($res = ldap_search($ldap, $userBase, "(&({$operFilter})({$userAttr}={$user}))", array($userAttr)))) {
      $mysqli->close();
      $errno = ldap_errno($ldap);
      $errstr = ldap_error($ldap);
      ldap_close($ldap);
      return array('error' => "LDAP search error ({$errno}) {$errstr}");
    }
    if (ldap_count_entries($ldap, $res) > 0) {
       $ret['rights'] = 'operator';
    }
    if (!($res = ldap_search($ldap, $userBase, "(&({$engeneerFilter})({$userAttr}={$user}))", array($userAttr)))) {
      $mysqli->close();
      $errno = ldap_errno($ldap);
      $errstr = ldap_error($ldap);
      ldap_close($ldap);
      return array('error' => "LDAP search error ({$errno}) {$errstr}");
    }
    if (ldap_count_entries($ldap, $res) > 0) {
       $ret['rights'] = 'engeneer';
    }
    if (!($res = ldap_search($ldap, $userBase, "(&({$adminFilter})({$userAttr}={$user}))", array($userAttr)))) {
      $mysqli->close();
      $errno = ldap_errno($ldap);
      $errstr = ldap_error($ldap);
      ldap_close($ldap);
      return array('error' => "LDAP search error ({$errno}) {$errstr}");
    }
    if (ldap_count_entries($ldap, $res) > 0) {
       $ret['rights'] = 'admin';
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
    $req = $mysqli->prepare("INSERT INTO `users` (`firstName`, `secondName`, `middleName`, `login`, `passwordHash`, `isDisabled`, `rights`, `email`, `phone`, ".
                                                 "`loginDB`) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'ldap')".
                            "ON DUPLICATE KEY UPDATE `firstName` = VALUES(`firstName`), `secondName` = VALUES(`secondName`), `middleName` = VALUES(`middleName`), ".
                                                    "`login` = VALUES(`login`), `passwordHash` = VALUES(`passwordHash`), `isDisabled` = 0, `rights` = VALUES(`rights`), ".
                                                    "`email` = VALUES(`email`), `phone` = VALUES(`phone`), `partner_id` = NULL, `loginDB` = 'ldap'");
    $newHash = md5($pass.$user."reppep");
    $req->bind_param('ssssssss', $ret['firstName'], $ret['lastName'], $ret['middleName'], $user, $newHash, $ret['rights'], $ret['mail'], $phone);
    if (!$req->execute()) {
      $err = "MySQL error ({$mysqli->errno}) {$mysqli->error}";
      $req->close();
      $mysqli->close();
      return array('error' => $err);
    }
    $req->close();
    $fromLdap = 1;
  }
  ldap_close($ldap);
  
  // Выбираем пользователя из базы
  $req = $mysqli->prepare("SELECT `id`, `firstName`, `secondName`, `middleName`, `rights`, `email`, `phone`, `address`, `partner_id`, `passwordHash` ".
                            "FROM `users` WHERE `login` = ? AND (`passwordHash` = ? OR `passwordHash` = ? OR `passwordHash` = ?) AND `isDisabled` = 0");
  $oldHash = md5($pass);
  $newHash = md5($pass.$user."reppep");
  $req->bind_param('ssss', $user, $pass, $oldHash, $newHash);
  $req->bind_result($id, $fName, $lName, $mName, $rights, $email, $phone, $address, $partner, $hash);
  if (!$req->execute()) {
    $err = "MySQL error ({$mysqli->errno}) {$mysqli->error}";
    $req->close();
    $mysqli->close();
    return array('error' => $err);
  }
  if (!$req->fetch()) {
    $req->close();
    return array('error' => 'Incorrect user or password', 'msg' => 'Неверное имя пользователя или пароль');
  }
  $req->close();
  if ($fromLdap == 0 && $newpass != '')
  	$newHash = md5($newpass.$user."reppep");
  if ($hash != $newHash)
  	$mysqli->query("UPDATE `users` SET `passwordHash` = '{$newHash}' WHERE `id` = {$id}");
  $ret = array('username'   => $user,
               'myID'       => $id,
               'firstName'  => $fName,
               'lastName'   => $lName,
               'middleName' => $mName,
               'phone'      => $phone,
               'mail'       => $email,
      		   'rights'     => $rights,
               'address'    => $address,
               'partner'    => $partner,
               'fromLdap'   => $fromLdap);
  $mysqli->close();
  return array('user' => $ret);
}


//=====================================================================
// Основная часть
//=====================================================================
  $err = "";
  if (!isset($_SESSION['private']) || !isset($_SESSION['public'])) {
	$config = array(
	"private_key_bits" => 1024,
	"private_key_type" => OPENSSL_KEYTYPE_RSA
	);
	$res = openssl_pkey_new($config);
    openssl_pkey_export($res, $private);
	$_SESSION['private'] = $private;
	$public = openssl_pkey_get_details($res);
	$_SESSION['public'] = $public['key'];
  }
  if (!isset($_POST['Op'])) {
    echo json_encode(array('key' => $_SESSION['public']));
    exit;
  }

  switch ($_POST['Op']) {
    case 'in':
      if (!isset($_POST['user']) || $_POST['user'] == '') {
        echo json_encode(array('error' => 'Не указано имя пользователя.'));
        exit;
	  }
      $pass = '';
      if (isset($_POST['pass']))
   	    openssl_private_decrypt(base64_decode($_POST['pass']), $pass, $_SESSION['private']);
 	  if ($pass == '') {
 	    if (isset($_REQUEST['x']) && $_REQUEST['x'] == 0) {
		  echo json_encode(array('retry' => $_SESSION['public']));
          exit;
		}
        echo json_encode(array('error' => 'Не указан пароль.'));
		exit;
      }
	  $newpass = '';
	  if (isset($_REQUEST['change']) && $_REQUEST['change'] == 1 && isset($_REQUEST['newpass'])) {
		openssl_private_decrypt(base64_decode($_POST['newpass']), $newpass, $_SESSION['private']);
 	    if ($newpass == '' || strlen($newpass) < 6) {
          echo json_encode(array('error' => 'Ошибка в запросе.'));
          exit;
		}
  	  }
      $ret = login($_POST['user'], $pass, $newpass);
      if (isset($ret['error'])) {
        if (isset($ret['msg']))
          echo json_encode(array('error' => $ret['msg']));
        else
          echo json_encode(array('error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 'orig' => $ret['error']));
        exit;
      }
      $_SESSION['user'] = $ret['user'];
      echo json_encode(array('redirect' => "/desktop.html"));
      exit;
    case 'out':
      session_destroy();
      echo json_encode(array('redirect' => "/index.html"));
      break;
  }
?>
