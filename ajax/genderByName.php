<?php	
	$gbn_byFirstName = array(
		array('last' => 1, 'man' => 0.9,  'woman' => 0,    'ends' => array('й')),
		array('last' => 2, 'man' => 0.3,  'woman' => 0,    'ends' => array('он', 'ов', 'ав', 'ам', 'ол', 'ан', 'рд', 'мп')),
		array('last' => 1, 'man' => 0.01, 'woman' => 0,    'ends' => array('б', 'в', 'г', 'д', 'ж', 'з', 'й', 'к', 'л', 'м', 'н', 'п', 'р', 'с', 'т', 'ф', 'х', 'ц', 'ч', 'ш', 'щ')),
		array('last' => 1, 'man' => 0.02, 'woman' => 0,    'ends' => array('ь')),
		array('last' => 2, 'man' => 0,    'woman' => 0.1,  'ends' => array('вь', 'фь', 'ль')),
		array('last' => 2, 'man' => 0,    'woman' => 0.04, 'ends' => array('ла')),
		array('last' => 3, 'man' => 0,    'woman' => 0.2,  'ends' => array('лья', 'вва', 'ока', 'ука', 'ита')),
		array('last' => 3, 'man' => 0,    'woman' => 0.15, 'ends' => array('има')),
		array('last' => 3, 'man' => 0,    'woman' => 0.5,  'ends' => array('лия', 'ния', 'сия', 'дра', 'лла', 'кла', 'опа')),
		array('last' => 4, 'man' => 0,    'woman' => 0.5,  'ends' => array('льда', 'фира', 'нина', 'лита')));
	$gbn_byLastName = array(
		array('last' => 2, 'man' => 0.4,  'woman' => 0,    'ends' => array('ов', 'ин', 'ев', 'ий', 'ёв', 'ый', 'ын', 'ой')),
		array('last' => 3, 'man' => 0,    'woman' => 0.4,  'ends' => array('ова', 'ина', 'ева', 'ёва', 'ына')),
		array('last' => 2, 'man' => 0,    'woman' => 0.4,  'ends' => array('ая')));
	$gbn_byMiddleName = array(
		array('last' => 2, 'man' => 12,   'woman' => 0,    'ends' => array('ич')),
		array('last' => 2, 'man' => 0,    'woman' => 12,   'ends' => array('на')));

	function genderByName($detect, $name) {
		$man = 0;
		foreach($detect as $d) {
			if (in_array(mb_substr($name, mb_strlen($name, 'utf-8')-$d['last'], $d['last'], 'utf-8'), $d['ends'])) {
				$man += $d['man'];
				$man -= $d['woman'];
			}
		}
		return $man;
	}

    function genderByNames($firstName, $middleName, $lastName) {
		global $gbn_byFirstName, $gbn_byLastName, $gbn_byMiddleName;
		$man = 0;
		if ($firstName != '')
			$man += genderByName($gbn_byFirstName, $firstName);
		if ($lastName != '')
			$man += genderByName($gbn_byLastName, $lastName);
		if ($middleName != '')
			$man += genderByName($gbn_byMiddleName, $middleName);
		return $man;
	}
?>