<?php

header('Content-Type: application/json; charset=UTF-8');

include 'init.php';
include 'func_calcTime.php';

$ret = calcTime($db, $paramValues['division'], $paramValues['service'], $paramValues['slaLevel'], 0);
echo json_encode(array('_createdAt' => $ret['createdAt'], '_repairBefore' => $ret['repairBefore']));
?>