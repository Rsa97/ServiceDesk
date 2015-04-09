<?php
	header('Content-Type: application/json; charset=UTF-8');

	include('../config/db.php');
	session_start();
	if (!isset($_SESSION['user'])) {
		echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
		exit;
	}
	if (!isset($_REQUEST['op'])) {
		echo json_encode(array('error' => 'Ошибка в параметрах запроса.'));
		exit;
	}
	$byUser = 1;
	$byActive = 1;
	$byAllowed = 0;
	if (isset($_SESSION['user']['rights'])) 
  		switch($_SESSION['user']['rights']) {
  			case 'admin':
        		$byActive = 0;
			case 'engeneer':
      		case 'operator':
				$byUser = 0;
				break;
			case 'partner':
        		$byAllowed = 1;
			case 'client':
				break;
		}

	// Подключаемся к MySQL
	$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
	if ($mysqli->connect_error) {
		echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
		exit;
	}
	$mysqli->query("SET NAMES utf8");
	
	$curDiv = (isset($_REQUEST['div']) ? intval($_REQUEST['div']) : 0);
	$servNum = (isset($_REQUEST['num']) ? '%'.$_REQUEST['num'].'%' : '%');
	$result = array();
	$limitDiv = 0;
	switch($_REQUEST['op']) {
		case 'getList':
			$req = $mysqli->prepare("SELECT count(*) ".
										"FROM `equipment` AS `eq` ".
										"WHERE `eq`.`contractDivisions_id` = ? ".
											"AND `eq`.`onService` = 1 ".
											"AND `eq`.`serviceNumber` LIKE ?");
			$req->bind_param('is', $curDiv, $servNum);
			$req->bind_result($count); 
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
				exit;
			}
			$req->fetch();
			$req->close();
			$req = $mysqli->prepare("SELECT `eq`.`serviceNumber`, `eq`.`rem`, `em`.`name`, `emfg`.`name`, `eqst`.`description` ".
										"FROM `equipment` AS `eq` ".
										"LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
										"LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `em`.`equipmentManufacturers_id` ".
										"LEFT JOIN `equipmentSubTypes` AS `eqst` ON `eqst`.`id` = `em`.`equipmentSubTypes_id` ".
										"WHERE `eq`.`contractDivisions_id` = ? ".
											"AND `eq`.`onService` = 1 ".
											"AND `eq`.`serviceNumber` LIKE ? ".
											"ORDER BY `eqst`.`description`, `emfg`.`name`, `em`.`name`, `eq`.`serviceNumber`");
			$req->bind_param('is', $curDiv, $servNum);
			$req->bind_result($eqServNum, $eqRem, $eqModel, $eqMfg, $eqSubType);
			if (!$req->execute()) {
				echo json_encode(array('error' => 'Внутренняя ошибка сервера'));
				exit;
			}
			$prevSubType = '';
			$list = "<ul id='snList'>";
			while ($req->fetch()) {
				if ($eqRem != '')
					$eqRem = "(".htmlspecialchars($eqRem).")";
				if ($prevSubType != $eqSubType) {
					if ($prevSubType != '')
						$list .= "</ul>";				
					$list .= "<li class='collapsed'><span class='ui-icon ui-icon-folder-collapsed'></span>{$eqSubType}<ul".($count == 1 ? " class='single'" : "").">";
				}
				$prevSubType = $eqSubType;
				$list .= "<li data-id='{$eqServNum}'>{$eqServNum} - {$eqMfg} {$eqModel} {$eqRem}"; 
			}
			$list .= "</ul></ul>";
			$req->close();
			$result['selectEqList'] = $list;
			break;
	}
	echo json_encode($result);
?>