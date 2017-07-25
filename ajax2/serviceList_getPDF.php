<?php

include 'common.php';
include 'init.php';

// Получаем документ с проверкой прав
try {
	$req = $db->prepare("SELECT DISTINCT `e`.`lastName`, `e`.`firstName`, `e`.`middleName`, `p`.`name`, `p`.`address`, `c`.`number`, ".
										 "`div`.`name`, IFNULL(`divca`.`name`, `cca`.`name`), `cont`.`lastName`, `cont`.`firstName`, ".
										 "`cont`.`middleName`, `cont`.`phone`, `cont`.`email`, `rq`.`problem`, `srv`.`name`, ".
										 "`eq`.`serviceNumber`, `rq`.`slaLevel`, `rq`.`createdAt`, `rq`.`repairedAt`, `rq`.`solution`, ".
										 "`rq`.`solutionProblem`, `rq`.`solutionRecomendation`, `rq`.`guid` ".
          					"FROM `requests` AS `rq` ".
            				"JOIN `contractDivisions` AS `div` ON `rq`.`id` = :requestId ".
            					"AND `rq`.`currentState` IN ('accepted', 'fixed', 'repaired', 'closed') AND `div`.`guid` = `rq`.`contractDivision_guid` ".
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
	 $contPhone, $contEmail, $problem, $srvName, $servNum, $slaLevel, $createdAt, $repairedAt, $sol, $solProblem, $solRecomend,
	 $requestGuid) = $row;
	 
$engineerName = nameFull($engLN, $engFN, $engMN);
$engineerSName = nameWithInitials($engLN, $engFN, $engMN);
$contactName = nameFull($contLN, $contFN, $contMN);
$contactSName = nameWithInitials($contLN, $contFN, $contMN);
$userName = nameFull($_SESSION['user']['lastName'], $_SESSION['user']['firstName'], $_SESSION['user']['middleName']);
$userSName = nameWithInitials($_SESSION['user']['lastName'], $_SESSION['user']['firstName'], $_SESSION['user']['middleName']);

$requestGuid = formatGuid($requestGuid);

$barcode = '';
$barcodeHash = '';
include 'init_soap.php';
if (false !== $soap) {
	try {
		$soapReq = array('sd_request_table' => array(array('GUID' => $requestGuid)));
		$res = $soap->sd_Request_getBarcode($soapReq);
		$answer = $res->return->sd_request_row;
		if (is_array($answer))
			$answer = $answer[0];
		if (1 == $answer->ResultSuccessful) {
			$barcode = $answer->barcode;
			$barcodeHash = $answer->barcodehash;
		}
	} catch (Exception $e) {
	}
}

$html = "<!DOCTYPE html><html><head>";
$html .= "<style type='text/css'>";
$html .= "body {width: 210mm; padding: 10mm 10mm 10mm 20mm; font-size: 8pt;} ";
$html .= "h1 {font-size: 12pt; font-weight: bold; text-align: center; margin: 0;} ";
$html .= "h2 {font-size: 10pt; font-weight: normal; text-align: center; margin: 0;} ";
$html .= "h3 {font-size: 8pt; text-align: left; margin: 8pt 0 0 0;} ";
$html .= "h4 {font-size: 8pt; font-weight: normal; margin: 8pt 0 0 0;} ";
$html .= "table {border: 1px solid black; border-collapse: collapse; width: 100%} ";
$html .= "td {border: 1px solid black; padding: 0.2mm 1.9mm 0.2mm 1.9mm;} ";
$html .= "td:nth-child(1) {width: 53mm;} ";
$html .= "td:nth-child(2) {width: 116mm;} ";
$html .= "td.frame {width: 100%; height: 15mm; vertical-align: top;} ";
$html .= "table.sign, table.head {border: 0} ";
$html .= "table.sign td {border: 0; text-align: center; width: 50%;} ";
$html .= "table.head td {border: 0; text-align: center;} ";
$html .= "span.bold {font-weight: bold;} ";
$html .= "span.small {font-size: 6pt;} ";
$html .= ".barcode {font-family: code128; text-align: center; font-size: 48pt;} ";
$html .= "</style></head><body>";

$html .= "<table class='head'><tbody>";
$html .= "<tr><td rowspan='2'><h1>СЕРВИСНЫЙ ЛИСТ №&nbsp;{$paramValues['id']}-01</h1>";
$html .= "<h2>от ".date('d.m.Y')."<br>";
$html .= "по заявке № {$paramValues['id']}</h2>";
$html .= "<td class='barcode'>".htmlspecialchars($barcode);
$html .= "<tr><td>".htmlspecialchars($barcodeHash);
$html .= "</table>";
$html .= "<h3>1. Данные Исполнителя</h3>";
$html .= "<table><tbody>";
$html .= "<tr><td>Исполнитель<td>".htmlspecialchars("ООО «Содействие»");
$html .= "<tr><td>Адрес Исполнителя<td>".htmlspecialchars("Россия, 167004, Республика Коми, город Сыктывкар, улица Первомайская, дом № 149, (8212) 214808; (8212) 202974, all@sodrk.ru");
$html .= "<tr><td>Подрядчик, Исполнитель работ<td>".htmlspecialchars($partnerName);
$html .= "<tr><td>Адрес Подрядчика<td>".htmlspecialchars($partnerAddress);
$html .= "<tr><td>Ф.И.О. исполнителя работ<td>".htmlspecialchars($userName);
$html .= "</table>";
$html .= "<h3>2. Данные Получателя услуг</h3>";
$html .= "<table><tbody>";
$html .= "<tr><td>Получатель<td>".($div == $contragent ? '' : htmlspecialchars($contragent).'<br>').htmlspecialchars($div);
$html .= "<tr><td>Ф.И.О. ответственного<td>".htmlspecialchars($contactName);
$html .= "<tr><td>Контактные данные получателя<td>".htmlspecialchars(implode(', ', array($contPhone, $contEmail)));
$html .= "</table>";
$html .= "<h3>3. Постановка задачи на оказание услуги</h3>";
$html .= "<table><tbody>";
$html .= "<tr><td>Основание вызова<td>".htmlspecialchars('Договор '.$contractNumber);
$html .= "<tr><td>Тип наряда<td>".htmlspecialchars($srvName);
$html .= "<tr><td>Индивидуальный сервисный номер<td>".htmlspecialchars($servNum);
$problem = preg_replace('/(\r?\n)+/', "\n", $problem);
$html .= "<tr><td>Задача на оказание услуги<td>".nl2br(htmlspecialchars($problem));
$html .= "<tr><td>Приоритет услуги<td>".htmlspecialchars($slaLevels[$slaLevel]);
$date = '';
if (preg_match('/(\d\d\d\d)-(\d\d)-(\d\d)/', $createdAt, $match))
	$date = $match[3].'.'.$match[2].'.'.$match[1];
$html .= "<tr><td>Дата начала работ<td>".htmlspecialchars($date);
$date = '';
if (preg_match('/(\d\d\d\d)-(\d\d)-(\d\d)/', $repairedAt, $match))
	$date = $match[3].'.'.$match[2].'.'.$match[1];
$html .= "<tr><td>Дата окончания работ<td>".htmlspecialchars($date);
$html .= "</table>";
$html .= "<h3>4. Результаты оказания услуги</h3>";
$html .= "<h4>Перечень оказанных услуг, заключение Исполнителя:</h4>";
$solution = array();
if ($solProblem != '')
	$solution[] = nl2br(htmlspecialchars($solProblem)); 
if ($sol != '')
	$solution[] = nl2br(htmlspecialchars($sol)); 
if ($solRecomend != '')
	$solution[] = nl2br(htmlspecialchars($solRecomend));
$html .= "<table><tbody><tr><td class='frame'>".implode('<br>', $solution)."</table>";
$html .= "<h4>Перечень произведённых замен оборудования и комплектующих:</h4>";
$html .= "<table><tbody><tr><td class='frame'></table>";
$html .= "<h4>Результаты указания услуги и замечания со стороны Получателя услуги:</h4>";
$html .= "<table><tbody><tr><td class='frame'></table>";
$html .= "<h3>5. Подписи ответственных лиц Исполнителя и Получателя услуги</h3>";
$html .= "<table class='sign'>";
$html .= "<tr><td><br><span class='bold'>Представитель Исполнителя</span><br><br>____________________ / ".htmlspecialchars($userSName)."<br><span class='small'>(подпись, Ф.И.О.)</span><br><br>МП";
$html .= "<td><br><span class='bold'>Представитель Получателя услуги</span><br><br>____________________ / ____________________<br><span class='small'>(подпись, Ф.И.О.)</span><br><br>МП";
$html .= "</table>";

$html .= "</body></html>";

//print $html;

require_once '../mPDF/vendor/autoload.php';
$mpdf = new mPDF('s','A4',8,'freesans',20,10,10,10);
$mpdf->WriteHTML($html);
$mpdf->Output("СЕРВИСНЫЙ_ЛИСТ_№_{$paramValues['id']}-01.pdf", 'I');


/*$fileName = 'ServiceList'.$requestNum.'.rtf';
$fileSize = strlen($template);
header("Content-Type: application/rtf");
header("Content-Disposition: attachment; filename={$fileName}");
header("Content-Length: {$fileSize}");
ob_clean();
flush();
echo $template; */

?>