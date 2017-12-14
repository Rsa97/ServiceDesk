<?php

/**
 * Возвращает данные заявки с учётом прав
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
    returnAnswer(HttpCodes::HTTP_500, $userData);
    exit;
}

$ids = array();
$plannedIds = array();
foreach (explode(',', $variables['ids']) as $id) {
    if (preg_match('/p(\d+)/', $id, $matches)) {
        $plannedIds[] = $matches[1];
    } else {
        $ids[] = $id;
    }
}

// Готовим фильтр прав для SQL
list($firstJoin, $states, $join, $where, $reqVars) = buildRightsFilter($userData, 'requestView');

// Получаем отфильтрованный список заявок с учётом времени изменения
$idsOk = array();
$plannedIdsOk = array();
$checkRatesTimestamp = (1 == $variables['withRates'] ? (strtotime('now') >= $lastModified + 5 * 60) : false);
try {
    if (count($ids) > 0) {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`id`, `rq`.`timestamp`, "
            .               "(`rq`.`reactedAt` IS NULL OR `rq`.`fixedAt` IS NULL OR `repairedAt` IS NULL) AS `rateChanged` "
            .   "FROM `requests` AS `rq` " 
            .   "JOIN `contractDivisions` AS `div` ON `rq`.`id` IN (" . implode(',', $ids) . ") "
            .       "AND `div`.`guid` = `rq`.`contractDivision_guid` " 
            .   (count($firstJoin) > 0 ? "AND " . implode(' AND', $firstJoin) : "")
            .   $join 
            .   (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "")
            .   "ORDER BY `rq`.`id`"
        );
        $req->execute($reqVars);
        while ($row = $req->fetch(PDO::FETCH_NUM)) {
            list($id, $timestamp, $rateChanged) = $row;
            $idsOk[$id] = (($checkRatesTimestamp && (1 == $rateChanged)) ? $lastModified+1 : strtotime($timestamp));
        }
    }
    if (count($plannedIds) > 0) {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`id`, `rq`.`timestamp` "
            .   "FROM `plannedRequests` AS `rq` " 
            .   "JOIN `contractDivisions` AS `div` ON `rq`.`id` IN (" . implode(',', $plannedIds) , ") "
            .       "AND `div`.`guid` = `rq`.`contractDivision_guid` " 
            .   (count($firstJoin) > 0 ? "AND " . implode(' AND', $firstJoin) : "") 
            .   $join 
            .   (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "") 
            .   "ORDER BY `rq`.`id`"
        );
        $req->execute($reqVars);
        while ($row = $req->fetch(PDO::FETCH_NUM)) {
            list($id, $timestamp) = $row;
            $plannedIdsOk[$id] = strtotime($timestamp);
        }
    }
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

$result = array();
$neededIds = array();
$neededPlannedIds = array();
$maxTimestamp = $lastModified;
if (null !== $lastModified) {
    foreach ($ids as $id) {
        if (!isset($idsOk[$id])) {
            $result[$id] = null;
        } else if (1 == $variables['withRates'] || $idsOk[$id] > $lastModified) {
            $neededIds[] = $id;
        }
    }
    foreach ($plannedIds as $id) {
        if (!isset($plannedIdsOk[$id])) {
            $result["p{$id}"] = null;
        } else if (1 == $variables['withRates'] || $plannedIdsOk[$id] > $lastModified) {
            $neededPlannedIds[] = $id;
        }
    }
}

if (count($neededPlannedIds) > 0) {
    try {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`id`, HEX(`rq`.`contractDivision_guid`), HEX(`rq`.`service_guid`), `rq`.`slaLevel, "
            .               "`rq`.`nextDate`, `rq`.`problem`, `div`.`addProblem`, HEX(`rq`.`partner_guid`), "
            .               "`rq`.`nextDate` <= DATE_ADD(NOW(), INTERVAL `rq`.`preStart` DAY) AS `canDoNow` "
            .   "FROM `plannedRequests` AS `rq` "
            .   "LEFT JOIN `contractDivisions` AS `div` ON `rq`.`id` IN (" . implode(',', $neededPlannedIds) . ") "
            .       "AND `div`.`guid` = `rq`.`contractDivision_guid` "
            .   (count($firstJoin) > 0 ? "AND " . implode(' AND', $firstJoin) : "") 
            .   $join 
            .   (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "")
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
    while ($row = $req->fetch(PDO::CHECK_NUM)) {
        list($id, $divisionGuid, $serviceGuid, $slaLevel, $nextDate, $problem, $addProblem, $partnerGuid, $canDonNow) = $row;
        $result["p{$id}"] = array(
            'division' => ('' == $divisionGuid ? null : $divisionGuid),
            'service' => ('' == $serviceGuid ? null : $serviceGuid),
            'slaLevel' => $slaLevel,
            'nextDate' => $nextDate,
            'problem' => array(
                'main' => $problem,
                'additional' => $addProblem
            ),
            'partner' => ('' == $partnerGuid ? null : $partnerGuid),
            'canDoNow' => $canDoNow
        );
    }
}

if (count($neededIds) > 0) {
    $ratesReq = " ";
    if (1 == $variables['withRates']) {
        $ratesReq = ", IFNULL(`rq`.`reactRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`reactedAt`, NOW())))/`rq`.`toReact`), " 
                  . "IFNULL(`rq`.`fixRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`fixedAt`, IFNULL(`rq`.`repairedAt`, NOW()))))/`rq`.`toFix`), " 
                  . "IFNULL(`rq`.`repairRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`repairedAt`, NOW())))/`rq`.`toRepair`) ";
        $join .= "LEFT JOIN ("
               .    "SELECT MAX(`timestamp`) AS `onWaitAt`, `request_guid` "
               .        "FROM `requestEvents` "
               .        "WHERE `event` = 'onWait' "
               .        "GROUP BY `request_guid`"
               . ") AS `ow` ON `ow`.`request_guid` = `rq`.`guid` ";
    }
    try {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`id`, HEX(`rq`.`guid`), CAST(`rq`.`problem` AS CHAR(1024)), `rq`.`createdAt`, "
            .               "`rq`.`reactBefore`, `rq`.`reactedAt`, `rq`.`fixBefore`, IFNULL(`rq`.`fixedAt`, `rq`.`repairedAt`), "
            .               "`rq`.`repairBefore`, `rq`.`repairedAt`, `rq`.`currentState`, `rq`.`stateChangedAt`, "
            .               "HEX(`rq`.`contactPerson_guid`), HEX(`rq`.`contractDivision_guid`), `rq`.`slaLevel`, "
            .               "HEX(`rq`.`engineer_guid`), HEX(`rq`.`equipment_guid`), HEX(`rq`.`service_guid`), `rq`.`onWait`, "
            .               "`rq`.`solutionProblem`, `rq`.`solution`, `rq`.`solutionRecomendation`, HEX(`rq`.`partner_guid`) "
            .               $ratesReq
            .   "FROM `requests` AS `rq` " 
            .   "JOIN `contractDivisions` AS `div` ON `rq`.`id` IN (" . implode(',', $neededIds) . ") "
            .       "AND `div`.`guid` = `rq`.`contractDivision_guid` " 
            .   (count($firstJoin) > 0 ? "AND " . implode("AND ", $firstJoin) : "") 
            .   $join 
            .   (count($where) > 0 ? "WHERE " . implode("AND ", $where) : "")
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
        list(
            $id, $guid, $problem, $createdAt, $reactBefore, $reactedAt, $fixBefore, $fixedAt, $repairBefore, $repairedAt, 
            $state, $stateChangedAt, $contactGuid, $divisionGuid, $slaLevel, $engineerGuid, $equipmentGuid, $serviceGuid,
            $onWait, $solProblem, $sol, $solRecommend, $partnerGuid, $reactRate, $fixRate, $repairRate
        ) = array_pad($row, 26, null);
        if ('preReceived' == $state) {
            $state = 'received';
        }
        $result[$id] = array(
            'guid' => ('' == $guid ? null : $guid),
            'problem' => $problem,
            'times' => array(
                'createdAt' => $createdAt,
                'reactBefore' => $reactBefore,
                'reactedAt' => $reactedAt,
                'fixBefore' => $fixBefore,
                'fixedAt' => $fixedAt,
                'repairBefore' => $repairBefore,
                'reparedAt' => $repairedAt
            ),
            'state' => array(
                'current' => $state,
                'changedAt' => $stateChangedAt,
                'onWait' => $onWait,
                'sync1C' => ('' == $guid ? 0 : 1),
                'toPartner' => ('' == $partnerGuid ? 0 : 1)
            ),
            'division' => ('' == $divisionGuid ? null : $divisionGuid),
            'contact' => ('' == $contactGuid ? null : $contactGuid),
            'engineer' => ('' == $engineerGuid ? null : $engineerGuid), 
            'partner' => ('' == $partnerGuid ? null : $partnerGuid),
            'equipment' => ('' == $equipmentGuid ? null : $equipmentGuid),
            'service' => ('' == $serviceGuid ? null : $serviceGuid),
            'slaLevel' => $slaLevel,
            'solution' => array(
                'problem' => $solProblem,
                'solution' => $sol,
                'recommendation' => $solRecommend
            ),
            'rates' => array(
                'react' => $reactRate,
                'fix' => $fixRate,
                'repair' => $repairRate
            )
        );
    }

}
$result['now'] = strtotime('now');
$result['lastModified'] = $lastModified;
$result['lastModifiedStr'] = gmdate('D, d M Y H:i:s', $lastModified);
$result['checkRatesTimestamp'] = $checkRatesTimestamp;

if (0 == count($result) && null !== $lastModified) {
    returnAnswer(HttpCode::HTTP_304, null);
} else {
    returnAnswer(
        HttpCode::HTTP_200, 
        $result,
        array('Last-Modified: ' . gmdate('D, d M Y H:i:s').' GMT')
    );
}
exit;
?>