<?php

	$routes = array(
		'GET' => array(
			'me' => 		array('file' => '/rest/user/info.php'),
			'allowedOps' => array('file' => '/rest/interface/allowedOps.php',
								'filters' => array('type' => '/^(?:received|accepted|toClose|planned|closed|canceled|all)$/')),
			'filter' => 	array('file' => '/rest/interface/filter.php'),
			'requests' => 	array('file' => '/rest/request/list.php',
								'filters' => array('type' => '/^received|accepted|toClose|planned|closed|canceled|all$/',
												   'contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
												   'division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
												   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
												   'from' => '/^\d{4}-\d\d-\d\d$/',
												   'to' => '/^\d{4}-\d\d-\d\d$/',
												   'onlyMy' => '/^0|1$/'
							)),
			'request' => 	array('file' => '/rest/request/get.php',
								'require' => array('id'),
								'filters' => array('id', '/^\d+$/')),
			'contragents' => array('file' => '/rest/contragent/list.php'),
			'contragent' => array('file' => '/rest/contragent/get.php',
								'require' => array('guid'),
								'filters' => array('guid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'contracts' => 	array('file' => '/rest/contract/list.php',
								'require' => array('contragent'),
								'filters' => array('contragent' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'contract' => 	array('file' => '/rest/contract/get.php',
								'require' => array('guid'),
								'filters' => array('guid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'divisions' => 	array('file' => '/rest/division/list.php',
								'require' => array('contract'),
								'filters' => array('contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'division' => 	array('file' => '/rest/division/get.php',
								'require' => array('guid'),
								'filters' => array('guid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							)
		),
		'POST' => array(
			'auth' => 		array('file' => '/rest/user/auth.php',
								'require' => array('name', 'pass'),
								'filters' => array('name' => '/^[a-z][-_0-9a-z]*$/'),
								'withoutToken' => true
						   	),
			'newPass' => 	array('file' => '/rest/user/newPass.php',
								'require' => array('pass')
						  	)
		),
		'PUT' => array(
		),
		'DELETE' => array(
		)		
	);
	
	
	if (!isset($routes[$_SERVER['REQUEST_METHOD']])) {
		header("{$_SERVER['SERVER_PROTOCOL']} 405 Method Not Allowed");
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(array('result' => 'error', 'error' => 'Недопустимый метод'));	
		exit;
	}

	$params = array();
	foreach($_REQUEST as $name => $value) {
		if ('routeRequest' != $name) {
			$params[$name] = $value;
		}
	}

	foreach ($routes[$_SERVER['REQUEST_METHOD']] as $route => $def) {
		if (strpos($_REQUEST['routeRequest'], $route) === 0) {
			$rest = substr($_REQUEST['routeRequest'], strlen($route)+1);
			if ($rest === false)
				$rest = '';

			$urlParams = explode('/', $rest);
			if (count($urlParams)%2) {
				header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
				header('Content-Type: application/json; charset=UTF-8');
				echo json_encode(array('result' => 'error', 'error' => 'Неверная структура параметров'));	
				exit;
			}

			for ($i = 0; $i < count($urlParams); $i += 2) {
				$params[$urlParams[$i]] = $urlParams[$i+1]; 
			}
			
			if (isset($def['require'])) {
				foreach ($def['require'] as $required) {
					if (!isset($params[$required])) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						header('Content-Type: application/json; charset=UTF-8');
						echo json_encode(array('result' => 'error', 'error' => "Не указан параметр '{$required}'"));	
						exit;
					}
				}
			}
			
			if (isset($def['filters'])) {
				foreach($def['filters'] as $name => $filter) {
					if (isset($params[$name]) && !preg_match($filter, $params[$name])) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						header('Content-Type: application/json; charset=UTF-8');
						echo json_encode(array('result' => 'error', 'error' => "Недопустимое значение параметра '{$name}'"));	
						exit;
					}
				}
			}
			
			if (!isset($def['withoutToken']) || (false == $def['withoutToken'])) {
				if (!isset($params['token'])) {
					header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(array('result' => 'error', 'error' => "Не указан токен"));	
					exit;
				}
				include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
				try {
					
					$req = $db->prepare("SELECT HEX(`user_guid`) AS `user_guid` ".
											"FROM `tokens` ".
											"WHERE `token` = UNHEX(:token) AND `expired` > NOW()");
					$req->execute(array('token' => $params['token']));
					if (!($row = $req->fetch(PDO::FETCH_ASSOC))) {
						header("{$_SERVER['SERVER_PROTOCOL']} 401 Unauthorized");
						header('Content-Type: application/json; charset=UTF-8');
						json_encode(array('result' => 'error', 'error' => "Токен просрочен"));	
						exit;
					}
					$params['user_guid'] = $row['user_guid']; 
				} catch (PDOException $e) {
					header("{$_SERVER['SERVER_PROTOCOL']} 500 Internal Server Error");
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(array(	'result' => 'error',
											'error' => 'Внутренняя ошибка сервера', 
											'orig' => "MySQL error".$e->getMessage()));
					exit;
				}
			}
			
			if (isset($def['file']) && file_exists("{$_SERVER['DOCUMENT_ROOT']}{$def['file']}")) {
				include_once("{$_SERVER['DOCUMENT_ROOT']}{$def['file']}");
				exit;
			} else {
				header("{$_SERVER['SERVER_PROTOCOL']} 500 Internal Server Error");
				header('Content-Type: application/json; charset=UTF-8');
				echo json_encode(array('result' => 'error', 'error' => "Не найден файл метода"));	
				exit;
			}
			
		}
	}
	
	header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(array('result' => 'error', 'error' => "Не найден метод"));	
	exit;
	
?>