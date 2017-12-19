<?php
/**
 * 随机字符串
 * @param $length
 * @return string
 */
function getRandomString($length){
    $arr = array_merge(range(0, 9), range('A', 'Z'));
    $str = '';
    $arr_len = count($arr);
    for ($i = 0; $i < $length; $i++) {
        $rand = mt_rand(0, $arr_len-1);
        $str .= $arr[$rand];
    }
    return $str;
}
