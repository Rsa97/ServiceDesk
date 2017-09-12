<?php
	function returnJson($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
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

	session_start();
	if (!isset($_SESSION['user'])) {
		returnJson(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
	if ($_SESSION['user']['rights'] != 'admin') {
		returnJson(array('error' => 'Недостаточно прав для администрирования.', 'redirect' => '/index.html'));
		exit;
	}
	$_SESSION['time'] = time();
	session_commit();
	if (!isset($_REQUEST['call'])) {
		returnJson(array('error' => 'Ошибка в параметрах.'));
		exit;
	}
	// Подключаемся к MySQL
	$mysqli = mysqli_init(); 
	$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, 3306, null, MYSQLI_CLIENT_FOUND_ROWS);
	if ($mysqli->connect_error) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	$ret = array();
	$id = 0;
	$lastId = 0;
	$password = '';
	$disable = 0;
	switch($_REQUEST['call']) {
		case 'init':
			break;
		case 'getlists':
			if (!isset($_REQUEST['field']) || !isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($_REQUEST['field']) {
				case 'partner':
					$req =  $mysqli->prepare("SELECT `id`, `name` FROM `partner` ORDER BY `name`");
					$req->bind_result($partnerId, $partnerName);
					if (!$req->execute()) {
						returnJson(array('error' => 'Внутренняя ошибка сервера.'));
						exit;
					}
					$list = array(array('id' => 0, 'name' => '--Нет--', 'mark' => 'gray'));
					while ($req->fetch()) {
						$list[] = array('id' => $partnerId, 'name' => htmlspecialchars($partnerName));
					}
					$req->close();
					returnJson(array('options' => $list));
					exit;
					break;
				default:
					returnJson(array('error' => 'Ошибка в параметрах.'));
					exit;
			}
			break;
		case 'update':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) < 0 ||
				!isset($_REQUEST['fn']) || $_REQUEST['fn'] == '' ||
				!isset($_REQUEST['gn']) || $_REQUEST['gn'] == '' ||
				!isset($_REQUEST['mn']) || !isset($_REQUEST['address']) ||
				!isset($_REQUEST['login']) || $_REQUEST['login'] == '' ||
				!isset($_REQUEST['rights']) || $_REQUEST['rights'] == '' ||
				!isset($_REQUEST['email']) || !isset($_REQUEST['email']) ||
				!isset($_REQUEST['phone']) || !isset($_REQUEST['partner'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($_REQUEST['rights'] == 'partner' && $_REQUEST['partner'] <= 0) {
				returnJson(array('error' => 'Для пользовател с правами партнёра должен быть указан партнёр.'));
				exit;
			}
			$partner = ($_REQUEST['rights'] == 'partner' ? $_REQUEST['partner'] : null);
			if ($id == 0) {
				shuffle($pwchars);
				$password = implode(array_slice($pwchars, 0, $passLength));
				$hash = md5($password.$_REQUEST['login']."dwPwen");
				$req = $mysqli->prepare("INSERT IGNORE INTO `users` (`firstName`, `secondName`, `middleName`, `login`, `passwordHash`, ".
										"`isDisabled`, `rights`, `email`, `phone`, `address`, `partner_id`, `loginDB`) ".
										"VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'mysql')");
				$req->bind_param('sssssssssi', $_REQUEST['gn'], $_REQUEST['fn'], $_REQUEST['mn'], $_REQUEST['login'], $hash, 
								$_REQUEST['rights'], $_REQUEST['email'], $_REQUEST['phone'], $_REQUEST['address'], $partner);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `users` SET `firstName` = ?, `secondName` = ?, `middleName` = ?, ".
										  "`login` = ?, `rights` = ?, `email` = ?, `phone` = ?, `address` = ?, `partner_id` = ? ".
										  "WHERE `id` = ?");
				$req->bind_param('ssssssssii', $_REQUEST['gn'], $_REQUEST['fn'], $_REQUEST['mn'], $_REQUEST['login'], 
								$_REQUEST['rights'], $_REQUEST['email'], $_REQUEST['phone'], $_REQUEST['address'], $partner, $id);
			}
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$lastId = ($id == 0 ? $mysqli->insert_id : $id);
			if (($id == 0 && $mysqli->insert_id <= 0) || ($id > 0 && $mysqli->affected_rows <= 0)) {
				returnJson(array('error' => 'Пользователь с таким логином уже существует или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			if ($password != '') {
				$to = $_REQUEST['fn'].($_REQUEST['gn'] != '' ? (' '.mb_substr($_REQUEST['gn'], 0, 1, 'utf-8').'.'.
						($_REQUEST['mn'] != '' ? (' '.mb_substr($_REQUEST['mn'], 0, 1, 'utf-8').'.') : '')) : '');
				$to1 = $_REQUEST['gn'].(($_REQUEST['gn'] != '' && $_REQUEST['mn'] != '') ? ' ' : '').$_REQUEST['mn']; 
				$gender = (genderByNames($_REQUEST['gn'], $_REQUEST['mn'], $_REQUEST['fn']) >= 0 ? 'ый' : 'ая');
				$msg = compose_mail("Информационное сообщение от службы технической поддержки «Со-Действие»\r\n".
    							"Уважаем{$gender} {$to1}, вы зарегистрированы в системе учета и обработки заявок группы компаний «Со-действие».\r\n".
    							"\r\n".
    							"Вам присвоено имя пользователя {$_REQUEST['login']}\r\n".
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
    							"<p>Вам присвоено имя пользователя <span class='login'>{$_REQUEST['login']}</span><br>".
    							"Для задания пароля перейдите по ссылке<br>".
    							"<a href='http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h=${hash}'>http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}</a>".
    							"<p>".
    							"<p>----<br>".
    							"С уважением, служба технической поддержки «Со-Действие».<br>".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
								"</div>"
				);
				if (smtpmail($_REQUEST['email'], $to, "Для Вас создана учётная запись в сервисдеске «Со-Действие»", $msg['body'], $msg['header']))
					$ret['message'] = 'Пароль задан, сообщение отправлено по электронной почте.';
				else 
					$ret['message'] = "Пароль задан, сообщение отправить не удалось.\nСсылка для установки пароля: http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}";
			}
			break;			
		case 'del':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("DELETE IGNORE FROM `users` WHERE `id` = ?");
			$req->bind_param('i', $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			if ($mysqli->affected_rows <= 0) {
				returnJson(array('error' => 'Пользователь уже участвует в заявках или ошибка в параметрах.'));
				exit;
			}
			$req->close();
			break;			 
		case 'lock':
			$disable = 1;
		case 'unlock':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("UPDATE `users` SET `isDisabled` = ? WHERE `id` = ?");
			$req->bind_param('ii', $disable, $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			break;
		case 'changePass':
			if (!isset($_REQUEST['id']) || ($id = $_REQUEST['id']) <= 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			$req = $mysqli->prepare("SELECT `login`, `email`, `firstName`, `secondName`, `middleName` FROM `users` WHERE `id` = ?");
			$req->bind_param('i', $id);
			$req->bind_result($user, $email, $givenName, $familyName, $middleName);
			if (!$req->execute() || !$req->fetch()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			shuffle($pwchars);
			$password = implode(array_slice($pwchars, 0, $passLength));
			$hash = md5($password.$user."dwPwen");
			$req = $mysqli->prepare("UPDATE IGNORE `users` SET `passwordHash` =  ? WHERE `id` = ?");
			$req->bind_param('si', $hash, $id);
			if (!$req->execute()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			$to = $familyName.($givenName != '' ? (' '.mb_substr($givenName, 0, 1, 'utf-8').'.'.($middleName != '' ? (' '.mb_substr($middleName, 0, 1, 'utf-8').'.') : '')) : '');
			$to1 = $givenName.(($givenName != '' && $middleName != '') ? ' ' : '').$middleName; 
			$gender = (genderByNames($givenName, $middleName, $familyName) >= 0 ? 'ый' : 'ая');
			$msg = compose_mail("Информационное сообщение от службы технической поддержки «Со-Действие»\r\n".
    							"Уважаем{$gender} {$to1}, ваш пароль был сброшен.\r\n".
    							"\r\n".
    							"Ваше имя пользователя: {$user}\r\n".
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
    							"<p>Ваше имя пользователя: <span class='login'>{$user}</span>".
    							"<p>".
    							"Для задания нового пароля перейдите по ссылке<br>".
    							"<a href='http://sd.sodrk.ru/newpwd.php?i={$id}&h=${hash}'>http://sd.sodrk.ru/newpwd.php?i={$id}&h={$hash}</a>".
    							"<p>".
    							"<p>----<br>".
    							"С уважением, служба технической поддержки «Со-Действие».<br>".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
								"</div>");
			if (smtpmail($email, $to, "Ваш пароль в сервисдеске «Со-Действие» был сброшен", $msg['body'], $msg['header']))
				returnJson(array('message' => 'Пароль изменён, сообщение отправлено по электронной почте.'));
			else 
				returnJson(array('message' => "Пароль изменён, сообщение отправить не удалось.\nСсылка: http://sd.sodrk.ru/newpwd.php?i={$id}&h={$hash}"));
			exit;
			break;
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `u`.`id`, `u`.`secondName`, `u`.`firstName`, `u`.`middleName`, `u`.`login`, `u`.`isDisabled`, ".
									"`u`.`rights`, `u`.`email`, `u`.`phone`, `u`.`address`, `p`.`name`, `u`.`loginDB`, `used`.`uid` ".
									"FROM `users` AS `u` ".
									"LEFT JOIN `partner` AS `p` ON `p`.`id` = `u`.`partner_id` ".
									"LEFT JOIN ( ".
										"SELECT DISTINCT `users_id` AS `uid` FROM `userContracts` ".
										"UNION SELECT DISTINCT `users_id` AS `uid` FROM `requestEvents` ".
										"UNION SELECT DISTINCT `users_id` AS `uid` FROM `userContractDivisions` ".
										"UNION SELECT DISTINCT `contactPersons_id` AS `uid` FROM `request` ".
										"UNION SELECT DISTINCT `engeneer_id` AS `uid` FROM `request` ".
									") AS `used` ON `used`.`uid` = `u`.`id` ".
									"ORDER BY `secondName`, `firstName`, `middleName`");
	$req->bind_result($uid, $fn, $gn, $mn, $login, $isDisabled, $rights, $email, $phone, $address, $partner, $loginDB, $inUse);
	if (!$req->execute()) {
		returnJson(array('error' => 'Внутренняя ошибка сервера.'));
		exit;
	}
	$tbl = array();
	$last = 0;
	$i = 0;
	$req->store_result();
	while ($req->fetch()) {
		$row = array('id' => $uid, 'fields' => array(htmlspecialchars($fn), htmlspecialchars($gn), htmlspecialchars($mn), 
													 htmlspecialchars($login), $rights, htmlspecialchars($email), 
													 htmlspecialchars($phone), htmlspecialchars($address), htmlspecialchars($partner)));
		if ($uid == $lastId) {
			$row['last'] = 1;
			$last = $i;
		}
		if ($inUse != '')
			$row['notDel'] = 1;
		if ($loginDB == 'ldap') {
			$row['notEdit'] = 1;
			$row['notDel'] = 1;
			$row['mark'] = 'gray';
		} else
			$row['changePass'] = 1;
		if ($isDisabled == 1) {
			$row['state'] = 'locked';
			$row['mark'] = 'red';
		} else
			$row['state'] = 'unlocked';
		$tbl[] = $row;
		$i++;
	}
	$ret['table'] = $tbl;
	$ret['last'] = $last;
	returnJson($ret);
?>