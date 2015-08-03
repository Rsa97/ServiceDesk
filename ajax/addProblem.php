<?php
	header('Content-Type: application/json; charset=UTF-8');
	include "../config/db.php";

	session_start();
	if (!isset($_SESSION['user'])) {
		echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
  
	if (!isset($_REQUEST['op'])) {
		echo json_encode(array('error' => 'Ошибка в параметрах'));
		exit;
	}
	$mysqli = mysqli_init(); 
	$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName, 3306, null, MYSQLI_CLIENT_FOUND_ROWS);
	if ($mysqli->connect_error) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");

	if (!isset($_SESSION['user']) || ($_SESSION['user']['rights'] != 'admin' && 
		$_SESSION['user']['rights'] != 'operator' && $_SESSION['user']['rights'] != 'engeneer')) {
		echo json_encode(array('error' => 'Недостаточно прав'));
		exit;
	}

	// Строим фильтр из сессии или по умолчанию
	if (isset($_SESSION['filter'])) {
		$divFilter = $_SESSION['filter']['divFilter'];
		$byCntrAgent = $_SESSION['filter']['byCntrAgent'];
		$byDiv = $_SESSION['filter']['byDiv'];
	} else {
		$divFilter = 0;
		$byCntrAgent = 1;
		$byDiv = 1;
	}
	if (isset($_SESSION['filter'])) {
		$srvFilter = $_SESSION['filter']['srvFilter'];
		$byService = $_SESSION['filter']['srvFilter'];
	} else {
		$srvFilter = 0;
		$byService = 1;
	}
	$byContrTime = ($_SESSION['user']['rights'] == 'admin' ? 0 : 1);

	$_SESSION['time'] = time();
	session_commit();
	switch($_REQUEST['op']) {
		case 'getContragents':
			// Получаем список доступных контрагентов
			$req = $mysqli->prepare(
				"SELECT DISTINCT `ca`.`id`, `ca`.`name` ".
      				"FROM `contractDivisions` AS `div` ".
					"JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
					"JOIN `contragents` AS `ca` ON `ca`.`id` = `c`.`contragents_id` ".
					"WHERE (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            			"AND (? = 0 OR `div`.`id` = ?) ".
            			"AND (? = 0 OR `c`.`contragents_id` = ?) ".
            			"AND `div`.`isDisabled` = 0 ".
					"ORDER BY `ca`.`name`");
			$req->bind_param('iiiii', $byContrTime, $byDiv, $divFilter, $byCntrAgent, $divFilter);
			$req->bind_result($caId, $caName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
				exit;
			}
			$list = '';
			while ($req->fetch())
				$list .= "<option value='{$caId}'>".htmlspecialchars($caName);
			$req->close();
			$result['apContragent'] = $list;
			break;
		case 'getContracts':
			// Получаем список доступных контрактов
			if (!isset($_REQUEST['caId'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			$req = $mysqli->prepare(
				"SELECT DISTINCT `c`.`id`, `c`.`number` ".
      				"FROM `contractDivisions` AS `div` ".
					"JOIN `contracts` AS `c` ON `c`.`id` = `div`.`contracts_id` ".
					"WHERE (? = 0 OR (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)) ".
            			"AND (? = 0 OR `div`.`id` = ?) ".
            			"AND `c`.`contragents_id` = ? ".
            			"AND `div`.`isDisabled` = 0 ".
					"ORDER BY `c`.`number`");
			$req->bind_param('iiii', $byContrTime, $byDiv, $divFilter, $_REQUEST['caId']);
			$req->bind_result($cId, $cNum);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
				exit;
			}
			$list = '';
			while ($req->fetch())
				$list .= "<option value='{$cId}'>".htmlspecialchars($cNum);
			$req->close();
			$result['apContract'] = $list;
			break;
		case 'getDivisions':
			// Получаем список доступных контрактов
			if (!isset($_REQUEST['cId'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			$req = $mysqli->prepare(
				"SELECT DISTINCT `div`.`id`, `div`.`name` ".
      				"FROM `contractDivisions` AS `div` ".
            		"WHERE (? = 0 OR `div`.`id` = ?) ".
            			"AND `div`.`contracts_id` = ? ".
            			"AND `div`.`isDisabled` = 0 ".
					"ORDER BY `div`.`name`");
			$req->bind_param('iii', $byDiv, $divFilter, $_REQUEST['cId']);
			$req->bind_result($divId, $divName);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
				exit;
			}
			$list = "<option value='0'>Все";
			while ($req->fetch())
				$list .= "<option value='{$divId}'>".htmlspecialchars($divName);
			$req->close();
			$result['apDivision'] = $list;
			break;
		case 'getProblem':
			// Получаем список доступных контрактов
			if (!isset($_REQUEST['cId']) || !isset($_REQUEST['divId'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			if ($_REQUEST['divId'] == 0) {
				$problem = '';
			} else {
				$req = $mysqli->prepare("SELECT `addProblem` FROM `contractDivisions` WHERE `id` = ?");
				$req->bind_param('i', $_REQUEST['divId']);
				$req->bind_result($problem);
				if (!$req->execute()) {
					echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
					exit;
				}
				$req->fetch();
				$req->close();
			}
			$result['_apProblem'] = $problem;
			break;
		case 'setProblem':
			// Получаем список доступных контрактов
			if (!isset($_REQUEST['cId']) || !isset($_REQUEST['divId']) || !isset($_REQUEST['problem'])) {
				echo json_encode(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			$problem = trim($_REQUEST['problem']);
			if ($_REQUEST['divId'] == 0) {
				$req = $mysqli->prepare("UPDATE IGNORE `contractDivisions` ".
											"SET `addProblem` = CONCAT(`addProblem`, IF(`addProblem` = '', '', '\n'), ?) ".
											"WHERE `contracts_id` = ? AND `isDisabled` = 0");
				$req->bind_param('si', $problem, $_REQUEST['cId']);
			} else {
				$req = $mysqli->prepare("UPDATE IGNORE `contractDivisions` SET `addProblem` = ? WHERE `id` = ?");
				$req->bind_param('si', $problem, $_REQUEST['divId']);
			}
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
				exit;
			}
			if ($mysqli->affected_rows == 0) {
				echo json_encode(array('error' => 'Ошибка в параметрах'));
				exit;
			}
			$req->close();
			$result['ok'] = 1;
			break;
		default:
			echo json_encode(array('error' => 'Ошибка в параметрах'));
			exit;
	}
	echo json_encode($result);
	$mysqli->close();
?>
