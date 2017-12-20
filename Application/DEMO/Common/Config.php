<?php
$arrConfig = include(APP_ROOT.'Common'.NS.'Config.php');

$arrTemp = Array(
    //订单支付状态
    'ORDER_STATUS' => Array(
        1 => '等待支付',
        2 => '支付成功',
        3 => '支付失败',
        4 => '交易关闭',
        5 => '交易成功有退款',
        6 => '退款成功',
    ),
);

return array_merge($arrConfig,$arrTemp);