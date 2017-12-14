<?php

/**
 * Авторизация пользователя
 * 
 * PHP version 5
 *
 * @param string  name      Имя пользователя
 * @param string  pass      Пароль
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

$name = isset($variables['name']) ? $variables['name'] : '';
$pass = isset($variables['pass']) ? $variables['pass'] : '';

$newHash = md5($pass . $name . "reppep");

try {
    $req = $db->prepare(
        "SELECT HEX(`guid`) AS `user_guid`, REPLACE(UUID(), '-', '') AS `token` " .
            "FROM `users` " .
            "WHERE `login` = :user AND `isDisabled` = 0 AND `passwordHash` = :newHash"
    );
    $req->execute(array('user' => $name, 'newHash' => $newHash));

    if (! ($row = $req->fetch(PDO::FETCH_ASSOC))) {
        returnAnswer(
            HttpCode::HTTP_403,
            array(
                'result' => 'error',
                'error' => 'Неверное имя пользователя или пароль'
            )
        );
        exit;
    }

    $req = $db->prepare(
        "INSERT INTO `tokens` (`user_guid`, `token`, `issued`, `expired`) " .
            "VALUES (UNHEX(:user_guid), UNHEX(:token), NOW(), NOW()+INTERVAL 3900 SECOND)"
    );
    $req->execute(array('user_guid' => $row['user_guid'], 'token' => $row['token']));

} catch (PDOException $e) {
    returnAnswer(
        HttpCode::HTTP_500,
        array(
            'result' => 'error',
            'error' => 'Внутренняя ошибка сервера. Попробуйте перезагрузить страницу через некоторое время.',
            'orig' => "MySQL error" . $e->getMessage()
        )
    );
    exit;
}

returnAnswer(HttpCode::HTTP_200, $row['token']);
?>