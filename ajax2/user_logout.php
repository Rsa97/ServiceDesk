<?php
header('Content-Type: application/json; charset=UTF-8');

session_destroy();

echo json_encode(array('redirect' => "/index.html"));
?>
	