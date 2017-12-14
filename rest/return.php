<?php

/**
 * Возврат ответа
 * 
 * PHP version 5
 * 
 * @category Common
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */

/**
 * Коды возврата HTTP
 * 
 * @category Common
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */
class HttpCode
{
    const HTTP_200 = '200 OK';
    const HTTP_304 = '304 Not Modified';
    const HTTP_400 = '400 Bad Request';
    const HTTP_401 = '401 Unauthorized';
    const HTTP_403 = '403 Forbidden';
    const HTTP_404 = '404 Not Found';
    const HTTP_405 = '405 Method Not Allowed';
    const HTTP_500 = '500 Internal Server Error';
};

/**
 * Возвращает ответ
 * 
 * @param HttpCode $code    Код ответа HTTP
 * @param any      $data    Возвращаемые данные 
 * @param array    $headers Дополнительные заголовки ответа
 * @param string   $format  Формат ответа 'json' или 'xml'
 * 
 * @return none
 */
function returnAnswer($code, $data, $headers = array(), $format = 'json')
{
    header("{$_SERVER['SERVER_PROTOCOL']} {$code}");
    foreach ($headers as $hdr) {
        header($hdr);
    }
    switch ($format) {
    case 'json':
    default:
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        break;
    }
}
?>