<?php
include_once('config.php');
header('Content-Type: application/json; charset=UTF-8');
if (!isset($_POST['op'])) {
	echo json_encode(array('error' => 'NoOp'));
	exit;
}
$result = array();
switch ($_REQUEST['op']) {
	case 'getEq':
		if (!isset($_REQUEST['num']) || !preg_match('~(\d+)~', $_REQUEST['num'], $match)) {
			$result['error'] = 'Invalid parameters';
			break;
		}
		$result = getEquipmentInfo($match[1]); // sn, onSrv, div, brand, model, type
		$result['num'] = $match[1];
		break;
}
echo json_encode($result);
?>