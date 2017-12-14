<?php
/**
 * Возвращает список доступных заявок с учётом фильтра
 * 
 * PHP version 5
 * 
 * @category Common
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
list($firstJoin, $states, $join, $where, $reqVars) = buildRightsFilter($userData, 'requestsList');

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

// Получаем плановые заявки
$requests = array();
$services = array();
$persons = array();
if ('all' == $selTypes[0] || in_array('planned', $selTypes)) {
    if ('partner' != $userData['rights']) {
        try {
            $req = $db->prepare(
                "SELECT DISTINCT `rq`.`id` " 
                . "FROM `plannedRequests` AS `rq` " 
                . "JOIN `contractDivisions` AS `div` ON `div`.`guid` = `rq`.`contractDivision_guid` "
                .   "AND `div`.`isDisabled` = 0 AND `rq`.`nextDate` < DATE_ADD(NOW(), INTERVAL 1 MONTH) "
                . (count($firstJoin) > 0 ? "AND " . implode("AND ", $firstJoin) : " ")
                . $join 
                . (count($where) > 0 ? "WHERE " . implode("AND ", $where) : " ") 
                . "ORDER BY `rq`.`nextDate`"
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
            list($id) = $row;
            $requests[] = "p".$id;
        }
    }
}

if ('' != $selStates) {
    // Добавляем остальные поля фильтра
    $states = "AND `rq`.`currentState` IN ($selStates) ";
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
            $where[] = "`rq`.`partner_guid` = UNHEX(:partner_guid) ";
            $reqVars['partner_guid'] = $variables['partner'];
        }
        if (null != $variables['engineer']) {
            $where[] = "`rq`.`engineer_guid` = UNHEX(:engineer_guid) ";
            $reqVars['engineer_guid'] = $variables['engineer'];
        }
    }

    try {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`id` " 
            . "FROM `requests` AS `rq` " 
            . "JOIN `contractDivisions` AS `div` ON `div`.`guid` = `rq`.`contractDivision_guid` " 
            . (count($firstJoin) > 0 ? "AND " . implode("AND ", $firstJoin) : " ") 
            . $states 
            . $join 
            . (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "") 
            . "ORDER BY `rq`.`id`"
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
        list($id) = $row;
        $requests[] = $id;
    }
}

$hash = md5(json_encode($requests));
if ($eTag != $hash) {
    returnAnswer(
        HttpCode::HTTP_200, 
        $requests,
        array('ETag: '.$hash)
    );
} else {
    returnAnswer(HttpCode::HTTP_304, null);
}
?>