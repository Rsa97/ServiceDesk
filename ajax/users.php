<?php
	header('Content-Type: application/json; charset=UTF-8');
	include('../config/db.php');
	include('smtp.php');
	include('genderByName.php');
	$pwchars = array('2', '3', '4', '6', '7', '8', '9', 'a', 'b', 
					 'c', 'd', 'e', 'f', 'g', 'h', 'j', 'k', 'm',
					 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v',
					 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'E', 'F',
					 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R',
					 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '!', '@',
					 '#', '$', '%', '^', '&', '*'); 
	$passLength = 8;
	$rightArr = array('admin' => 'Администратор', 'operator' => 'Оператор', 'engeneer' => 'Инженер', 'partner' => 'Партнёр', 'client' => 'Клиент');
	session_start();
	if (!isset($_SESSION['user'])) {
		echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
	if ($_SESSION['user']['rights'] != 'admin') {
		echo json_encode(array('error' => 'Недостаточно прав для администрирования.', 'redirect' => '/index.html'));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	if (!isset($_REQUEST['call'])) {
		echo json_encode(array('error' => 'Ошибка в параметрах.'));
		exit;
	}
	// Подключаемся к MySQL
	$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	if ($mysqli->connect_error) {
		trigger_error("Ошибка подключения к серверу MySQL ({$mysqli->connect_errno})  {$mysqli->connect_error}");
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$ret = array();
	$id = 0;
	$password = '';
	switch($_REQUEST['call']) {
		case 'init':
			break;
		case 'switchUserLock':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE IGNORE `users` SET `isDisabled` =  NOT(`isDisabled`) WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		case 'changePwd':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0 ||
				!isset($_REQUEST['login']) || ($login = $_REQUEST['login']) == '') {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("SELECT `login`, `email`, `firstName`, `secondName`, `middleName` FROM `users` WHERE `id` = ?");
			$req->bind_param('i', $id);
			$req->bind_result($user, $email, $givenName, $familyName, $middleName);
			if (!$req->execute() || !$req->fetch() || $user != $login) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			shuffle($pwchars);
			$password = implode(array_slice($pwchars, 0, $passLength));
			$hash = md5($password.$user."dwPwen");
			$req = $mysqli->prepare("UPDATE IGNORE `users` SET `passwordHash` =  ? WHERE `id` = ?");
			$req->bind_param('si', $hash, $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			$to = $familyName.($givenName != '' ? (' '.mb_substr($givenName, 0, 1, 'utf-8').'.'.($middleName != '' ? (' '.mb_substr($middleName, 0, 1, 'utf-8').'.') : '')) : '');
			$to1 = $givenName.(($givenName != '' && $middleName != '') ? ' ' : '').$middleName; 
			$gender = (genderByNames($givenName, $middleName, $familyName) >= 0 ? 'ый' : 'ая');
			$msg = compose_mail("Информационное сообщение от службы технической поддержки «Со-Действие»\r\n".
    							"Уважаем{$gender} {$to1}, ваш пароль был сброшен.\r\n".
    							"\r\n".
    							"Для задания нового пароля перейдите по ссылке\r\n".
    							"http://sd.sodrk.ru/newpwd.php?i={$id}&h={$hash}\r\n".
    							"\r\n".
    							"----\r\n".
    							"С уважением, служба технической поддержки «Со-Действие».\r\n".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.",
								"<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>".
								"<style><!--".
  								".header { font-size: 1.1em; font-weight: bold; } ".
  								".login { font-weight: bold; font-style: italic; } ".
								"--></style>".
								"<div dir='ltr'>".
    							"<span class='header'>Информационное сообщение от службы технической поддержки «Со-Действие»</span>".
    							"<p>Уважаем{$gender} {$to1}, ваш пароль был сброшен.".
    							"<p>".
    							"Для задания нового пароля перейдите по ссылке<br>".
    							"<a href='http://sd.sodrk.ru/newpwd.php?i={$id}&h=${hash}'>http://sd.sodrk.ru/newpwd.php?i={$id}&h={$hash}</a>".
    							"<p>".
    							"<p>----<br>".
    							"С уважением, служба технической поддержки «Со-Действие».<br>".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
								"</div>"
				);
			
			if (smtpmail($email, $to, "Ваш пароль в сервисдеске «Со-Действие» был сброшен", $msg['body'], $msg['header']))
				echo json_encode(array('message' => 'Пароль изменён, сообщение отправлено по электронной почте.'));
			else 
				echo json_encode(array('message' => "Пароль изменён, сообщение отправить не удалось.\nСсылка: http://sd.sodrk.ru/newpwd.php?i={$id}&h={$hash}"));
			exit;
			break;
		case 'partnersList':
			$req = $mysqli->prepare("SELECT `id`, `name` FROM `partner` WHERE `id` > 0 ORDER BY `name`");
			$req->bind_result($id, $name);
			$list = '';
			if ($req->execute()) {
				while($req->fetch()) {
					$list .= "<option value='{$id}'>".htmlspecialchars($name);
				}
			}
			$req->close();
			$ret['org'] = $list;
			echo json_encode($ret);
			exit;
			break;
		case 'updateUser':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
		case 'addUser':
			$partner = 0;
			if (!isset($_REQUEST['givenName']) || ($givenName = $_REQUEST['givenName']) == '' ||
				!isset($_REQUEST['familyName']) || ($familyName = $_REQUEST['familyName']) == '' ||
				!isset($_REQUEST['middleName']) || ($middleName = $_REQUEST['middleName']) == '' ||
				!isset($_REQUEST['login']) || ($login = $_REQUEST['login']) == '' ||
				!isset($_REQUEST['rights']) || ($rights = $_REQUEST['rights']) == '' ||
				!isset($_REQUEST['email']) || ($email = $_REQUEST['email']) == '' ||
				!isset($_REQUEST['phone']) || ($phone = $_REQUEST['phone']) == '' ||
				!isset($_REQUEST['address']) || ($address = $_REQUEST['address']) == '' ||
				!isset($_REQUEST['partner']) || ($rights == 'partner' && ($partner = $_REQUEST['partner']) <= 0)) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$password = '';
			switch($_REQUEST['call']) {
				case 'updateUser':
					$req = $mysqli->prepare("UPDATE IGNORE `users` SET `firstName` = ?, `secondName` = ?, `middleName` = ?, ".
											  "`login` = ?, `rights` = ?, `email` = ?, `phone` = ?, `address` = ?, `partner_id` = ? ".
											  "WHERE `id` = ?");
					$req->bind_param('ssssssssii', $givenName, $familyName, $middleName, $login, $rights, $email, $phone, $address, $partner, $id);
					break;
				case 'addUser':
					shuffle($pwchars);
					$password = implode(array_slice($pwchars, 0, $passLength));
					$hash = md5($password.$login."dwPwen");
					$req = $mysqli->prepare("INSERT IGNORE INTO `users` (`firstName`, `secondName`, `middleName`, `login`, `passwordHash`, ".
											"`isDisabled`, `rights`, `email`, `phone`, `address`, `partner_id`, `loginDB`) ".
											"VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'mysql')");
					$req->bind_param('sssssssssi', $givenName, $familyName, $middleName, $login, $hash, $rights, $email, $phone, $address, $partner);
					break;
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'.$mysqli->error));
				exit;
			}
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				echo json_encode(array('error' => 'Уже есть пользователь с таким логином или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			if ($password != '') {
				$to = $familyName.($givenName != '' ? (' '.mb_substr($givenName, 0, 1, 'utf-8').'.'.($middleName != '' ? (' '.mb_substr($middleName, 0, 1, 'utf-8').'.') : '')) : '');
				$to1 = $givenName.(($givenName != '' && $middleName != '') ? ' ' : '').$middleName; 
				$gender = (genderByNames($givenName, $middleName, $familyName) >= 0 ? 'ый' : 'ая');
				$msg = compose_mail("Информационное сообщение от службы технической поддержки «Со-Действие»\r\n".
    							"Уважаем{$gender} {$to1}, вы зарегистрированы в системе учета и обработки заявок группы компаний «Со-действие».\r\n".
    							"\r\n".
    							"Вам присвоено имя пользователя {$login}\r\n".
    							"Для задания пароля перейдите по ссылке\r\n".
    							"http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}\r\n".
    							"\r\n".
    							"----\r\n".
    							"С уважением, служба технической поддержки «Со-Действие».\r\n".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.",
								"<meta http-equiv='Content-Type' content='text/html; charset=utf-8'>".
								"<style><!--".
  								"h1 { font-size: 1.1em; font-weight: bold; } ".
  								".login { font-weight: bold; font-style: italic; } ".
								"--></style>".
								"<div dir='ltr'>".
    							"<h1>Информационное сообщение от службы технической поддержки «Со-Действие»</h1>".
    							"<p>Уважаем{$gender} {$to1}, вы зарегистрированы в системе учета и обработки заявок группы компаний «Со-действие».".
    							"<p>".
    							"<p>Вам присвоено имя пользователя <span class='login'>{$login}</span><br>".
    							"Для задания пароля перейдите по ссылке<br>".
    							"<a href='http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h=${hash}'>http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}</a>".
    							"<p>".
    							"<p>----<br>".
    							"С уважением, служба технической поддержки «Со-Действие».<br>".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
								"</div>"
				);
								
				if (smtpmail($email, $to, "Для Вас создана учётная запись в сервисдеске «Со-Действие»", $msg['body'], $msg['header']))
					$ret['message'] = 'Пароль задан, сообщение отправлено по электронной почте.';
				else 
					$ret['message'] = "Пароль задан, сообщение отправить не удалось.\nСсылка для установки пароля: http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}";
			}
			break;
		case 'delUser':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `users` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `u`.`id`, `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, `u`.`login`, ". 
							  "`u`.`isDisabled`, `u`.`rights`, `u`.`email`, `u`.`phone`, `u`.`address`, `u`.`loginDB`, `p`.`name`".
							  "FROM `users` AS `u` ".
							  "LEFT JOIN `partner` AS `p` ON `p`.`id` = `u`.`partner_id` ".
							  "ORDER BY `secondName`, `firstName`, `middleName`");
	$req->bind_result($id, $givenName, $familyName, $middleName, $login, $isDisabled, $rights, $email, $phone, $address,
					  $loginDB, $partner);
	if (!$req->execute()) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl =	"<table class='usersTbl'>".
				"<thead><tr><th><th>Фамилия<th>Имя<th>Отчество<th>Логин<th>Права<th>E-Mail<th>Телефон<th>Адрес<th>Организация".
				"<tbody>";
	while ($req->fetch()) {
		$class = $loginDB;
		if ($isDisabled == 1)
			$class = ' locked';
		$tbl .= "<tr data-id='{$id}' class='{$class}'><td>".($loginDB == 'ldap' ? "" : "<span class='ui-icon ui-icon-pencil'> </span><span class='ui-icon ui-icon-trash'> </span><span class='ui-icon ui-icon-key'> </span>").
				"<span class='ui-icon ".($isDisabled == 1 ? "ui-icon-locked" : "ui-icon-unlocked")."'> </span>".
				"<td>{$familyName}<td>{$givenName}<td>{$middleName}".
				"<td>{$login}<td>{$rightArr[$rights]}<td>".htmlspecialchars($email)."<td>".htmlspecialchars($phone)."<td>".htmlspecialchars($address)."<td>".
				($rights == 'partner' ? htmlspecialchars($partner) : '');
	}
	$tbl .= "<tr data-id='0'><td><span class='ui-icon ui-icon-plusthick'> </span><td>Добавить<td><td><td><td><td><td><td><td></table>".
			"<input type='hidden' id='oldOrg'><input id='rlist' type='hidden' value='<select id=\"rights\">";
	foreach ($rightArr as $val => $opt)
		$tbl .= "<option value=\"{$val}\">${opt}";
	$tbl .= "</select>'>";
	$req->close();	
	$ret['content'] = $tbl;
	echo json_encode($ret);
?>	