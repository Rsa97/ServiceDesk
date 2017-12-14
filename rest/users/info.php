<?php

/**
 * Возвращает информацию о пользователе
 * 
 * PHP version 5
 *
 * @param string  info      Тип информации all,info,filter,allowedOps,allowedFilters
 * 
 * @return string token     Токен пользователя
 * 
 * @category Common
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */

require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php";

$allowedOperationsChanged = strtotime('2017-12-11 00:00:00');
$allowedOperations = array(
    'received' => array(
        'admin' => array('New', 'Accept', 'Cancel', 'Wait'),
        'client' => array('New', 'Cancel'),
        'operator' => array('New', 'Cancel'),
        'engineer' => array('New', 'Accept', 'Cancel', 'Wait'),
        'partner' => array('Accept', 'Wait')
    ),
    'accepted' => array(
        'admin' => array('Fixed', 'Repaired', 'Wait'),
        'client' => array('Cancel'),
        'operator' => array('Cancel'),
        'engineer' => array('Fixed', 'Repaired', 'Wait'),
        'partner' => array('Fixed', 'Repaired', 'Wait')
    ),
    'fixed' => array(
        'admin' => array('Repaired', 'Wait'),
        'client' => array(),
        'operator' => array(),
        'engineer' => array('Repaired', 'Wait'),
        'partner' => array('Repaired', 'Wait')
    ),
    'repaired' => array(
        'admin' => array('UnClose', 'Close'),
        'client' => array('UnClose', 'Close'),
        'operator' => array('UnClose', 'Close'),
        'engineer' => array(),
        'partner' => array()
    ),
    'closed' => array(
        'admin' => array(),
        'client' => array(),
        'operator' => array(),
        'engineer' => array(),
        'partner' => array()
    ),
    'canceled' => array(
        'admin' => array('UnCancel'),
        'client' => array(),
        'operator' => array(),
        'engineer' => array('UnCancel'),
        'partner' => array()
    ),
    'planned' => array(
        'admin' => array('DoNow', 'AddProblem'),
        'client' => array(),
        'operator' => array('AddProblem'),
        'engineer' => array('DoNow', 'AddProblem'),
        'partner' => array('DoNow', 'AddProblem')
    )
);

$result = array();

try {
    // Получаем данные пользователя
    $req = $db->prepare(
        "SELECT `lastName`, `firstName`, `middleName`, `rights`, `email`, ".
                "`phone`, `cellphone`, `jid`, `address`, HEX(`partner_guid`), ".
                "`timestamp` ".
            "FROM `users` " .
            "WHERE `guid` = UNHEX(:user_guid)"
    );
    $req->execute(array('user_guid' => $variables['user_guid']));
    if ($row = $req->fetch(PDO::FETCH_NUM)) {
        list(
            $lastName, $firstName, $middleName, $rights, $email, $phone, $cellphone, $jid,
            $address, $partner_guid, $timestamp
        ) = $row;
        $timestamp = strtotime($timestamp);
        switch($variables['info']) {
        case 'info':
            if ($timestamp > $lastModified) {
                $info = array(
                    'fullName' => nameFull($lastName, $firstName, $middleName),
                    'shortName' => nameWithInitials($lastName, $firstName, $middleName),
                    'rights' => $rights,
                    'partner' => $patnerGuid,
                    'email' => $email,
                    'phone' => $phone,
                    'cellphone' => $cellphone,
                    'jid' => $jid,
                    'address' => $address,
                    'timestamp' => $timestamp,
                    'ifModified' => $lastModified,
                    'header' => strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])
                );
                returnAnswer(
                    HttpCode::HTTP_200, 
                    $info, 
                    array('Last-Modified: '.gmdate('D, d M Y H:i:s', $timestamp).' GMT')
                );
            } else {
                returnAnswer(HttpCode::HTTP_304, null);
            }
            exit;
        case 'allowedOps':
            if ($allowedOperationsChanged > $timestamp) {
                $timestamp = $allowedOperationsChanged;
            }
            if ($timestamp > $lastModified) {
                // Формируем список доступных операций
                $allowed = array();
                foreach ($allowedOperations as $forState => $states) {
                    $allowed[$forState] = $states[$rights];
                }
                returnAnswer(
                    HttpCode::HTTP_200, 
                    $allowed,
                    array('Last-Modified: '.gmdate('D, d M Y H:i:s', $timestamp).' GMT')
                );
            } else {
                returnAnswer(HttpCode::HTTP_304, null);
            }
            exit;
        }
    } else {
        returnAnswer(
            HttpCode::HTTP_404,
            array(
                'result' => 'error',
                'value' => 'Нет такого пользователя'
            )
        );
        exit;
    }

    // Получаем доступные пользователю фильтры
    // Готовим фильтр SQL
    list($firstJoin, $states, $join, $where, $reqVars) = buildRightsFilter(
        array('result' => 'ok', 'rights' => $rights, 'partner_guid' => $partner_guid),
        'userInfo'
    );

    // Строим список доступных контрагентов и подразделений
    $req = $db->prepare(
        "SELECT DISTINCT `ca`.`name`, HEX(`c`.`guid`), `c`.`number`, " .
            "HEX(`div`.`guid`), `div`.`name` " .
            "FROM `contragents` AS `ca` " .
            "JOIN `contracts` AS `c` ON `ca`.`guid` = `c`.`contragent_guid` " .
            "JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` " .
            $join . (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "") .
            "ORDER BY `ca`.`name`, `c`.`number`, `div`.`name`"
    );
    $req->execute($reqVars);

    $prevContragent = "";
    $prevContract = "";
    $divFilter = array();
    $iContragent = -1;
    $iContract = -1;
    $iDivision = 0;
    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list($contragentName, $contractGuid, $contractNumber, $divisionGuid, $divisionName) = $row;
        if ($contragentName != $prevContragent) {
            $divFilter[++$iContragent] = array('name' => $contragentName, 'contracts' => array());
            $iContract = -1;
            $prevContragent = $contragentName;
        }
        if ($contractGuid != $prevContract) {
            $divFilter[$iContragent]['contracts'][++$iContract] = array(
                'guid' => $contractGuid,
                'name' => $contractNumber,
                'divisions' => array()
            );
            $iDivision = 0;
            $prevContract = $contractGuid;
        }
        $divFilter[$iContragent]['contracts'][$iContract]['divisions'][$iDivision++] = array(
            'guid' => $divisionGuid,
            'name' => $divisionName
        );
    }

    // Строим список доступных услуг
    $req = $db->prepare(
        "SELECT DISTINCT HEX(`s`.`guid`), `s`.`name` " .
            "FROM `contracts` AS `c` " .
            "JOIN `contractDivisions` AS `div` ON `div`.`contract_guid` = `c`.`guid` " .
            $join .
            "JOIN `contractServices` AS `cs` ON `cs`.`contract_guid` = `c`.`guid` " .
            "JOIN `services` AS `s` ON `s`.`guid` = `cs`.`service_guid` " . (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "") .
            "ORDER BY `s`.`name`"
    );
    $req->execute($reqVars);

    $srvFilter = array();
    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list($serviceGuid, $serviceName) = $row;
        $srvFilter[] = array(
            'guid' => $serviceGuid,
            'name' => $serviceName
        );
    }

    // Строим список партнёров
    $partnerFilter = null;
    if ('admin' == $rights) {
        $req = $db->prepare(
            "SELECT HEX(`guid`), `name` " .
                "FROM `partners` " .
                "ORDER BY `name`"
        );
        $req->execute();

        $partnerFilter = array();
        while ($row = $req->fetch(PDO::FETCH_NUM)) {
            list($partnerGuid, $partnerName) = $row;
            $partnerFilter[] = array(
                'guid' => $partnerGuid,
                'name' => $partnerName
            );
        }
    } 

    // Строим список инженеров
    $engineerFilter = null;
    if ('admin' == $rights) {
        $req = $db->prepare(
            "SELECT HEX(`guid`), `lastName`, `firstName`, `middleName` " .
                "FROM `users` " .
                "WHERE `rights` IN ('admin', 'engineer', 'partner') " .
                "AND `login` != 'robot' " .
                "ORDER BY `lastName`, `firstName`, `middleName`"
        );
        $req->execute();

        $engineerFilter = array();
        while ($row = $req->fetch(PDO::FETCH_NUM)) {
            list($engineerGuid, $engineerLName, $engineerGName, $engineerMName) = $row;
            $engineerFilter[] = array(
                'guid' => $partnerGuid,
                'name' => nameWithInitials($engineerLName, $engineerGName, $engineerMName)
            );
        }
    }

    $allowedFilters = array(
        'divisions' => $divFilter,
        'services' => $srvFilter,
        'partners' => $partnerFilter,
        'engineers' => $engineerFilter
    );

    $hash = md5(json_encode($allowedFilters));
    if ($eTag != $hash) {
        returnAnswer(
            HttpCode::HTTP_200, 
            $allowedFilters,
            array('ETag: '.$hash)
        );
    } else {
        returnAnswer(HttpCode::HTTP_304, null);
    }

} catch (PDOException $e) {
    returnAnswer(
        HttpCode::HTTP_500,
        array(
            'result' => 'error',
            'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.',
            'orig' => 'MySQL error' . $e->getMessage()
        )
    );
    exit;
}
?>	