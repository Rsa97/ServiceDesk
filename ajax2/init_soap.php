<?php

include '../config/soap.php';

$soapParameters = array('login' => $soap_user, 'password' => $soap_pass, "cache_wsdl" => 0);
try {
	$soap = new SoapClient($soap_uri, $soapParameters);
} catch (Exception $e) {
	$soap = false;
}

?>