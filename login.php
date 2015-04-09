<?php
require_once('config.php');

if (isset($_POST['username']) && ($_POST['username'] <> '') && isset($_POST['password']) && ($_POST['password'] <> '')) {
	login($_POST['username'], $_POST['password']);
}
if (isset($_SESSION['username'])) {
	echo "
		<script lang='JavaScript'>
		window.location.href = 'system.php'
		</script>
		";
	} else {
		session_destroy();
		echo "Не авторизован";
		echo "
			<script lang='JavaScript'>
			window.location.href = 'index.php'
			</script>
		";
}
?>