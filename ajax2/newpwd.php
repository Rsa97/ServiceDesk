<?php
  session_start();
  include 'config/db.php';
  
  if (isset($_REQUEST['op']) && $_REQUEST['op'] == 'cp') {
  	$mode = 'cp';
    header('Content-Type: application/json; charset=UTF-8');
  } else if (isset($_REQUEST['i']) && isset($_REQUEST['h']))
  	$mode = 'init';
  else
  	exit;  
  $intErr = "<!DOCTYPE html><head><meta http-equiv='Content-Language' content='ru'>".
    		"<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>".
    		"<title>Служба технической поддержки, Компания Со-действие</title>".
    		"</head><body><h1>Внутренняя ошибка сайта. Попробуйте позднее.</h1></body></html>"; 

  $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
  if ($mysqli->connect_error) {
  	if ($mode == 'op')
	  echo json_encode(array('error' => "Внутренняя ошибка сайта."));  		
  	else
      echo $intErr;
	exit;
  }
  $mysqli->query("SET NAMES utf8");
  
  if ($mode == 'cp') {
  	if (!isset($_REQUEST['newpass']) || !isset($_SESSION['user']) || !isset($_SESSION['user']['myID']) ||
	  !isset($_SESSION['private']))
	  exit;
	openssl_private_decrypt(base64_decode($_POST['newpass']), $newpass, $_SESSION['private']);
 	if ($newpass == '' || strlen($newpass) < 6) {
	  exit;
	}
	$req = $mysqli->prepare("SELECT `login`, `firstName`, `secondName`, `middleName`, `rights`, `email`, `phone`, `address`, `partner_id` ".
                            "FROM `users` WHERE `id` = ?");
	$req->bind_param('i', $_SESSION['user']['myID']);
	$req->bind_result($user, $fName, $lName, $mName, $rights, $email, $phone, $address, $partner);
  	if (!$req->execute()) {
      echo json_encode(array('error' => "Внутренняя ошибка сайта."));
	  exit;
  	}
	$req->fetch();
	$req->close();
  	$newHash = md5($newpass.$user."reppep");
    $mysqli->query("UPDATE `users` SET `passwordHash` = '{$newHash}' WHERE `id` = {$_SESSION['user']['myID']}");
  	$_SESSION['user'] = array(
  		'username'   => $user,
        'myID'       => $_SESSION['user']['myID'],
        'firstName'  => $fName,
        'lastName'   => $lName,
        'middleName' => $mName,
        'phone'      => $phone,
        'mail'       => $email,
      	'rights'     => $rights,
        'address'    => $address,
        'partner'    => $partner,
        'fromLdap'   => 0);
	echo json_encode(array('redirect' => "/desktop.html"));
	exit;
  }
  
  $ok = 0;
  $req = $mysqli->prepare("SELECT COUNT(*) FROM `users` WHERE `id` = ? AND `passwordHash` = ?");
  $req->bind_param('is', $_REQUEST['i'],$_REQUEST['h']);
  $req->bind_result($ok);
  if (!$req->execute()) {
  	echo $intErr;
  }
  $req->fetch();
  $req->close();
  
  if ($ok != 1)
  	return;
  
  $_SESSION['user'] = array('myID' => $_REQUEST['i']); 

  $config = array(
	"private_key_bits" => 1024,
	"private_key_type" => OPENSSL_KEYTYPE_RSA
  );
  $res = openssl_pkey_new($config);
  openssl_pkey_export($res, $private);
  $_SESSION['private'] = $private;
  $public = openssl_pkey_get_details($res);
  $_SESSION['public'] = $public['key'];
  
?>
<html>
  <head>
    <meta http-equiv='Content-Language' content='ru'>
    <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
    <title>Служба технической поддержки, Компания Со-действие</title>
    <link rel='stylesheet' type='text/css' href='css/index.css'>
    <script type='text/javascript' src='js/jquery.js'></script>
    <script type='text/javascript' src='js/rsa.js'></script>
  </head>
  <body>
	  <div class='head'>
	    <img src='img/color_web.jpg' alt='Изображение отсутствует' width='368px' height='222px'>
    	<h1>Информационная система технической поддержки</h1> 
    	<h2>Единый телефон технической поддержки<br>8 (8212) 214-808</h2>
        <div class='login'>
    	  <h3>Введите новый пароль</h3>
        	<input type='hidden' id='key' value='<?php echo $_SESSION['public']; ?>'>
    		<label><span>Новый</span><input type='password' id='newpasswd' placeholder='Новый пароль' autofocus></label><br>
    		<label><span>пароль:</span><input type='password' id='checkpasswd' placeholder='Повторите новый пароль'></label><br>
    		<input type='button' id='login' value='Войти'>
        </div>
	  </div>
    <script type='text/javascript' src='js/newpass.js'></script>
  </body>
</html>
