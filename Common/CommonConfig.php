<?php
defined('APP_PATH') or die('404 Not Found');
$arrConfig =  Array(
    'SITE'=>Array(
        'NAME'=>'我的小站',
        'KEY_NAME'=>'我的小站',
        'DESCRIPTION'=>'我的小站。',
    ),
    'DB'=>Array(//数据库设置
        'TYPE'=>'mysqli',
        'HOST'=>'localhost',
        'NAME'=>'test',
        'USER'=>'test',
        'PWD'=>'test',
        'PORT'=>'3306',
        'PREFIX'=>'s_',
        'CHARSET'=>'utf8',
    ),
    'SESSION_HANDLER'=>Array(
        'NAME'=>'redis',
        'PATH'=>'tcp://120.78.80.218:63798?auth=foobaredf23fdafasflxvxz.vaf;jdsafi2pqfjaf;;dsafj;sajfsapfisapjf',
    ),
    'EMAIL' => Array(
        'SERVER' => 'smtp.exmail.qq.com',
        'USER_NAME' => '',
        'PASS_WORD' => '',
        'PORT' => 25,
        'FROM_EMAIL' => '', //发件人EMAIL
        'FROM_NAME' => 'Mrs langshen', //发件人名称
    )
);