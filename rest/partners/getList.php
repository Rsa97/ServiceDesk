<?php
/**
 * Получить данные партнёров по guid
 * 
 * PHP version 5
 * 
 * @category Partners
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
        "SELECT HEX(`guid`), `name`, `address` "
        .   "FROM `partners` "
        .   "WHERE `guid` IN (" . $guids . ") AND `timestamp` >= :lastModified"
    );
    $req->execute(array('lastModified' => strftime('%Y-%m-%d %H:%M:%S', $lastModified)));
    $result = array();
    while ($row = $req->fetch(PDO::FETCH_NUM)) {
        list($guid, $name, $address) = $row;
        $result[$guid] = array(
            'name' => $name,
            'address' => $shortName
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