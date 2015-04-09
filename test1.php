<?php

$arr = array(18897109, 12828837, 9461105, 6371773, 5965343, 5946800, 5582170, 5564635, 5268860, 4552402, 4335391, 4296250, 4224851, 4192887, 3439809, 3279833, 3095313, 2812896, 2783243, 2710489, 2543482, 2356285, 2226009, 2149127, 2142508,  2134411);

function find($arr, $res, $sum, $code) {
    if (count($arr) == 0) {
	return 0;
    }
    $el = array_pop($arr);
    if ($sum+$el == 100000000) {
	$res[] = $el;
	return $res;
    }
    $res1 = find($arr, $res, $sum, "{$code}0");
    if (is_array($res1))
	return $res1;
    $res[] = $el;
    return find($arr, $res, $sum+$el, "{$code}1");
}

$res = find($arr, array(), 0, '');
if (is_array($res)) {
    var_dump($res);
} else
    echo 'fail';

?>
