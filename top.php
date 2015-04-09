<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" style="width: 0px; height: 0px;">
<meta http-equiv="Content-Language" content="ru">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<head>
	<link rel="stylesheet" type="text/css" href="style.css" />
</head>



<?php
	include_once('config.php');
	echo "<table align='right' style='padding-top: 5px;'><tr><td>" . $_SESSION['secondName'] . " " . $_SESSION['firstName'] . " " . $_SESSION['middleName'] ." (" . $_SESSION['myID'] . ")</td><td align='right'><ul class='secondMenu'><li><a href='logout.php'><img src='/img/quit.png'/>Выход</a></li></ul></td></tr></table>";
?>