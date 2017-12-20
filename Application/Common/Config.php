<?php
$arrConfig = [];
include 'CommonConfig.php';

$arrTemp =  Array(
    'CASH_STATUS'=>Array(//提现的状态
        '审核进行中',
        '审核成功',
        '审核失败',
    ),
    'INPOUR_STATUS' => Array(
        '失败',
        '进行中',
        '成功',
        '差额异常',
    ),
    'REAL_STATUS'=>Array(
        '0'=>'未完成',
        '1'=>'审核中',
        '2'=>'已完成',
        '3'=>'未通过',
    ),
    'NORMAL_STATUS'=>Array(
        '0'=>'未知',
        '1'=>'审核中',
        '2'=>'正常',
        '3'=>'未通过',
    ),
    'PAYMENT' => Array(
        1 => '支付宝',
        2 => '微信支付',
    )
);
return array_merge($arrConfig,$arrTemp);
