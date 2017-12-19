<?php
defined('APP_PATH') or die('404 Not Found');
//系统必须的默认配置
return Array(
    /* 默认设定 */
    'DEFAULT_FILTER'        =>  'htmlspecialchars', // 默认参数过滤方法 用于I函数...
    /* COOKIES设置 */
    'COOKIE'=>Array(
        'PREFIX'=>'',
        'EXPIRE'=>'',
        'PATH'=>'/',
        'DOMAIN'=>'.'.DOMAIN,
        'HTTPONLY'=>'',
    ),
    'SESSION'=>Array(
        'AUTO_START'=>true,// 是否自动开启Session
        'PREFIX'=>'',// session 前缀
        'VAR_ID'=>'SPASESSION',//sessionID的变量名
        'DOMAIN'=>'.'.DOMAIN,
        'EXPIRE'=>24*3600,//存活时间
    ),
    /* 模板引擎设置 */
    'TMPL_DEFAULT_THEME'    => '',//默认的主题，如果有主题，后面要带/
    'DEFAULT_CHARSET'       =>  'utf-8',
    'TMPL_ACTION_ERROR'     =>  FRAME_PATH.'Tpl/Dispatch_jump.', // 默认错误跳转对应的模板文件
    'TMPL_ACTION_SUCCESS'   =>  FRAME_PATH.'Tpl/Dispatch_jump.html', // 默认成功跳转对应的模板文件
    'TMPL_TEMPLATE_SUFFIX'  =>  '.html',     // 默认模板文件后缀
    // 布局设置
    'TMPL_DENY_FUNC_LIST'   =>  'echo,exit',    // 模板引擎禁用函数
    'TMPL_DENY_PHP'         =>  false, // 默认模板引擎是否禁用PHP原生代码
    'TMPL_VAR_IDENTIFY'     =>  'array',     // 模板变量识别。留空自动判断,参数为'obj'则表示对象
    'TMPL_CACHE_ON'         =>  true,        // 是否开启模板编译缓存,设为false则每次都会重新编译
    'TMPL_CACHE_TIME'       =>  0,         // 模板缓存有效期 0 为永久，(以数字为值，单位:秒)
    /* 系统变量名称设置 */
    'VAR_AJAX_SUBMIT'       =>  'ajax',  // 默认的AJAX提交变量
    'HTTP_CACHE_CONTROL'    =>  'private',  // 网页缓存控制
);