<?php

	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php");
	include_once("{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php");
	
	header('Content-Type: application/json; charset=UTF-8');

	$user_guid = (isset($params['user_guid']) ? $params['user_guid'] : null);
	
	$result = array();
	
	try {
		$req = $db->prepare("SELECT `lastName`, `firstName`, `middleName`, `rights`, `email`, 
									`phone`, `cellphone`, `jid`, `address`, `filter` FROM `users` ".
								"WHERE `guid` = UNHEX(:user_guid)");
		$req->execute(array('user_guid' => $user_guid));
		if ($row = $req->fetch(PDO::FETCH_NUM)) {
			list($lastName, $firstName, $middleName, $rights, $email, $phone, $cellphone, $jid, 
				 $address, $filter) = $row;
			if (null == $filter || '' == $filter) {
				$filter = array('service' => null, 'contract' => null, 'division' => null, 
								'text' => '', 'onlyMy' => false,
								'from' => date('Y-m-d', strtotime('-3 months')), 
								'to' => date('Y-m-d', strtotime('now')));
			} else {
				$filter = json_decode($filter);
			}
			$result = array(
					'fullName' => nameFull($lastName, $firstName, $middleName), 
					'shortName' => nameWithInitials($lastName, $firstName, $middleName),
					'rights' => $rights, 'email' => $email, 'phone' => $phone, 'cellphone' => $cellphone,
					'jid' => $jid, 'address' => $address, 'filter' => $filter
				);
		}
	} catch (PDOException $e) {
		echo json_encode(array(	'result' => 'error',
								'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить '.
											'страницу через некоторое время.', 
								'orig' => 'MySQL error'.$e->getMessage()));
		exit;
	}
	
	echo json_encode(array('result' => 'ok', 'info' => $result, 'expireTime' => 24*60*60));
	exit;
?>	