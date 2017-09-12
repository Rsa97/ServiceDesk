<?php

// Описываем кнопки, доступные с разными правами в разных разделах
	$buttons = array('New' => array('name' => 'Создать', 'icon' => 'ui-icon-document'),
					 'Accept' => array('name' => 'Принять', 'icon' =>  'ui-icon-plus'),
					 'Cancel' => array('name' => 'Отменить', 'icon' => 'ui-icon-cancel'),
					 'SetTo' => array('name' => 'Назначить', 'icon' => 'ui-icon-seek-next'),
					 'Dload' => array('name' => 'Выгрузить', 'icon' => 'ui-icon-circle-arrow-s'),
					 'UpForm' => array('name' => 'Загрузить форму', 'icon' => 'ui-icon-circle-arrow-n'),
					 'Close' => array('name' => 'Закрыть', 'icon' => 'ui-icon-closethick'),
					 'CheckForm' => array('name' => 'Форма обследования', 'icon' => 'ui-icon-clipboard'),
					 'UnCancel' => array('name' => 'Открыть повторно', 'icon' => 'ui-icon-notice'),
					 'Delete' => array('name' => 'Удалить', 'icon' => 'ui-icon-trash'),
					 'Edit' => array('name' => 'Изменить', 'icon' => 'ui-icon-pencil'),
					 'Fixed' => array('name' => 'Восстановлено', 'icon' => 'ui-icon-wrench'),
					 'Repaired' => array('name' => 'Завершено', 'icon' => 'ui-icon-check'),
					 'Wait' => array('name' => 'Ожидание', 'icon' => 'ui-icon-clock'),
					 'UnClose' => array('name' => 'Отказать', 'icon' => 'ui-icon-alert'),
					 'DoNow' => array('name' => 'Выполнить сейчас', 'icon' => 'ui-icon-extlink'),
					 'AddProblem' => array('name' => 'Добавить примечание', 'icon' => 'ui-icon-info')
				);

	$allowedBtn = array(
					 'received' => array('admin' => array('New', 'Accept', 'Cancel', 'Wait'),
										 'client' => array('New', 'Cancel'),
										 'operator' => array('New', 'Cancel'),
										 'engineer' => array('New', 'Accept', 'Cancel', 'Wait'),
										 'partner' => array('Accept', 'Wait')),
				 	 'accepted' => array('admin' => array('Fixed', 'Repaired', 'Wait'),
										 'client' => array('Cancel'),
										 'operator' => array('Cancel'),
										 'engineer' => array('Fixed', 'Repaired', 'Wait'),
										 'partner' => array('Fixed', 'Repaired','Wait')),
					 'toClose'  => array('admin' => array('UnClose', 'Close'),
										 'client' => array('UnClose', 'Close'),
										 'operator' => array('UnClose', 'Close'),
										 'engineer' => array(),
										 'partner' => array()),
					 'planned'  => array('admin' => array('DoNow', 'AddProblem'),
										 'client' => array(),
										 'operator' => array('AddProblem'),
										 'engineer' => array('DoNow', 'AddProblem'),
										 'partner' => array('DoNow', 'AddProblem')),
					 'closed'   => array('admin' => array(),
										 'client' => array(),
										 'operator' => array(),
										 'engineer' => array(),
										 'partner' => array()),
					 'canceled' => array('admin' => array('UnCancel'),
										 'client' => array(),
										 'operator' => array(),
										 'engineer' => array('UnCancel'),
										 'partner' => array()));

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$state = (isset($params['state']) ? $params['state'] : 'all');
	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	
	$result = array();
	$rights = '*';

	try {
		$req = $db->prepare("SELECT `rights` FROM `users` WHERE `guid` = UNHEX(:user_guid)");
		$req->execute(array('user_guid' => $user_guid));
		if ($row = $req->fetch(PDO::FETCH_NUM)) {
			list($rights) = $row;
		}
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.', 
								'orig' => 'MySQL error'.$e->getMessage()));
		exit;
	}
		
	foreach($allowedBtn as $forState => $states) {
		if ('all' == $state || $forState == $state) {
			$result[$forState] = array();
			foreach($states[$rights] as $buttonName) {
				$result[$forState][$buttonName] = $buttons[$buttonName];
			}
		}
	}
	
	echo json_encode(array('result' => 'ok', 'ops' => $result, 'expireTime' => 24*60*60));
?>