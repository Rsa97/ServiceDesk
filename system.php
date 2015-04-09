<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" style="width: 0px; height: 0px;">
<meta http-equiv="Content-Language" content="ru">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<head>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<script src="js/jquery.js"></script>
	<script src="js/jquery-ui.js"></script>
	<script src="js/functions.js"></script>
</head>

<?php
include_once('config.php');
checkLoginStatus();
echo "<title>Интерфейс [".getInterfaceName($_SESSION['userGroups'])."] - Служба технической поддержки, Компания Содействие</title>";


echo "
<body style='margin-top: 0px; margin-bottom: 0px; margin-left: 0px; margin-right: 0px;'>
	<div id='mainPage'>
		<div id='topDiv'></div>
		<div id='cardDiv' style='visibility:hidden'></div>
		<div id='newCardDiv' style='visibility:hidden'></div>
		<div id='workflowDiv'></div>
		<div id='bottomDiv'>
			<center>Содействие 2013-2014</center>
		</div>
	</div>
</body>

";

?>
