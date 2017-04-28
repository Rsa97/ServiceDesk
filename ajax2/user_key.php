<?php
	header('Content-Type: application/json; charset=UTF-8');

	session_start();
	
	$config = array('private_key_bits' => 1024,
					'private_key_type' => OPENSSL_KEYTYPE_RSA);
	$res = openssl_pkey_new($config);
	openssl_pkey_export($res, $private);
	$_SESSION['private'] = $private;
	$public = openssl_pkey_get_details($res);
	$_SESSION['public'] = $public['key'];
	echo json_encode(array('key' => $_SESSION['public']));
?>