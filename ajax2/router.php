<?php

	$routes = array('user/key'	 	=> array('file' => 'user_key.php'),
					'user/login' 	=> array('file' => 'user_login.php', 
										'post' => 	array('name', 'pass', 'newPass','changePass'),
										'required' => array('name', 'pass', 'changePass')),
					'user/logout' 	=> array('file' => 'user_logout.php'),
					'user/isAdmin'  => array('file' => 'user_isAdmin.php'),
					'user/name'		=> array('file' => 'user_name.php'),
					'user/messageConfig/get' => array('file' => 'user_getMessageConfig.php'),
					'user/messageConfig/set' => array('file' => 'user_setMessageConfig.php',
										'post' => array('cellPhone', 'jid', 'data'),
										'filters' => array('cellPhone' => '/^9\d{9}$/',
														   'jid' => '/\S+@\S+/'),
										'required' => array('cellPhone', 'jid', 'data')),
					'time'			=> array('file' => 'time.php'),
					'filter/build'  => array('file' => 'filter_build.php'),
					'filter/set' 	=> array('file' => 'filter_set.php', 
										'post' => 	array('byDiv', 'bySrv', 'byText', 'onlyMy', 'byFrom', 'byTo'),
										'filters' => array('byDiv' => '/^(?:n0|(?:C|D)[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/',
														   'bySrv' => '/^(?:n0|S[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/',
														   'byFrom' => '/^\d{4}-\d\d-\d\d$/',
														   'byTo' => '/^\d{4}-\d\d-\d\d$/',
														   'onlyMy' => '/^[01]$/')),
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
										'post' => array('id'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'slaLevel' => '/^(?:critical|high|medium|low)$/',
														   'id' => '/^\d+$/'),
										'required' => array('division', 'service', 'slaLevel')),
					'request/new' => array('file' => 'request_new.php',
										'get' => array('division', 'service', 'slaLevel', 'contact'),
										'post' => array('equipment', 'problem'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'slaLevel' => '/^(?:critical|high|medium|low)$/',
														   'equipment' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'contact' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'problem' => '/\S+/'),
										'required' => array('division', 'service', 'slaLevel', 'problem', 'contact')),
					'request/changeEq' => array('file' => 'request_changeEquipment.php',
										'get' => array('id'),
										'post' => array('equipment'),
										'filter' => array('id' => '/^\d+$/',
														  'equipment' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('id')),
					'request/addComment' => array('file' => 'request_addComment.php',
										'get' => array('id'),
										'post' => array('comment'),
										'filter' => array('id' => '/^\d+$/',
														  'comment' => '\S+/'),
										'required' => array('id', 'comment')),
					'request/addFile' => array('file' => 'request_addFile.php',
										'get' => array('id'),
										'filter' => array('id' => '/^\d+$/'),
										'required' => array('id')),
					'request/getSolution' => array('file' => 'request_getSolution.php',
										'get' => array('id'),
										'filter' => array('id' => '/^\d+$/'),
										'required' => array('id')),
					'request/Accept' => array('file' => 'request_accept.php',
										'get' => array('ids'),
										'filters' => array('ids' => '/^(?:\d+,)*\d+,?$/'),
										'required' => array('ids')),
					'request/Cancel' => array('file' => 'request_cancel.php',
										'get' => array('id'),
										'post' => array('cause'),
										'filters' => array('id' => '/^\d+$/',
														   'cause' => '/\S+/'),
										'required' => array('id', 'cause')),
					'request/UnCancel' => array('file' => 'request_unCancel.php',
										'get' => array('id'),
										'post' => array('cause'),
										'filters' => array('id' => '/^\d+$/',
														   'cause' => '/\S+/'),
										'required' => array('id', 'cause')),
					'request/Fixed' => array('file' => 'request_fixed.php',
										'get' => array('ids'),
										'filters' => array('ids' => '/^(?:\d+,)*\d+,?$/'),
										'required' => array('ids')),
					'request/Repaired' => array('file' => 'request_repaired.php',
										'get' => array('id'),
										'post' => array('solProblem', 'sol', 'solRecomend'),
										'filters' => array('id' => '/^\d+$/',
														   'solProblem' => '/\S+/',
														   'sol' => '/\S+/',
														   'solRecomend' => '/\S+/'),
										'required' => array('id', 'solProblem', 'sol', 'solRecomend')),
					'request/Close' => array('file' => 'request_close.php',
										'get' => array('ids'),
										'filters' => array('ids' => '/^(?:\d+,)*\d+,?$/'),
										'required' => array('ids')),
					'request/UnClose' => array('file' => 'request_unClose.php',
										'get' => array('id'),
										'post' => array('cause'),
										'filters' => array('id' => '/^\d+$/',
														   'cause' => '/\S+/'),
										'required' => array('id', 'cause')),
					'request/Wait' => array('file' => 'request_wait.php',
										'get' => array('id'),
										'post' => array('cause'),
										'filters' => array('id' => '/^\d+$/',
														   'cause' => '/\S+/'),
										'required' => array('id', 'cause')),
					'request/DoNow' => array('file' => 'request_doNow.php',
										'get' => array('ids'),
										'filters' => array('ids' => '/^(?:\d+,)*\d+,?$/'),
										'required' => array('ids')),
					'request/partner/set' => array('file' => 'request_setPartner.php',
										'get' => array('request', 'partner'),
										'filters' => array('request' => '/^\d+$/',
														   'partner' => '/^(?:0|[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/'),
										'required' => array('request', 'partner')),
					'request/contact/set' => array('file' => 'request_setContact.php',
										'get' => array('request', 'contact'),
										'filters' => array('request' => '/^\d+$/',
														   'contact' => '/^(?:0|[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/'),
										'required' => array('request', 'contact')),
					'request/sla/set' => array('file' => 'request_setSla.php',
										'get' => array('id', 'service', 'slaLevel'),
										'filters' => array('id' => '/^\d+$/',
														   'service' => '/^(?:0|[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12})$/',
														   'slaLevel' => '/^(?:critical|high|medium|low)$/'),
										'required' => array('id', 'service', 'slaLevel')),
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
					'dir/partners' => array('file' => 'dir_partners.php',
										'get' => array('card'),
										'filters' => array('card' => '/^\d+$/')),
					'equipment/info' => array('file' => 'equipment_info.php',
										'get' => array('division', 'equipment'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'equipment' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division', 'equipment')),
					'serviceList/get' => array('file' => 'serviceList_getPDF.php', 
										'get' => array('id'),
										'filters' => array('id' => '/^\d+$/'),
										'required' => array('id')),
					'file/get' => array('file' => 'file_get.php', 
										'get' => array('n', 'docGuid'),
										'filters' => array('n' => '/^\d+$/', 
														   'docGuid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('n', 'docGuid')),
					'problem/get' => array('file' => 'problem_get.php',
										'get' => array('division'),
										'filters' => array('division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('division')),
					'problem/set' => array('file' => 'problem_set.php',
										'get' => array('contract', 'division'),
										'post' => array('problem'),
										'filters' => array('contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'division' => '/^(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|\*)$/'),
										'required' => array('contract', 'division', 'problem')),
					'adm/calendar/init' => array('file' => 'adm_calendar.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/calendar/setYear' => array('file' => 'adm_calendar.php', 
										'set' => array('call' => 'setYear'),
										'get' => array('year'),
										'filter' => array('year' => '/^\d{4}$/'),
										'required' => array('call', 'year')),
					'adm/eqmodels/init' => array('file' => 'adm_eqmodels.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/services/init' => array('file' => 'adm_services.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/partners/init' => array('file' => 'adm_partners.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/contragents/init' => array('file' => 'adm_contragents.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/divisionTypes/init' => array('file' => 'adm_divisionTypes.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/users/init' => array('file' => 'adm_users.php', 
										'set' => array('call' => 'init'),
										'required' => array('call')),
					'adm/users/changePass' => array('file' => 'adm_users.php',
										'set' => array('call' => 'changePass'),
										'get' => array('id'),
										'filters' => array('id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('call', 'id')),
					'user/changePass' => array('file' => 'newpwd.php',
										'set' => array('mode' => 'init'),
										'get' => array('id', 'key'),
										'filters' => array('id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('mode', 'id', 'key')),
					'user/setPass' => array('file' => 'newpwd.php',
										'set' => array('mode' => 'cp'),
										'post' => array('newpass'),
										'required' => array('mode', 'newpass')),
					'adm/contracts/getlist' => array('file' => 'adm_contracts.php',
										'set' => array('call' => 'getlists'),
										'get' => array('field', 'id'),
										'filters' => array('field' => '/^(?:contragents|contracts|contract)$/',
														   'id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('call', 'field', 'id')),
					'adm/sla' => array('file' => 'adm_contractSLA.php',
										'get' => array('contId', 'call', 'id'),
										'filters' => array('contId' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'call' => '/^init$/',
														   'id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('contId', 'call')),
					'adm/divisions' => array('file' => 'adm_divisions.php',
										'get' => array('call', 'id'),
										'filters' => array('call' => '/^(?:init|smsChange)$/',
														   'id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('call', 'id')),
					'adm/divisionEq' => array('file' => 'adm_divisionEq.php',
										'get' => array('divId', 'call', 'id'),
										'post' => array('last'),
										'filters' => array('divId' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'call' => '/^init$/',
														   'id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'last' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('divId', 'call')),
					'adm/divisionWorkplaces' => array('file' => 'adm_divisionWorkplaces.php',
										'get' => array('divId', 'call', 'id'),
										'post' => array('last'),
										'filters' => array('divId' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'call' => '/^init$/',
														   'id' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'last' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/'),
										'required' => array('divId', 'call')),
					'adm/divisionPlanned' => array('file' => 'adm_divisionPlanned.php',
										'get' => array('divId', 'call', 'id'),
										'post' => array('field', 'service', 'sla', 'problem', 'nextDate', 'interval', 'preStart'),
										'filters' => array('divId' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'call' => '/^(?:init|getlist|update|del)$/',
														   'id' => '/^\d+$/',
														   'field' => '/^(?:service|sla)$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'sla' => '/^(?:critical|high|medium|low)$/',
														   'problem' => '/\S/',
														   'nextDate' => '/^\d{4}-\d\d-\d\d$/',
														   'interval' => '/(?:(\d+)\s*y)?\s*(?:(\d+)\s*m)?\s*(?:(\d+)\s*w)?\s*(?:(\d+)\s*d)?/',
														   'preStart' => '/^\d+$/'),
										'required' => array('divId', 'call')),
					'api/dir/divisions' => array('file' => 'api_dir_divisions.php',
										'get' => array('token', 'contract', 'format'),
										'post' => array('contract', 'format'),
										'filters' => array('token' => '/^[0-9a-f]{40}$/',
														   'contract' => '/\S+/',
														   'format' => '/json|xml/'),
										'required' => array('token', 'contract')),
					'api/dir/services' => array('file' => 'api_dir_services.php',
										'get' => array('token', 'division', 'format'),
										'post' => array('format'),
										'filters' => array('token' => '/^[0-9a-f]{40}$/',
														   'division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'format' => '/json|xml/'),
										'required' => array('token', 'division')),
					'api/request/new' => array('file' => 'api_request_new.php',
										'get' => array('token', 'division', 'service', 'slaLevel', 'format'),
										'post' => array('equipment', 'problem', 'format'),
										'filters' => array('token' => '/^[0-9a-f]{40}$/',
														   'division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'slaLevel' => '/^(?:critical|high|medium|low)$/',
														   'equipment' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/',
														   'problem' => '/\S+/',
														   'format' => '/json|xml/'),
										'required' => array('token', 'division', 'service', 'slaLevel', 'problem'))
	);
	$paramValues = array();
	foreach ($routes as $route => $def) {
		if (strpos($_REQUEST['routeRequest'], $route) === 0) {
			$rest = substr($_REQUEST['routeRequest'], strlen($route)+1);
			if ($rest === false)
				$rest = '';
			$params = explode('/', $rest);
			if (isset($def['set'])) {
				foreach($def['set'] as $key => $val)
					$paramValues[$key] = $val;
			}
			if (isset($def['get'])) {
				foreach($params as $i => $param)
					if (isset($def['get'][$i]))
						$paramValues[$def['get'][$i]] = trim($param);
			}
			if (isset($def['post'])) {
				foreach($_REQUEST as $name => $value)
					if (in_array($name, $def['post']))
						$paramValues[$name] = trim($value);
			}
			if(isset($def['filters'])) {
				foreach($paramValues as $name => $value)
					if (isset($def['filters'][$name]) && false === preg_match($def['filters'][$name], $value)) {
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