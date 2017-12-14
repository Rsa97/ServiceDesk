<?php
/**
 * Получить данные оборудования по guid
 * 
 * PHP version 5
 * 
 * @category Equipments
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php";
require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/functions.php";

$guids = "UNHEX('" . implode("'), UNHEX('", explode(',', $variables['guids'])) . "')";

try {
    $req = $db->prepare(
        "SELECT HEX(`e`.`guid`), `e`.`serviceNumber`, `e`.`serialNumber`, `e`.`onService`, `m`.`name`, "
        .      "`mf`.`name`, `st`.`name`, `t`.`name`, HEX(`e`.`workplace_guid`), `e`.`rem` "
        .   "FROM `equipment` AS `e` "
        .   "JOIN `equipmentModels` AS `m` ON `m`.`guid` = `e`.`equipmentModel_guid` "
        .   "JOIN `equipmentManufacturers AS `mf` ON `mf`.`guid` = `m`.`equipmentManufacturer_guid` "
        .   "JOIN `equipmentSubTypes` AS `st` ON `st`.`guid` = `m`.`equipmentSubType_guid` "
        .   "JOIN `equipmentTypes` AS `t` ON `t`.`guid` = `st`.`equipmentType_guid` "
        .   "WHERE `e`.`guid` IN (" . $guids . ") AND `e`.`timestamp` >= :lastModified"
    );
    $req->execute(array('lastModified' => strftime('%Y-%m-%d %H:%M:%S', $lastModified)));
    $result = array();
    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list(
            $guid, $serviceNumber, $serialNumber, $onService, $model, $manufacturer, $subType,
            $type, $workplaceGuid, $comment
        ) = $row;
        $result[$guid] = array(
            'serviceNumber' => $serviceNumber,
            'serialNumber' => $serialNumber,
            'onService' => $onService,
            'model' => $model,
            'manufacturer' => $manufacturer,
            'subType' => $subType,
            'type' => $type,
            'workplace' => ('' == $workplaceGuid ? null : $workplaceGuid),
            'comment' => $comment
        );
    }
} catch (PDOException $e) {
    returnAnswer(
        HttpCode::HTTP_500,
        array(
            'result' => 'error',
            'error' => 'Внутренняя ошибка сервера', 
            'orig' => "MySQL error " . $e->getMessage(),
            'place' => $e->getFile() . " : row " . $e->getLine()
        )
    );
    exit;
}

if (0 == count($result)) {
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