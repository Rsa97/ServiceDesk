<?php
	include('common.php');

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
	if (!isset($paramValues['call'])) {
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
	$lastId = '';
	$password = '';
	$disable = 0;
	switch($paramValues['call']) {
		case 'init':
			break;
		case 'getlists':
			if (!isset($paramValues['field']) || !isset($paramValues['id']) || ($id = $paramValues['id']) < 0) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			switch($paramValues['field']) {
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) < 0 ||
				!isset($paramValues['fn']) || $paramValues['fn'] == '' ||
				!isset($paramValues['gn']) || $paramValues['gn'] == '' ||
				!isset($paramValues['mn']) || !isset($paramValues['address']) ||
				!isset($paramValues['login']) || $paramValues['login'] == '' ||
				!isset($paramValues['rights']) || $paramValues['rights'] == '' ||
				!isset($paramValues['email']) || !isset($paramValues['email']) ||
				!isset($paramValues['phone']) || !isset($paramValues['partner'])) {
				returnJson(array('error' => 'Ошибка в параметрах.'));
				exit;
			}
			if ($paramValues['rights'] == 'partner' && $paramValues['partner'] <= 0) {
				returnJson(array('error' => 'Для пользовател с правами партнёра должен быть указан партнёр.'));
				exit;
			}
			$partner = ($paramValues['rights'] == 'partner' ? $paramValues['partner'] : null);
			if ($id == 0) {
				shuffle($pwchars);
				$password = implode(array_slice($pwchars, 0, $passLength));
				$hash = md5($password.$paramValues['login']."dwPwen");
				$req = $mysqli->prepare("INSERT IGNORE INTO `users` (`firstName`, `secondName`, `middleName`, `login`, `passwordHash`, ".
										"`isDisabled`, `rights`, `email`, `phone`, `address`, `partner_id`, `loginDB`) ".
										"VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'mysql')");
				$req->bind_param('sssssssssi', $paramValues['gn'], $paramValues['fn'], $paramValues['mn'], $paramValues['login'], $hash, 
								$paramValues['rights'], $paramValues['email'], $paramValues['phone'], $paramValues['address'], $partner);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `users` SET `firstName` = ?, `secondName` = ?, `middleName` = ?, ".
										  "`login` = ?, `rights` = ?, `email` = ?, `phone` = ?, `address` = ?, `partner_id` = ? ".
										  "WHERE `id` = ?");
				$req->bind_param('ssssssssii', $paramValues['gn'], $paramValues['fn'], $paramValues['mn'], $paramValues['login'], 
								$paramValues['rights'], $paramValues['email'], $paramValues['phone'], $paramValues['address'], $partner, $id);
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
				$to = $paramValues['fn'].($paramValues['gn'] != '' ? (' '.mb_substr($paramValues['gn'], 0, 1, 'utf-8').'.'.
						($paramValues['mn'] != '' ? (' '.mb_substr($paramValues['mn'], 0, 1, 'utf-8').'.') : '')) : '');
				$to1 = $paramValues['gn'].(($paramValues['gn'] != '' && $paramValues['mn'] != '') ? ' ' : '').$paramValues['mn']; 
				$gender = (genderByNames($paramValues['gn'], $paramValues['mn'], $paramValues['fn']) >= 0 ? 'ый' : 'ая');
				$msg = compose_mail("Информационное сообщение от службы технической поддержки «Со-Действие»\r\n".
    							"Уважаем{$gender} {$to1}, вы зарегистрированы в системе учета и обработки заявок группы компаний «Со-действие».\r\n".
    							"\r\n".
    							"Вам присвоено имя пользователя {$paramValues['login']}\r\n".
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
    							"<p>Вам присвоено имя пользователя <span class='login'>{$paramValues['login']}</span><br>".
    							"Для задания пароля перейдите по ссылке<br>".
    							"<a href='http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h=${hash}'>http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}</a>".
    							"<p>".
    							"<p>----<br>".
    							"С уважением, служба технической поддержки «Со-Действие».<br>".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
								"</div>"
				);
				if (smtpmail($paramValues['email'], $to, "Для Вас создана учётная запись в сервисдеске «Со-Действие»", $msg['body'], $msg['header']))
					$ret['message'] = 'Пароль задан, сообщение отправлено по электронной почте.';
				else 
					$ret['message'] = "Пароль задан, сообщение отправить не удалось.\nСсылка для установки пароля: http://sd.sodrk.ru/newpwd.php?i={$mysqli->insert_id}&h={$hash}";
			}
			break;			
		case 'del':
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
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
			if (!isset($paramValues['id']) || ($id = $paramValues['id']) <= 0) {
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
			$req = $mysqli->prepare("SELECT `login`, `email`, `firstName`, `lastName`, `middleName` ".
										"FROM `users` ".
										"WHERE `guid` = UNHEX(REPLACE(?, '-', ''))");
			$req->bind_param('s', $paramValues['id']);
			$req->bind_result($user, $email, $givenName, $familyName, $middleName);
			if (!$req->execute() || !$req->fetch()) {
				returnJson(array('error' => 'Внутренняя ошибка сервера.'));
				exit;
			}
			$req->close();
			shuffle($pwchars);
			$password = implode(array_slice($pwchars, 0, $passLength));
			$hash = md5($password.$user."dwPwen");
			$req = $mysqli->prepare("UPDATE IGNORE `users` SET `passwordHash` =  ? WHERE `guid` = UNHEX(REPLACE(?, '-', ''))");
			$req->bind_param('ss', $hash, $paramValues['id']);
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
    							"http://sd.sodrk.ru/ajax/user/changePass/{$paramValues['id']}/{$hash}\r\n".
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
    							"<a href='http://sd.sodrk.ru/ajax/user/changePass/{$paramValues['id']}/{$hash}'>http://sd.sodrk.ru/ajax/user/changePass/{$paramValues['id']}/{$hash}</a>".
    							"<p>".
    							"<p>----<br>".
    							"С уважением, служба технической поддержки «Со-Действие».<br>".
    							"PS. Данное письмо сформировано автоматически. Пожалуйста, не отвечайте на него.".
								"</div>");
			if (smtpmail($email, $to, "Ваш пароль в сервисдеске «Со-Действие» был сброшен", $msg['body'], $msg['header']))
				returnJson(array('message' => 'Пароль изменён, сообщение отправлено по электронной почте.'));
			else 
				returnJson(array('message' => "Пароль изменён, сообщение отправить не удалось.\nСсылка: http://sd.sodrk.ru/ajax/user/changePass/{$paramValues['id']}/{$hash}"));
			exit;
			break;
		default:
			returnJson(array('error' => 'Ошибка в параметрах.'));
			exit;
	}
	$req = $mysqli->prepare("SELECT `u`.`guid`, `u`.`lastName`, `u`.`firstName`, `u`.`middleName`, `u`.`login`, `u`.`isDisabled`, ".
									"`u`.`rights`, `u`.`email`, `u`.`phone`, `u`.`address`, `p`.`name`, `u`.`loginDB`, `used`.`uid` ".
									"FROM `users` AS `u` ".
									"LEFT JOIN `partners` AS `p` ON `p`.`guid` = `u`.`partner_guid` ".
									"LEFT JOIN ( ".
										"SELECT DISTINCT `user_guid` AS `uid` FROM `userContracts` ".
										"UNION SELECT DISTINCT `user_guid` AS `uid` FROM `requestEvents` ".
										"UNION SELECT DISTINCT `user_guid` AS `uid` FROM `userContractDivisions` ".
										"UNION SELECT DISTINCT `contactPerson_guid` AS `uid` FROM `requests` ".
										"UNION SELECT DISTINCT `engineer_guid` AS `uid` FROM `requests` ".
									") AS `used` ON `used`.`uid` = `u`.`guid` ".
									"ORDER BY `lastName`, `firstName`, `middleName`");
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
		$uid = formatGuid($uid);
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