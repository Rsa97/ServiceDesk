<?php

	require_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	require_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");

	

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);

	$reqVars = array('user_guid' => $user_guid);
	$infoType = (isset($params['tail']) ? $params['tail'] : (isset($params['type']) ? $params['type'] : ''));
	if ('' == $infoType) {
		returnAnswer(400, array('result' => 'error', 'error' => "Не указан параметр 'type'"));	
		exit;
	}
	
	switch($infoType) {
		case 'filter':
			$updates = array();
			if (isset($params['filter'])) {
				$updates[] = '`filter` = :filter';
				$reqVars['filter'] = $params['filter'];
			} else {
				returnAnswer(HTTP_400, array('result' => 'error', 'error' => "Не указан параметр 'filter'"));	
				exit;
			}
			$result = array();
	}

	if (0 < count($updates)) {
		try {
			$req = $db->prepare("UPDATE `users` SET ".implode(', ', $updates)." WHERE `guid` = UNHEX(:user_guid)");
			$req->execute($reqVars);
	
		} catch (PDOException $e) {
			returnAnswer(HTTP_500, array('result' => 'error',
									'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
									'orig' => 'MySQL error'.$e->getMessage()));
			exit;
		}
	}
	
	require_once('info.php');
?>