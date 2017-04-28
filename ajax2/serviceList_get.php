<?php

include 'common.php';
include 'init.php';

// Получаем документ с проверкой прав
try {
	$req = $db->prepare("SELECT DISTINCT `e`.`lastName`, `e`.`firstName`, `e`.`middleName`, `p`.`name`, `p`.`address`, `c`.`number`, ".
										 "`div`.`name`, IFNULL(`divca`.`name`, `cca`.`name`), `cont`.`lastName`, `cont`.`firstName`, ".
										 "`cont`.`middleName`, `cont`.`phone`, `cont`.`email`, `rq`.`problem`, `srv`.`name`, ".
										 "`eq`.`serviceNumber`, `rq`.`slaLevel`, `rq`.`createdAt`, `rq`.`repairedAt`, `rq`.`solution`, ".
										 "`rq`.`solutionProblem`, `rq`.`solutionRecomendation` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`id` = :requestId ".
            					"AND `rq`.`currentState` IN ('repaired', 'closed') AND `div`.`guid` = `rq`.`contractDivision_guid` ".
            				"JOIN `contracts` AS `c` ON `c`.`guid` = `div`.`contract_guid` ".
            					"AND (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd` OR `rq`.`currentState` NOT IN ('closed', 'canceled')) ".
            				"LEFT JOIN `contragents` AS `divca` ON `divca`.`guid` = `div`.`contragent_guid` ".
            				"LEFT JOIN `contragents` AS `cca` ON `cca`.`guid` = `c`.`contragent_guid` ".
            				"LEFT JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `rq`.`contractDivision_guid` ".
            				"LEFT JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
            				"LEFT JOIN `users` AS `e` ON `e`.`guid` = `rq`.`engineer_guid` ".
							"LEFT JOIN `users` AS `cont` ON `cont`.`guid` = `rq`.`contactPerson_guid` ".
            				"LEFT JOIN `users` AS `pu` ON `pu`.`guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
            				"LEFT JOIN `partners` AS `p` ON `p`.`guid` = `pu`.`partner_guid` ".
            				"LEFT JOIN `services` AS `srv` ON `srv`.`guid` = `rq`.`service_guid` ".
            				"LEFT JOIN `equipment` AS `eq` ON `eq`.`guid` = `rq`.`equipment_guid` ".
          					"WHERE (:byClient = 0 OR `ucd`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', '')) ".
          							"OR `uc`.`user_guid` = UNHEX(REPLACE(:userGuid, '-', ''))) ".
            					"AND (:byPartner = 0 OR `rq`.`partner_guid` = UNHEX(REPLACE(:partnerGuid, '-', '')))");
	$req->execute(array('byClient' => $byClient, 'userGuid' => $userGuid, 'byPartner' => $byPartner, 'partnerGuid' => $partnerGuid,
    					'requestId' => $paramValues['id']));
	if (!($row = $req->fetch(PDO::FETCH_NUM))) {
		header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
		exit;
	}
} catch (PDOException $e) {
	header("{$_SERVER['SERVER_PROTOCOL']} 500 Internal Server Error ".$e->getMessage());
	exit;
}

list($engLN, $engFN, $engMN, $partnerName, $partnerAddress, $contractNumber, $div, $contragent, $contLN, $contFN, $contMN,
	 $contPhone, $contEmail, $problem, $srvName, $servNum, $slaLevel, $createdAt, $repairedAt, $sol, $solProblem, $solRecomend) = $row;
$engineerName = nameFull($engLN, $engFN, $engMN);
$engineerSName = nameWithInitials($engLN, $engFN, $engMN);
$contactName = nameFull($contLN, $contFN, $contMN);
$contactSName = nameWithInitials($contLN, $contFN, $contMN);

$userName = nameFull($_SESSION['user']['lastName'], $_SESSION['user']['firstName'], $_SESSION['user']['middleName']);
$userSName = nameWithInitials($_SESSION['user']['lastName'], $_SESSION['user']['firstName'], $_SESSION['user']['middleName']);

$template = file_get_contents('../templates/servicelist.rtf');
if ($template === FALSE) {
	header("{$_SERVER['SERVER_PROTOCOL']} 500 Internal Server Error ");
	exit;
}
$template = preg_replace('/#date#/', date('d.m.Y'), $template);
$template = preg_replace('/#requestNumber#/', $paramValues['id'], $template);
$template = preg_replace('/#number#/', $paramValues['id'].'-01', $template);
//$template = preg_replace('/#engeneer#/', rtf_encode($engineerName), $template);
//$template = preg_replace('/#engeneerShort#/', rtf_encode($engineerSName), $template);
$template = preg_replace('/#engeneer#/', rtf_encode($userName), $template);
$template = preg_replace('/#engeneerShort#/', rtf_encode($userSName), $template);
$template = preg_replace('/#partner#/', rtf_encode($partnerName), $template);
$template = preg_replace('/#partnerAddress#/', rtf_encode($partnerAddress), $template);
$template = preg_replace('/#contract#/', rtf_encode('Договор '.$contractNumber), $template);
$template = preg_replace('/#division#/', rtf_encode(($div == $contragent ? '' : $contragent.'\par ').$div), $template);
$template = preg_replace('/#client#/', rtf_encode($contactName), $template);
$template = preg_replace('/#clientData#/', rtf_encode(implode(', ', array($contPhone, $contEmail))), $template);
$template = preg_replace('/#problem#/', rtf_encode(preg_replace('/\x0A/', '\\par ', $problem)), $template);
$template = preg_replace('/#service#/', rtf_encode($srvName), $template);
$template = preg_replace('/#serviceNumber#/', rtf_encode($servNum), $template);
$template = preg_replace('/#priority#/', rtf_encode($slaLevels[$slaLevel]), $template);
$date = '';
if (preg_match('/(\d\d\d\d)-(\d\d)-(\d\d)/', $createdAt, $match))
	$date = $match[3].'.'.$match[2].'.'.$match[1];
$template = preg_replace('/#requestDate#/', $date, $template);
$date = '';
if (preg_match('/(\d\d\d\d)-(\d\d)-(\d\d)/', $repairedAt, $match))
	$date = $match[3].'.'.$match[2].'.'.$match[1];
$template = preg_replace('/#closeDate#/', $date, $template);
$solution = array();
if ($solProblem != '')
	$solution[] = $solProblem; 
if ($sol != '')
	$solution[] = $sol; 
if ($solRecomend != '')
	$solution[] = $solRecomend; 
$template = preg_replace('/#solution#/', rtf_encode(implode('\par ', $solution)), $template);
$fileName = 'ServiceList'.$requestNum.'.rtf';
$fileSize = strlen($template);
header("Content-Type: application/rtf");
header("Content-Disposition: attachment; filename={$fileName}");
header("Content-Length: {$fileSize}");
ob_clean();
flush();
echo $template;
exit;

?>