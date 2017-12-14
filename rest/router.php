<?php

/**
 * Роутинг
 * 
 * PHP version 5
 * 
 * @category Common
 * @package  No
 * @author   RSA <rsa@sodrk.ru>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     http://www.sodrk.ru/
 */
require_once "{$_SERVER['DOCUMENT_ROOT']}/rest/return.php";

/**
 * Удалить дефисы из guid'а
 * 
 * @param string $guid GUID
 * 
 * @return string $guid GUID без дефисов
 */
function prepareGuid($guid) 
{
    return str_replace('-', '', $guid);
}

$lastModified = strtotime((isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] : '2000-01-01 00:00:00'));
$eTag = (isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : null);

$routes = array(
    'GET' => array(
        'users/me(?:/(info|filter|allowedOps|allowedFilters))?' => array(
            'file' => '/rest/users/info.php',
            'variables' => array('info'),
            'defaults' => array(
                'info' => 'info'
            )
        ),
        'users/list/([0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}(?:,(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}))*)' => array(
            'file' => '/rest/users/getList.php',
            'variables' => array('guids'),
            'require' => array('guids')
        ),
        'requests/counts(?:/(all|(?:(?:accepted|received|fixed|repaired|closed|canceled|planned)(?:,(?:accepted|received|fixed|repaired|closed|canceled|planned))*)))?' => array(
            'file' => '/rest/requests/counts.php',
            'variables' => array('type'),
            'filters' => array(
                'contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'partner' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'engineer'  => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'from' => '/^\d{4}-\d\d-\d\d$/',
                'to' => '/^\d{4}-\d\d-\d\d$/',
                'onlyMy' => '/^0|1$/'
            ),
            'defaults' => array(
                'type' => 'all',
                'contract' => null,
                'division' => null,
                'service' => null,
                'partner' => null,
                'engineer' => null,
                'from' => date("Y-m-d 00:00:00", strtotime('-3 months')),
                'to' => date("Y-m-d 00:00:00", strtotime('now')),
                'onlyMy' => 0,
                'text' => ''
            ),
            'prepare' => array(
                'contract' => 'prepareGuid',
                'division' => 'prepareGuid',
                'service' => 'prepareGuid',
                'partner' => 'prepareGuid',
                'engineer' => 'prepareGuid',
                'text' => 'trim'
            )
        ),
        'requests(?:/(all|(?:(?:accepted|received|fixed|repaired|closed|canceled|planned)(?:,(?:accepted|received|fixed|repaired|closed|canceled|planned))*)))?' => array(
            'file' => '/rest/requests/list.php',
            'variables' => array('type'),
            'filters' => array(
                'contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'partner' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'engineer'  => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
                'from' => '/^\d{4}-\d\d-\d\d$/',
                'to' => '/^\d{4}-\d\d-\d\d$/',
                'onlyMy' => '/^0|1$/'
            ),
            'defaults' => array(
                'type' => 'all',
                'contract' => null,
                'division' => null,
                'service' => null,
                'partner' => null,
                'engineer' => null,
                'from' => date("Y-m-d 00:00:00", strtotime('-3 months')),
                'to' => date("Y-m-d 00:00:00", strtotime('now')),
                'onlyMy' => 0,
                'text' => ''
            ),
            'prepare' => array(
                'contract' => 'prepareGuid',
                'division' => 'prepareGuid',
                'service' => 'prepareGuid',
                'partner' => 'prepareGuid',
                'engineer' => 'prepareGuid',
                'text' => 'trim'
            )
        ),
        'requests/list/(p?\d+(?:,p?\d+)*)' => array(
            'file' => '/rest/requests/getList.php',
            'variables' => array('ids'),
            'require' => array('ids'),
            'filters' => array(
                'withRates' => '/^(?:[01])$/'
            ),
            'defaults' => array(
                'withRates' => 0
            )
        ),
        'divisions/list/([0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}(?:,(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}))*)' => array(
            'file' => '/rest/divisions/getList.php',
            'variables' => array('guids'),
            'require' => array('guids')
        ),
        'services/list/([0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}(?:,(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}))*)' => array(
            'file' => '/rest/services/getList.php',
            'variables' => array('guids'),
            'require' => array('guids')
        ),
        'equipments/list/([0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}(?:,(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}))*)' => array(
            'file' => '/rest/equipments/getList.php',
            'variables' => array('guids'),
            'require' => array('guids')
        ),
        'contracts/list/([0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}(?:,(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}))*)' => array(
            'file' => '/rest/contracts/getList.php',
            'variables' => array('guids'),
            'require' => array('guids')
        ),
        'contragents/list/([0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}(?:,(?:[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}))*)' => array(
            'file' => '/rest/contragents/getList.php',
            'variables' => array('guids'),
            'require' => array('guids')
        ),
            /*
            'requestCounts' => 	array('file' => ,
								'filters' => array('type' => '/^received|accepted|toClose|planned|closed|canceled|all$/',
												   'contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
												   'division' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
												   'service' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i',
												   'from' => '/^\d{4}-\d\d-\d\d$/',
												   'to' => '/^\d{4}-\d\d-\d\d$/',
												   'onlyMy' => '/^0|1$/'
							)),
			'request' => 	array('file' => '/rest/request/get.php',
								'require' => array('id'),
								'filters' => array('id', '/^\d+$/')),
			'contragents' => array('file' => '/rest/contragent/list.php'),
			'contragent' => array('file' => '/rest/contragent/get.php',
								'require' => array('guid'),
								'filters' => array('guid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'contracts' => 	array('file' => '/rest/contract/list.php',
								'require' => array('contragent'),
								'filters' => array('contragent' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'contract' => 	array('file' => '/rest/contract/get.php',
								'require' => array('guid'),
								'filters' => array('guid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'divisions' => 	array('file' => '/rest/division/list.php',
								'require' => array('contract'),
								'filters' => array('contract' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							),
			'division' => 	array('file' => '/rest/division/get.php',
								'require' => array('guid'),
								'filters' => array('guid' => '/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}|[0-9a-f]{32}/i')
							) */
    ),
    'POST' => array(
        'auth' => array(
            'file' => '/rest/users/auth.php',
            'require' => array('name', 'pass'),
            'filters' => array('name' => '/^[a-z][-_0-9a-z]*$/i'),
            'withoutToken' => true
        ) 
    ),
    'PUT' => array(
    ),
    'PATCH' => array(
        'users/me/pass' => array(
            'file' => '/rest/users/newPass.php',
            'require' => array('pass')
        )
    ),
    'DELETE' => array(
    )
);

if (!isset($routes[$_SERVER['REQUEST_METHOD']])) {
    returnAnswer(
        HttpCode::HTTP_405, 
        array(
            'result' => 'error', 
            'error' => "Недопустимый метод"
        )
    );
    exit;
}

// Ищем метод
$variables = array();
$found = false;
foreach ($routes[$_SERVER['REQUEST_METHOD']] as $url => $def) {
    if (preg_match('#^' . $url . '$#i', $_REQUEST['routeRequest'], $vars)) {
        $found = true;
        break;
    }
}
if (!$found) {
    returnAnswer(
        HttpCode::HTTP_404, 
        array(
            'result' => 'error', 
            'error' => "Не найден метод"
        )
    );
    exit;
}
    
// Проверяем токен
if (!isset($def['withoutToken']) || true !== $def['withoutToken']) {
    $apiKey = (isset($_SERVER['HTTP_APIKEY']) ? $_SERVER['HTTP_APIKEY'] : null);
    if (null === $apiKey) {
        returnAnswer(
            HttpCode::HTTP_400, 
            array(
                'result' => 'error', 
                'error' => "Не указан токен"
            )
        );
        exit;
    }
    include_once "{$_SERVER['DOCUMENT_ROOT']}/rest/common/db.php";
    try {
        $req = $db->prepare(
            "SELECT HEX(`user_guid`) AS `user_guid` " .
                "FROM `tokens` " .
                "WHERE `token` = UNHEX(:token) AND `expired` > NOW()"
        );
        $req->execute(array('token' => $apiKey));
        if (! ($row = $req->fetch(PDO::FETCH_ASSOC))) {
            returnAnswer(
                HttpCode::HTTP_401, 
                array(
                    'result' => 'error', 
                    'error' => "Токен просрочен"
                )
            );
            exit;
        }
        $variables['user_guid'] = $row['user_guid'];
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
}

// Получаем все параметры
if (isset($def['variables'])) {
    foreach ($def['variables'] as $i => $name) {
        $variables[$name] = (isset($vars[$i + 1]) ? $vars[$i + 1] : null);
    }
}
foreach ($_REQUEST as $name => $value) {
    if ('routeRequest' != $name) {
        $variables[$name] = $value;
    }
}
if ('PUT' == $_SERVER['REQUEST_METHOD'] || 'PATCH' == $_SERVER['REQUEST_METHOD']) {
    $req = file_get_contents('php://input', 'r');
    foreach (explode('&', $req) as $par) {
        list($name, $value) = explode('=', $par);
        $variables[$name] = urldecode($value);
    }
}

    // Проверяем наличие обязательных параметров
if (isset($def['require'])) {
    foreach ($def['require'] as $required) {
        if (!isset($variables[$required])) {
            returnAnswer(
                HttpCode::HTTP_400, 
                array(
                    'result' => 'error', 
                    'error' => "Не указан параметр '{$required}'"
                )
            );
            exit;
        }
    }
}
    
// Проверяем корректность параметров
if (isset($def['filters'])) {
    foreach ($def['filters'] as $name => $filter) {
        if (isset($variables[$name]) && !preg_match($filter, $variables[$name])) {
            returnAnswer(
                HttpCode::HTTP_400, 
                array(
                    'result' => 'error', 
                    'error' => "Недопустимое значение параметра '{$name}' : '{$variables[$name]}'"
                )
            );
            exit;
        }
    }
}

// Заполняем значения по умолчанию
if (isset($def['defaults'])) {
    foreach ($def['defaults'] as $name => $value) {
        if (!isset($variables[$name])) {
            $variables[$name] = $value;
        }
    }
}

// Предварительное форматирование переметров
if (isset($def['prepare'])) {
    foreach ($def['prepare'] as $name => $function) {
        if (isset($variables[$name]) && null !== $variables[$name]) {
            $variables[$name] = $function($variables[$name]);
        }
    }
}

$file = "{$_SERVER['DOCUMENT_ROOT']}/{$def['file']}";
if (!file_exists($file)) {
    returnAnswer(
        HttpCode::HTTP_500, 
        array(
            'result' => 'error', 
            'error' => "Не найден файл метода"
        )
    );
    exit;
}

require_once $file;
exit;
?>