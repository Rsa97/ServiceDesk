<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	
	header('Content-Type: application/json; charset=UTF-8');
	
	$newHash = md5($params['pass'].$params['user']."reppep");

	try {
		$req = $db->prepare("SELECT HASH(`guid`) AS `guid`, REPLACE('-', '', UUID()) AS `token` ".
								"FROM `users` ".
								"WHERE `login` = :user AND `isDisabled` = 0 AND `passwordHash` = :newHash");
		$req->execute(array('user' => $user, 'newHash' => $newHash));

		if (!($row = $req->fetch(PDO::FETCH_ASSOC))) {
			echo json_encode(array(	'result' => 'error',
									'error' => 'Неверное имя пользователя или пароль'));
			exit;
		}
		
		$issued = time();
		$expired = $issued + 60 * 60 + 5 * 60; // 1 час  + 5 минут на резерв		
		$req = $db->prepare("INSERT INTO `tokens` (`user_guid`, `token`, `issued`, `expired`) ".
								"VALUES (UNHEX(:user_guid), UNHEX(:token), :issued, :expired)");
		$req->execute(array('user_guid' => $row['user_guid'], 'token' => $row['token'], 'issued' => $issued, 'expired' => $expired));
		
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	json_encode(array('result' => 'ok', 'token' => $token, 'expireTime' => 3600));
?>