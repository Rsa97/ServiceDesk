<?php

	$routes = array('user/key'	 	=> array('file' => 'user_key.php'),
					'user/login' 	=> array('file' => 'user_login.php', 
										'post' => 	array('name', 'pass', 'newPass','changePass'),
										'required' => array('name', 'pass', 'changePass')),
					'user/logout' 	=> array('file' => 'user_logout.php'),
					'user/isAdmin'  => array('file' => 'user_isAdmin.php'),
					'time'			=> array('file' => 'time.php'),
					'filter/build'  => array('file' => 'filter_build.php'),
					'filter/set' 	=> array('file' => 'filter_set.php', 
										'post' => 	array('byDiv', 'bySrv', 'byText', 'onlyMy', 'byFrom', 'byTo'),
										'filters' => array('byDiv' => '/^(?:n0|(?:C|D)[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/',
														   'bySrv' => '/^(?:n0|S[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/',
														   'byFrom' => '/^\d{4}-\d\d-\d\d$',
														   'byTo' => '/^\d{4}-\d\d-\d\d$',
														   'onlyMy' => '/^[01]$')),
					'request/view'  => array('file' => 'request_view.php', 
										'get' => array('n'),
										'post' => array('n'),
										'filters' => array('n' => '/^\d+$/'),
										'required' => array('n')),
					'request/isChangeAllowed' => array('file' => 'request_isChangeAllowed.php', 
										'get' => array('n'),
										'post' => array('n'),
										'filters' => array('n' => '/^\d+$/'),
										'required' => array('n')),
					'request/calcTime' => array('file' => 'request_calcTime.php',
										'get' => array('division', 'service', 'slaLevel'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'slaLevel' => '/^(?:critical|high|medium|low)$'),
										'required' => array('division', 'service', 'slaLevel')),
					'request/new' => array('file' => 'request_new.php',
										'get' => array('division', 'service', 'slaLevel'),
										'post' => array('equipment', 'problem', 'contact'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'slaLevel' => '/^(?:critical|high|medium|low)$',
														   'equipment' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'problem' => '/\S+/'),
										'required' => array('division', 'service', 'slaLevel', 'problem')),
					'dir/contragents' => array('file' => 'dir_contragents.php'),
					'dir/contracts' => array('file' => 'dir_contracts.php',
										'get' => array('contragent'),
										'filters' => array('contragent' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('contragent')),
					'dir/divisions' => array('file' => 'dir_divisions.php',
										'get' => array('contract'),
										'filters' => array('contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('contract')),
					'dir/contacts' => array('file' => 'dir_contacts.php',
										'get' => array('division'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division')),
					'dir/services' => array('file' => 'dir_services.php',
										'get' => array('division'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division')),
					'dir/slas' 	   => array('file' => 'dir_slas.php',
										'get' => array('division', 'service'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division', 'service')),
					'dir/equipment' => array('file' => 'dir_equipment.php',
										'get' => array('division'),
										'post' => array('servNum'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division')),
					'equipment/info' => array('file' => 'equipment_info.php',
										'get' => array('division', 'equipment'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'equipment' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division', 'equipment')),
					'file/get' => array('file' => 'file_get.php', 
										'get' => array('n', 'docGuid'),
										'filters' => array('n' => '/^\d+$/', 
														   'docGuid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('n', 'docGuid'))
	);
	$paramValues = array();
	foreach ($routes as $route => $def) {
		if (strpos($_REQUEST['routeRequest'], $route) === 0) {
			$rest = substr($_REQUEST['routeRequest'], strlen($route)+1);
			if ($rest === false)
				$rest = '';
			$params = explode('/', $rest);
			if (isset($def['get'])) {
				foreach($params as $i => $param)
					if (isset($def['get'][$i]))
						$paramValues[$def['get'][$i]] = trim($param);
			}
			if (isset($def['post'])) {
				foreach($_POST as $name => $value)
					if (in_array($name, $def['post']))
						$paramValues[$name] = trim($value);
			}
			if(isset($def['filters'])) {
				foreach($paramValues as $name => $value)
					if (isset($def['filters']['name']) && false === preg_match($def['filters']['name'], $value)) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						exit;
					}
			}
			if(isset($def['required'])) {
				foreach($def['required'] as $name)
					if (!isset($paramValues[$name])) {
						header("{$_SERVER['SERVER_PROTOCOL']} 400 Bad Request");
						exit;
					}
			}
			if (file_exists($def['file']))
				include_once($def['file']);
			else
				header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
			exit;
		}
	}
	header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
	exit;
?>