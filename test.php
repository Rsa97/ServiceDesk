<?php
	$query = (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : null);
	$header = (isset($_SERVER['HTTP_MYHEADER']) ? $_SERVER['HTTP_MYHEADER'] : null);
	$body = file_get_contents('php://input', 'r');
	echo json_encode(array('method' => $_SERVER['REQUEST_METHOD'], 
						   'query' => $query,
						   'header' => $header, 
						   'body' => $body));
?>