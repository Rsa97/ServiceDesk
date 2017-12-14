<?php
/**
 * Общие функции
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
 * Представление GUID'а в стандартном виде
 * 
 * @param variant $hex GUID в бинарном или компактном строковом виде
 * 
 * @return string $guid GUID в стандартном виде, нижний регистр, через дефис
 */
function formatGuid($hex) 
{
    if (null === $hex) {
        return null;
    }
    if (!preg_match('/^[0-9a-z]{32}$/i', $hex)) {
        $hex = unpack('H*', $hex);
        $hex = $hex[1];
    }
    if (preg_match('/^[0-9a-z]{32}$/i', $hex)) {
        return preg_replace(
            '/([0-9a-z]{8})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{12})/', 
            '$1-$2-$3-$4-$5', 
            strtolower($hex)
        );
    }
    return null;
}

/**
 * Фамилия и инициалы одной строкой
 * 
 * @param string $lastName   Фамилия
 * @param string $givenName  Имя
 * @param string $middleName Отчество
 * 
 * @return string $fio Фамилия с инициалами
 */
function nameWithInitials($lastName, $givenName, $middleName) 
{
    $res = array($lastName);
    if ('' != $givenName) {
        $res[] = mb_substr($givenName, 0, 1, 'utf-8').'.';
        if ('' != $middleName) {
            $res[] = mb_substr($middleName, 0, 1, 'utf-8').'.';
        }
    }
    return implode(' ', $res);
}

/**
 * Фамилия Имя Отчество одной строкой
 * 
 * @param string $lastName   Фамилия
 * @param string $givenName  Имя
 * @param string $middleName Отчество
 * 
 * @return string $fio Фамилия Имя Отчество одной строкой
 */
function nameFull($lastName, $givenName, $middleName) 
{
    $res = array($lastName);
    if ('' != $givenName) {
        $res[] = $givenName;
        if ('' != $middleName) {
            $res[] = $middleName;
        }
    }
    return implode(' ', $res);
}

/**
 * Время в формате SOAP/XML
 * 
 * @param string $time Время в формате, понимаемом DataTime
 * 
 * @return string $soapTime Время в формате SOAP/XML
 */
function timeToSOAP($time) 
{
    return date_format(new DateTime($time), 'c');
}

/**
 * Строит фильтр по правам пользователя на доступ к объектам в БД
 * 
 * @param object $userData  Данные пользователя из функции getUserData
 * @param string $operation Планируемая операция
 * 
 * @return string $soapTime Время в формате SOAP/XML
 */
function buildRightsFilter($userData, $operation)
{
    $firstJoin = array();
    $states = ' ';
    $join = "";
    $where = array();
    $reqVars = array();
    if ('client' == $userData['rights']) {
        $join .= "JOIN `userContracts` AS `uc` ON `uc`.`contract_guid` = `div`.`contract_guid` ".
                 "JOIN `userContractDivisions` AS `ucd` ON `ucd`.`contractDivision_guid` = `div`.`guid` ";
        $where[] = "(`ucd`.`user_guid` = UNHEX(REPLACE(:user_guid, '-', '')) ".
                    "OR `uc`.`user_guid` = UNHEX(REPLACE(:user_guid, '-', '')) ";
        $reqVars['user_guid'] = $userData['guid']; 
    } else if ('partner' == $userData['rights']) {
        $reqVars['partner_guid'] = $userData['partner_guid'];
    }
    // TODO:: Надо ли скрывать заявки по завершённым договорам?
    /*	if ('admin' != $userData['rights']) {
        $where[] = "(`c`.`contractStart` <= NOW() AND `c`.`contractEnd` >= NOW()) ";
    } */
    switch($operation) {
    case 'requestsList':
    case 'requestsView':
        if ('partner' == $userData['rights']) {
            $where[] = "`rq`.`partner_guid` = UNHEX(:partner_guid) ";
        }
        return array($firstJoin, $join, $states, $where, $reqVars);
        break;
    case 'userInfo':
        if ('partner' == $userData['rights']) {
            $join .= "JOIN `partnerDivisions` AS `a` ON `a`.`partner_guid` = UNHEX(REPLACE(:partner_guid, '-', '')) ".
                        "AND `a`.`contractDivision_guid` = `div`.`guid` ";
        }
        return array($firstJoin, $join, $states, $where, $reqVars);
        break;
    }
}

/**
 * Разрешено ли пользователю изменять услугу в заявке
 * 
 * @param object $userData Данные пользователя из функции getUserData
 * @param object $request  Информация о заявке
 * 
 * @return string $soapTime Время в формате SOAP/XML
 */
function canChangeService($userData, $request) 
{
    return (in_array($userData['rights'], array('admin', 'engineer')) && 'received' == $request['state']);
}

/**
 * Разрешено ли пользователю изменять контактное лицо в заявке
 * 
 * @param object $userData Данные пользователя из функции getUserData
 * @param object $request  Информация о заявке
 * 
 * @return string $soapTime Время в формате SOAP/XML
 */
function canChangeContact($userData, $request) 
{
    return ('admin' == $userData['rights'] && 'received' == $request['state']);
}
?>