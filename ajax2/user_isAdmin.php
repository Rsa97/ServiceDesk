<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();
if (isset($_SESSION['user']) && isset($_SESSION['user']['rights']) && $_SESSION['user']['rights'] == 'admin')
	echo json_encode(Array('isAdmin' => 'ok'));
else
	echo json_encode(Array('isUser' => 'ok'));
?>