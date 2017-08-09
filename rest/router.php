<?php

	$routes = array(
		'GET' => array(
			'auth' => array('file' => '/rest/user/auth.php',
							'require' => array('name', 'pass'),
							'filters' => array('name' => '/^[a-z][-_0-9a-z]*$/'),
							'withoutToken' => false
						   )
		),
		'POST' => array(
			'auth' => array('file' => '/rest/user/auth.php',
							'require' => array('name', 'pass'),
							'filters' => array('name' => '/^[a-z][-_0-9a-z]*'),
							'withoutToken' => true
						   )
		),
		'PUT' => array(
		),
		'DELETE' => array(
		)		
	);
	
	
	if (!isset($routes[$_SERVER['REQUEST_METHOD']])) {
		header("{$_SERVER['SERVER_PROTOCOL']} 405 Method Not Allowed");
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
				exit;
			}

			for ($i = 0; $i < count($urlParams); $i += 2) {
				$params[$urlParams[$i]] = $urlParams[$i+1]; 
			}
			
			if (isset($def['require'])) {
				foreach ($def['require'] as $required) {
					if (!isset($params[$required])) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						exit;
					}
				}
			}
			
			if (isset($def['filters'])) {
				foreach($def['filters'] as $name => $filter) {
					if (!preg_match($filter, $params[$name])) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						exit;
					}
				}
			}
			
			if (!isset($def['withoutToken']) || (false == $def['withoutToken'])) {
				if (!isset($params['token'])) {
					header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
					exit;
				}
				include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
				include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
				try {
					
					$time = time();
					$req = $db->prepare("SELECT HEX(`user_guid`) ".
											"FROM `tokens` ".
											"WHERE `token` = UNHEX(:token) AND `expired` < :time");
					$req->execute(array('token' => $params['token'], 'time' => $time));
					if ($row = $req->fetch(PDO::FETCH_ASSOC)) {
						$params['user_guid'] = $row['user_guid']; 
					} else {
						header("{$_SERVER['SERVER_PROTOCOL']} 401 Unauthorized");
						exit;
					}
				} catch (PDOException $e) {
					header("{$_SERVER['SERVER_PROTOCOL']} 500 Internal Server Error");
					header('Content-Type: application/json; charset=UTF-8');
					echo json_encode(array(	'result' => 'error',
											'error' => 'Внутренняя ошибка сервера', 
											'orig' => "MySQL error".$e->getMessage()));
				}
			}
			
			if (isset($def['file']) && file_exists("{$_SERVER['DOCUMENT_ROOT']}{$def['file']}")) {
				include_once("{$_SERVER['DOCUMENT_ROOT']}{$def['file']}");
				exit;
			} else {
				header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
				exit;
			}
			
		}
	}
	print_r($params);
	
	header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
	exit;
	
?>