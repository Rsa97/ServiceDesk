<?php

	function returnJSON($data) {
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data);
	}
	
	function buildDef($types, $args) {
		$res = '';
		
	}

	$session = session_start();
	$op = (isset($_REQUEST['op']) ? $_REQUEST['op'] : 'init');
/*	if (isset($_SESSION['url']) || isset($_SESSION['login']) && isset($_SESSION['pass'])) {
		$soapParameters = Array('login' => $_SESSION['login'], 'password' => $_SESSION['pass'], 'cache_wsdl' => 0);
		$client = new SoapClient($_SESSION['url'].'?wsdl', $soapParameters);
	} */
	switch($op) {
		case 'getWsdl':
			if (!isset($_REQUEST['url']) || !isset($_REQUEST['login']) || !isset($_REQUEST['pass']))
				exit;
			$url = preg_replace('/\?.*$/', '', $_REQUEST['url']);
			try {
				$soapParameters = Array('login' => $_REQUEST['login'], 'password' => $_REQUEST['pass'], 'cache_wsdl' => 0);
				$client = new SoapClient($url.'?wsdl', $soapParameters);
				$funcs = $client->__getFunctions();
				$types = $client->__getTypes();
			} catch (Exception $e) {
				returnJSON(array('error' => $e->getMessage()));
				exit;
			}
			$_SESSION['login'] = $_REQUEST['login'];
			$_SESSION['pass'] = $_REQUEST['pass'];
			$_SESSION['url'] = $url;
			$_SESSION['funcs'] = $funcs;
			$_SESSION['types'] = $types;
			$curl = curl_init();
			curl_setopt_array($curl, array(CURLOPT_URL => $url.'?wsdl', CURLOPT_RETURNTRANSFER => true,
										   CURLOPT_FOLLOWLOCATION => true));
			if ('' != $_REQUEST['login'])
				curl_setopt($curl, CURLOPT_USERPWD, $_REQUEST['login']);
			if ('' != $_REQUEST['pass'])
				curl_setopt($curl, CURLOPT_USERPWD, $_REQUEST['login'].':'.$_REQUEST['pass']);
			$wsdl = curl_exec($curl);
			curl_close($curl);
			$_SESSION['wsdl'] = $wsdl;
			$res = '';
			foreach($funcs as $i => $func) {
				$res .= "<option value='{$i}'>".htmlspecialchars($func)."</option>"; 
			}
			returnJSON(array('funcSelect' => $res, '_url' => $url, '_login' => $_REQUEST['login'], '_pass' => $_REQUEST['pass'], 'wsdl' => $wsdl));
			exit;
		case 'getParamsDef':
			if (!isset($_REQUEST['funcId']) || !isset($_SESSION['funcs']) || !isset($_SESSION['types']) ||
				!isset($_SESSION['funcs'][$_REQUEST['funcId']]))
				exit;
			$func = $_SESSION['funcs'][$_REQUEST['funcId']];
			$res = '';
			if (preg_match('/^.*?\((.*?)\)$/', $func, $match)) {
				$args = split(',', $match[1]);
				$res = buildDef($_SESSION['types'], $args);
			}
			exit;
		case 'init':
			$url = (isset($_SESSION['url']) ? $_SESSION['url'] : ''); 
			$login = (isset($_SESSION['login']) ? $_SESSION['login'] : '');
			$pass = (isset($_SESSION['pass']) ? $_SESSION['pass'] : '');
			echo <<<HTML
<!DOCTYPE html>
<html>
  <head>
    <meta http-equiv='Content-Language' content='ru'>
    <meta http-equiv='Content-Type' content='text/html; charset=utf-8'>
    <script type='text/javascript' src='js/jquery.js'></script>
    <script type='text/javascript' src='soapTest.js'></script>
    <link rel='shortcut icon' href='/favicon.ico' type='image/x-icon'>
    <link rel='icon' href='/favicon.ico' type='image/x-icon'>
  </head>
  <body>
  	<div class='header'>
  	    <form style='width:100%'>
  	    	<label>Адрес сервиса: <input type='text' class='headInput' id='url' name='url' value='{$url}'></label>
  	    	<label>Логин: <input type='text' class='headInput' id='login' name='login' value='{$login}'></label>
  	    	<label>Пароль: <input type='password' class='headInput' id='pass' name='pass' value='{$pass}'></label>
  	    	<input type='button' class='headInput' id='getWsdl' name='getWsdl' value='Получить WSDL'>
  	    </form>
  	    <form id='functionSelectorForm' style='width:100%''>
  	    	<label>Выберите функцию: <select class='headInput' id='funcSelect' name='funcSelect' ></select></label>
  	    </form>
  	</div>
  	<div class='params'>
  	</div>
  	<div class='result'>
  	</div>
  </body>
</html>
HTML;
			exit;
	}
		
?>