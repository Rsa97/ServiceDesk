<?php

function formatGuid($hex) {
	if (null === $hex)
		return null;
	if (!preg_match('/^[0-9a-z]{32}$/i', $hex)) {
		$hex = unpack('H*', $hex);
		$hex = $hex[1];
	}
	if (preg_match('/^[0-9a-z]{32}$/i', $hex))
		return preg_replace('/([0-9a-z]{8})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{12})/', '$1-$2-$3-$4-$5', strtolower($hex));
	return null;
}

function nameWithInitials($lastName, $givenName, $middleName) {
	return $lastName.
			('' == $givenName ? '' : (' '.mb_substr($givenName, 0, 1, 'utf-8').'.'.
										('' == $middleName ? '' : (' '.mb_substr($middleName, 0, 1, 'utf-8').'.'))));
}

function nameFull($lastName, $givenName, $middleName) {
	return $lastName.
		   ('' == $givenName ? '' : (' '.$givenName.('' == $middleName ? '' : (' '.$middleName))));
}

function timeToSOAP($time) {
	return date_format(new DateTime($time), 'c');
}

function rtf_encode($str) {
	if ($str == '')
		$str = ' ';
	$ret = '';
	$str = preg_replace('/\n/', '\par ', $str);
	$win = iconv('UTF-8', 'CP1251', $str);
		
	foreach (str_split($win) as $char)
		if ($char >= ' ' && $char <= '~')
			$ret .= $char;
		else
			$ret .= sprintf("\\'%02x",ord($char));
	return $ret;
}


$slaLevels = array('critical' => 'Критический', 'high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий');

$statusNames = array('received' => 'Получена',
					 'preReceived' => 'Получена',
					 'accepted' => 'Принята к исполнению',
					 'fixed' => 'Работоспособность восстановлена',
					 'repaired' => 'Работа завершена',
					 'closed' => 'Закрыта',
					 'canceled' => 'Отменена',
					 'planned' => 'Плановая',
					 'onWait' => 'Ожидание комплектующих');


?>