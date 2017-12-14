<?php

/**
 * Количество заявок с учётом прав пользователя и фильтра
 * 
 * PHP version 5
 * 
 * @category Requests
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */

require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/commonData.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php";

$userData = getUserData($db, $variables['user_guid']);
if ('ok' != $userData['result']) {
    returnAnswer(HttpCode::HTTP_500, $userData);
    exit;
}

$selTypes = explode(',', $variables['type']);
$selStates = array();
foreach ($groupStatus as $state => $sqlState) {
    if ('all' == $selTypes[0] || in_array($state, $selTypes)) {
        $selStates[] = $sqlState;
    }
}
$selStates = implode(',', $selStates);
if (null !== $variables['division']) {
    $variables['contract'] = null;
}

// Готовим фильтр прав для SQL
list($firstJoin, $states, $join, $where, $reqVars) = buildRightsFilter($userData, 'list');
if ('' != $selStates) {
    $states = " AND `rq`.`currentState` IN ($selStates) ";
}

// Считаем общее количество заявок
$totalCount = array();
if ('all' == $selTypes[0]) {
    foreach ($groupStatus as $state => $sqlState) {
        $totalCount[$state] = 0;
    }
} else {
    foreach ($selTypes as $state) {
        $totalCount[$state] = 0;
    }
}
if ('' != $selStates) {
    try {
        $req = $db->prepare(
            "SELECT `rq`.`currentState`, COUNT(*) "
                . "FROM `requests` AS `rq` "
                . "JOIN `contractDivisions` AS `div` ON `div`.`guid` = `rq`.`contractDivision_guid` "
                . (count($firstJoin) > 0 ? ("AND " . implode("AND ", $firstJoin)) : " ") 
                . $states
                . $join
                . (count($where) > 0 ? ("WHERE " . implode("AND ", $where)) : " ") 
                . "GROUP BY `rq`.`currentState`"
        );
        $req->execute($reqVars);
    } catch (PDOException $e) {
        returnAnswer(
            HttpsCode::HTTP_500,
            array(
                'result' => 'error',
                'error' => "Внутренняя ошибка сервера",
                'orig' => "MySQL error " . $e->getMessage(),
                'place' => $e->getFile() . " : row " . $e->getLine()
            )
        );
        exit;
    }
    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list($state, $count) = $row;
        if ('preReceived' == $state) {
            $state = 'received';
        }
        $totalCount[$state] += intval($count);
    }
}
if ('all' == $selTypes[0] || in_array('planned', $selTypes)) {
    $totalCount['planned'] = 0;
}

// Добавляем фильтр, заданный пользователем
if (null != $variables['contract']) {
    $firstJoin[] = "`div`.`contract_guid` = UNHEX(:contract_guid) ";
    $reqVars['contract_guid'] = $variables['contract'];
}
if (null != $variables['division']) {
    $firstJoin[] = "`rq`.`contractDivision_guid` = UNHEX(:division_guid) ";
    $reqVars['division_guid'] = $variables['division'];
}
if (null != $variables['service']) {
    $firstJoin[] = "`rq`.`service_guid` = UNHEX(:service_guid) ";
    $reqVars['service_guid'] = $variables['service'];
}

$filteredCount = array();
foreach ($groupStatus as $state => $sqlState) {
    if ('all' == $selTypes[0] || in_array($tate, $selTypes)) {
        $filteredCount[$state] = 0;
    }
}

// Получаем количество отфильтрованных плановых заявок	
if ('all' == $selTypes[0] || in_array('planned', $selTypes)) {
    $filteredCount['planned'] = 0;
    if ('partner' != $userData['rights']) {
        try {
            $req = $db->prepare(
                "SELECT COUNT(*) " 
                . "FROM `plannedRequests` AS `rq` " 
                . "JOIN `contractDivisions` AS `div` ON `div`.`guid` = `rq`.`contractDivision_guid` " 
                . "AND `div`.`isDisabled` = 0 AND `rq`.`nextDate` < DATE_ADD(NOW(), INTERVAL 1 MONTH) " 
                . (count($firstJoin) > 0 ? "AND " . implode("AND ", $firstJoin) : " ") 
                . $join 
//                . "LEFT JOIN `contragents` AS `ca` ON `ca`.`guid` = `c`.`contragent_guid` " 
//                . "LEFT JOIN `services` AS `s` ON `s`.`guid` = `rq`.`service_guid` " 
                . (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "")
            );
            $req->execute($reqVars);
        } catch (PDOException $e) {
            returnAnswer(
                HttpCode::HTTP_500, 
                array(
                    'result' => 'error', 
                    'error' => "Внутренняя ошибка сервера",
                    'orig' => "MySQL error " . $e->getMessage(),
                    'place' => $e->getFile() . " : row " . $e->getLine()
                )
            );
            exit;
        }

        if ($row = $req->fetch(PDO::FETCH_NUM)) {
            list($count) = $row;
            $filteredCount['planned'] = intval($count);
        }
    }
}

// Получаем количество отфильтрованных заявок	
if ('' != $selStates) {
    $where[] = "`rq`.`createdAt` >= :from_date AND `rq`.`createdAt` <= :to_date ";
    $reqVars['from_date'] = $variables['from'];
    $reqVars['to_date'] = $variables['to'];
    if (1 == $variables['onlyMy']) {
        $firstJoin[] = "(`rq`.`contactPerson_guid` = UNHEX(:user_guid) " .
            "OR `rq`.`engineer_guid` = UNHEX(:user_guid)) ";
        $reqVars['user_guid'] = $variables['user_guid'];
    }
    if ('' != $variables['text']) {
        $firstJoin[] = "`rq`.`problem` LIKE :text ";
        $reqVars['text'] = '%' . $variables['text'] . '%';
    }
    if ('admin' == $userData['rights']) {
        if (null != $variables['partner']) {
            $firstJoin[] = "`rq`.`partner_guid` = UNHEX(:partner_guid) ";
            $reqVars['partner_guid'] = $variables['partner'];
        }
        if (null != $variables['engineer']) {
            $firstJoin[] = "`rq`.`engineer_guid` = UNHEX(:engineer_guid) ";
            $reqVars['engineer_guid'] = $variables['engineer'];
        }
    }

    try {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`currentState`, COUNT(*) " 
            . "FROM `requests` AS `rq` " 
            . "JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` " 
            . (count($firstJoin) > 0 ? "AND " . implode("AND ", $firstJoin) : " ") 
            . $states 
            . $join 
            . (count($where) > 0 ? "WHERE " . implode("AND ", $where) : " ") 
            . "GROUP BY `rq`.`currentState`"
        );
        $req->execute($reqVars);
    } catch (PDOException $e) {
        returnAnswer(
            HttpCode::HTTP_500, 
            array(
                'result' => 'error', 
                'error' => "Внутренняя ошибка сервера",
                'orig' => "MySQL error " . $e->getMessage(),
                'place' => $e->getFile() . " : row " . $e->getLine()
            )
        );
        exit;
    }

    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list($state, $count) = $row;
        if ('preReceived' == $state) {
            $state = 'received';
        }
        $filteredCount[$state] += intval($count);
    }
}

$result = array('total' => $totalCount, 'filtered' => $filteredCount);
$hash = md5(json_encode($result));
if ($eTag != $hash) {
    returnAnswer(
        HttpCode::HTTP_200, 
        $result,
        array('ETag: '.$hash)
    );
} else {
    returnAnswer(HttpCode::HTTP_304, null);
}
?>