<?php

/**
 * Работа с базой данных
 * 
 * PHP version 5
 * 
 * @category Common
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/config/db.php";

// Подключаемся к MySQL
try {
    $db = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;",
        $dbUser,
        $dbPass,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    returnAnswer(
        HttpCode::HTTP_500,
        array(
            'result' => 'error',
            'error' => 'Внутренняя ошибка сервера',
            'orig' => "MySQL connection error " . $e->getMessage(),
            'place' => $e->getFile() . " : row " . $e->getLine()
        )
    );
    exit;
}
$db->exec("SET NAMES utf8");

/**
 * Получает пользовательские данные из базы
 * 
 * @param PDO  $db       Объект базы данных
 * @param guid $userGuid GUID пользователя
 * 
 * @return object $iserInfo Информация о пользователе или ошибка
 */
function getUserData($db, $userGuid)
{
    $rights = '';
    $partnerGuid = '';
    try {
        $req = $db->prepare(
            "SELECT `rights`, HEX(`partner_guid`) " .
                "FROM `users` WHERE `guid` = UNHEX(REPLACE(:user_guid, '-', ''))"
        );
        $req->execute(array('user_guid' => $userGuid));
        if ($row = $req->fetch(PDO::FETCH_NUM)) {
            list($rights, $partnerGuid) = $row;
        } else {
            $result = array(
                'code' => HttpCode::HTTP_404,
                'result' => 'error',
                'error' => "Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время."
            );
        }
        $result = array(
            'result' => 'ok',
            'guid' => $userGuid,
            'rights' => $rights,
            'partner_guid' => $partnerGuid
        );
    } catch (PDOException $e) {
        $result = array(
            'code' => HttpCode::HTTP_500,
            'result' => 'error',
            'error' => "Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.",
            'orig' => "MySQL error " . $e->getMessage(),
            'place' => $e->getFile() . " : row " . $e->getLine()
        );
    }
    return $result;
}
?>