<?php

header('Content-Type: application/json; charset=UTF-8');

$date = date_create();
echo json_encode(array('time' => date_format(date_create(), 'd.m.Y H:i'),
					   'timeEn' => date_format(date_create(), 'm/d/Y H:i')));
exit;
?>