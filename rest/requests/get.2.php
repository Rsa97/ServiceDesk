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
try {
    $req = $db->prepare(
        "SELECT DISTINCT `rq`.`id`, `rq`.`timestamp` "
        .   "FROM `requests` AS `rq` " 
        .   "JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` " 
        .   (count($firstJoin) > 0 ? "AND " . implode(' AND', $firstJoin) : "") 
        .   $join 
        .   (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "") 
        .   "ORDER BY `rq`.`id`"
    );
    $req->execute($reqVars);
    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list($id, $timestamp) = $row;
        $idsOk[$id] = strtotime($timestamp);
    }
    $req = $db->prepare(
        "SELECT DISTINCT `rq`.`id`, `rq`.`timestamp` "
        .   "FROM `plannedRequests` AS `rq` " 
        .   "JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` " 
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
} catch (PDOException $e) {
    returnAnswer(
        HttpCodes::HTTP_500, 
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
        } else if ($idsOk[$id] > $lastModified) {
            $neededIds[] = $id;
            if ($idsOk[$id] > $maxTimestamp) {
                $maxTimestamp = $idsOk[$id];
            }
        }
    }
    foreach ($plannedIds as $id) {
        if (!isset($plannedIdsOk[$id])) {
            $result["p{$id}"] = null;
        } else if ($plannedIdsOk[$id] > $lastModified) {
            $neededPlannedIds[] = $id;
            if ($plannedIdsOk[$id] > $maxTimestamp) {
                $maxTimestamp = $plannedIdsOk[$id];
            }
        }
    }
}

if (count($neededIds) > 0) {
    $ratesReq = '';
    if (1 == $variables['withRates']) {
        $ratesReq = ", IFNULL(`rq`.`reactRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`reactedAt`, NOW())))/`rq`.`toReact`), " 
                  . "IFNULL(`rq`.`fixRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`fixedAt`, IFNULL(`rq`.`repairedAt`, NOW()))))/`rq`.`toFix`), " 
                  . "IFNULL(`rq`.`repairRate`, calcTime_v3(`rq`.`id`, IF(1 = `rq`.`onWait`, IFNULL(`ow`.`onWaitAt`, NOW()), IFNULL(`rq`.`repairedAt`, NOW())))/`rq`.`toRepair`) ";
    }
    try {
        $req = $db->prepare(
            "SELECT DISTINCT `rq`.`id`, `rq`.`guid`, CAST(`rq`.`problem` AS CHAR(1024)), `rq`.`createdAt`, "
            .               "`rq`.`reactBefore`, `rq`.`reactedAt`, `rq`.`fixBefore`, IFNULL(`rq`.`fixedAt`, `rq`.`repairedAt`), "
            .               "`rq`.`repairBefore`, `rq`.`repairedAt`, `rq`.`currentState`, `rq`.`stateChangedAt`, "
            .               "HEX(`rq`.`contactPerson_guid`), HEX(`rq`.`contractDivision_guid`), `rq`.`slaLevel`, "
            .               "HEX(`rq`.`engineer_guid), HEX(`rq`.`equipment_guid`), HEX(`rq`.`service_guid`), `rq`.`onWait`, "
            .               "`rq`.`solutionProblem`, `rq`.`solution`, `rq`.`solutionRecomendation`, HEX(`rq`.`partner_guid`), "
            .               $ratesReq
            .   "FROM `requests` AS `rq` " 
            .   "JOIN `contractDivisions` AS `div` ON `rq`.`contractDivision_guid` = `div`.`guid` " 
            .   (count($firstJoin) > 0 ? "AND " . implode(' AND', $firstJoin) : "") 
            .   $join 
            .   (count($where) > 0 ? "WHERE " . implode(' AND', $where) : "")
        );
        $req->execute($reqVars);
    } catch (PDOException $e) {
        returnAnswer(
            HttpCodes::HTTP_500, 
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
            $onWait, $solProblem, $sol, $solRecomend, $partnerGuid, $reactRate, $fixRate, $repairRate
        ) = array_pad($row, 26, null);
        if ('preReceived' == $state) {
            $state = 'received';
        }
        if ('canceled' == $state) {
            $reactColor = '#808080';
            $fixColor = '#808080';
            $repairColor = '#808080';
            $reactComment = 'Заявка отменена';
            $fixComment = '';
            $repairComment = '';
            $sliderColor = '#808080';
            $reactPercent = 0;
            $fixPercent = 0;
            $repairPercent = 0;
        } else {
            $timeToReact *= 60;
            $timeToFix *= 60;
            $timeToRepair *= 60;
            if ($reactPercent > 1) {
                $reactPercent = 1;
            }
            if ($fixPercent > 1) {
                $fixPercent = 1;
            }
            if ($repairPercent > 1) {
                $repairPercent = 1;
            }
            $reactComment = ($reactedAt == '' ? (1 == $onWait ? ("Приостановлено " . date_format(date_create($onWaitAt), 'd.m.Y H:i')) : ("Принять до " . date_format(date_create($reactBefore), 'd.m.Y H:i'))) : ("Принято " . date_format(date_create($reactedAt), 'd.m.Y H:i')));
            $fixComment = ($fixedAt == '' ? (1 == $onWait ? ('' == $reactedAt ? '' : ("Приостановлено " . date_format(date_create($onWaitAt), 'd.m.Y H:i'))) : ("Восстановить до " . date_format(date_create($fixBefore), 'd.m.Y H:i'))) : ("Восстановлено " . date_format(date_create($fixedAt), 'd.m.Y H:i')));
            $repairComment = ($repairedAt == '' ? (1 == $onWait ? ('' == $fixedAt ? '' : ("Приостановлено " . date_format(date_create($onWaitAt), 'd.m.Y H:i'))) : ("Завершить до " . date_format(date_create($repairBefore), 'd.m.Y H:i'))) : ("Завершено " . date_format(date_create($repairedAt), 'd.m.Y H:i')));
            $reactColor = ($reactedAt == '' ? ('rgb(' . floor(255 * $reactPercent) . ',' . floor(255 * (1 - $reactPercent)) . ',0)') : '#808080');
            $fixColor = ($fixedAt == '' ? ('rgb(' . floor(255 * $fixPercent) . ',' . floor(255 * (1 - $fixPercent)) . ',0)') : '#808080');
            $repairColor = ($state == 'closed' ? '#808080' : ($repairedAt == '' ? ('rgb(' . floor(255 * $repairPercent) . ',' . floor(255 * (1 - $repairPercent)) . ',0)') : 'yellow'));
            $sliderColor = ($reactedAt == '' ? $reactColor : ($fixedAt == '' ? $fixColor : $repairColor));
            $reactPercent = floor($reactPercent * 100);
            $fixPercent = floor($fixPercent * 100);
            $repairPercent = floor($repairPercent * 100);
        }
    $requests[] = array(
        'id' => $id,
        'status' => array(
            'type' => $state, 'onWait' => $onWait, 'sync1C' => (null == $requestGuid ? 0 : 1),
            'toPartner' => ('' == $partnerName ? 0 : 1)
        ),
        'slaLevel' => $slaLevel,
        'service' => $srvGuid,
        'problem' => $problem,
        'receiveTime' => date_format(date_create($createdAt), 'd.m.Y H:i'),
        'repairBefore' => date_format(date_create($repairBefore), 'd.m.Y H:i'),
        'contract' => $contractNumber,
        'division' => $div,
        'contragent' => $contragent,
        'contact' => ('' == $contGuid ? null : $contGuid),
        'engineer' => ('' == $engGuid ? null : $engGuid),
        'time' => array(
            'toReact' => array('percent' => $reactPercent, 'text' => $reactComment, 'color' => $reactColor),
            'toFix' => array('percent' => $fixPercent, 'text' => $fixComment, 'color' => $fixColor),
            'toRepair' => array('percent' => $repairPercent, 'text' => $repairComment, 'color' => $repairColor)
        ),
        'partner' => $partnerName,
        'slider' => array('color' => $sliderColor)
    );
    if (!isset($services[$srvGuid])) {
        $services[$srvGuid] = array('name' => $srvName, 'shortname' => $srvSName, 'only_auto' => $srvAutoOnly);
    }
    if (null != $contGuid && !isset($persons[$contGuid])) {
        $persons[$contGuid] = array(
            'name' => nameFull($contLN, $contGN, $contMN), 'email' => $contEmail, 'phone' => $contPhone,
            'shortname' => nameWithInitials($contLN, $contGN, $contMN)
        );
    }
    if (null != $engGuid && !isset($persons[$engGuid])) {
        $persons[$engGuid] = array(
            'name' => nameFull($engLN, $engGN, $engMN), 'email' => $engEmail, 'phone' => $engPhone,
            'shortname' => nameWithInitials($engLN, $engGN, $engMN)
        );
    }
}
}

returnAnswer(HTTP_200, array(
    'result' => 'ok',
    'value' => array(
        'total' => $totalCount, 'requests' => $requests, 'services' => $services,
        'users' => $persons
    ),
    'expireTime' => 120
));

?>