<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$name = isset($params['name']) ? $params['name'] : ''; 
	$pass = isset($params['pass']) ? $params['pass'] : ''; 
	
	$newHash = md5($pass.$name."reppep");

	try {
		$req = $db->prepare("SELECT HEX(`guid`) AS `user_guid`, REPLACE(UUID(), '-', '') AS `token` ".
								"FROM `users` ".
								"WHERE `login` = :user AND `isDisabled` = 0 AND `passwordHash` = :newHash");
		$req->execute(array('user' => $name, 'newHash' => $newHash));

		if (!($row = $req->fetch(PDO::FETCH_ASSOC))) {
			echo json_encode(array(	'result' => 'error',
									'error' => 'Неверное имя пользователя или пароль'));
			exit;
		}
		
		$req = $db->prepare("INSERT INTO `tokens` (`user_guid`, `token`, `issued`, `expired`) ".
								"VALUES (UNHEX(:user_guid), UNHEX(:token), NOW(), NOW()+INTERVAL 3900 SECOND)");
		$req->execute(array('user_guid' => $row['user_guid'], 'token' => $row['token']));
		
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
								'orig' => "MySQL error".$e->getMessage()));
		exit;
	}

	echo json_encode(array('result' => 'ok', 'token' => $row['token'], 'expireTime' => 3600));
?>