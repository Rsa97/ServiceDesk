<?php

function formatGuid($hex) {
	if (null === $hex)
		return null;
	if (!preg_match('/^[0-9a-z]{32}$/i', $hex)) {
		$hex = unpack('H*', $hex);
		$hex = $hex[1];
	}
	if (preg_match('/^[0-9a-z]{32}$/i', $hex))
		return preg_replace('/([0-9a-z]{8})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{12})/', 
							'$1-$2-$3-$4-$5', strtolower($hex));
	return null;
}

function nameWithInitials($lastName, $givenName, $middleName) {
	$res = array($lastName);
	if ('' != $givenName) {
		$res[] = mb_substr($givenName, 0, 1, 'utf-8').'.';
		if ('' != $middleName) {
			$res[] = mb_substr($middleName, 0, 1, 'utf-8').'.';
		}
	}
	return implode(' ', $res);
}

function nameFull($lastName, $givenName, $middleName) {
	$res = array($lastName);
	if ('' != $givenName) {
		$res[] = $givenName;
		if ('' != $middleName) {
			$res[] = $middleName;
		}
	}
	return implode(' ', $res);
}

function timeToSOAP($time) {
	return date_format(new DateTime($time), 'c');
}

function getUserData($db, $user_guid) {
	$rights = '';
	$partner_guid = '';
	try {
		$req = $db->prepare("SELECT `rights`, HEX(`partner_guid`) ".
								"FROM `users` WHERE `guid` = UNHEX(:user_guid)");
		$req->execute(array('user_guid' => $user_guid));
		if ($row = $req->fetch(PDO::FETCH_NUM)) {
			list($rights, $partner_guid) = $row;
		}
		$result = array('result' => 'ok', 'rights' => $rights, 'partner_guid' => $partner_guid);
	} catch (PDOException $e) {
		$result = array('result' => 'error',
						'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить '.
									'страницу через некоторое время.', 
						'orig' => 'MySQL error'.$e->getMessage());
	}
	return $result;
}

?>