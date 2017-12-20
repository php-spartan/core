<?php
define('IS_CLI',PHP_SAPI=='cli');//模式
define('NS',DIRECTORY_SEPARATOR);//标准分隔符
define('CLASS_EXT','.class.php');//设置类的后缀名
define('FRAME_PATH',__DIR__.NS);//设置框架路径
IS_CLI && define('URL_PATH',isset($argv[1])?$argv[1]:'');//如果是命令行，定义传入的参数

class Spt {
    public static $arrInstance = [];// 实例化对象
    public static $arrConfig = [];//站点实例的配置
    public static $arrLang = [];//语言包
    private static $arrError = [];//错误信息

    /**
     * 框架开始启动
     * @param array $_arrConfig
     */
    public static function start($_arrConfig = []){
        error_reporting(E_ALL);
        spl_autoload_register('Spt::autoLoad');//注册AUTOLOAD方法
        set_error_handler('Spt::appError');//用户自定义的错误处理函数
        set_exception_handler('Spt::appException');//用户自己的异常处理方法
        register_shutdown_function('Spt::appShutdown');//脚本执行关闭
        version_compare(PHP_VERSION,'5.6','<') && die('You need to use version 5.6 or higher.');
        !defined('APP_ROOT') && die('You need to defined "APP_ROOT" of you site root directory.');
        substr(APP_ROOT,-1) != NS && die('"APP_ROOT" needs a band "'.NS.'" to end.');
        (!isset($_arrConfig['APP_NAME']) || !$_arrConfig['APP_NAME']) && die('You need to configure site\'s variable "APP_NAME".');
        (!isset($_arrConfig['LANG']) || !$_arrConfig['LANG']) && $_arrConfig['LANG'] = 'zh-cn';
        (!isset($_arrConfig['TIME_ZONE']) || !$_arrConfig['TIME_ZONE']) && $_arrConfig['TIME_ZONE'] = 'PRC';
        define('APP_NAME',ucfirst(strtolower($_arrConfig['APP_NAME'])));//项目名称
        define('APP_PATH',APP_ROOT.APP_NAME.NS);//APP的根目录
        date_default_timezone_set($_arrConfig['TIME_ZONE']);//设置系统时区
        define('HOST',$_arrConfig['HOST']);//完整域名
        define('DOMAIN',$_arrConfig['DOMAIN']);//域名段
        self::$arrConfig = $_arrConfig;//全局化当前配置
        self::$arrConfig['DEBUG'] && self::createAppDir(); //检测并创建目录
        self::loadApp();
        if (self::$arrConfig['SERVER']){
            self::runServer();
        }else{
            if (C('SESSION_HANDLER')){
                ini_set('session.save_handler',C('SESSION_HANDLER.NAME'));
                ini_set('session.save_path',C('SESSION_HANDLER.PATH'));
            }
            self::runController();
        }
    }

    /**
     * 创建app必要目录
     */
    private static function createAppDir() {
        $arrDir = Array(
            APP_PATH,
            APP_PATH.'Controller'.NS,
            APP_PATH.'Common'.NS,
        );
        if (!self::$arrConfig['SERVER']){
            $arrDir[] = APP_PATH.'View'.NS;
            $arrDir[] = APP_PATH.'Runtime'.NS;
            $arrDir[] = APP_PATH.'Runtime'.NS.'Cache'.NS;
            $arrDir[] = APP_PATH.'Runtime'.NS.'Log'.NS;
        }else{
            $arrDir[] = APP_PATH.'Logic'.NS;
        }
        foreach ($arrDir as $dir){
            !is_dir($dir) && mkdir($dir,0755,true);
        }
    }

    /**
     * 加载语言包、函数、配置文件
     */
    private static function loadApp(){
        $arrFile = Array(
            FRAME_PATH.'Lang'.NS.self::$arrConfig['LANG'].'.lang.php',
            FRAME_PATH.'Common'.NS.'Functions.php',
            APP_PATH.'Common'.NS.'Functions.php',
            APP_ROOT.'Common'.NS.'Functions.php',
            FRAME_PATH.'Common'.NS.'Config.php',
            APP_PATH.'Common'.NS.'Config.php'
        );
        foreach ($arrFile as $file){
            if (!is_file($file)){
                continue;
            }
            if (stripos($file,'Config.php') > 0){
                C(include($file));
            }elseif (stripos($file,'.lang.php') > 0){
                self::$arrLang = array_merge(self::$arrLang,include($file));
            }else{
                include($file);
            }
        }
        if (self::$arrConfig['SERVER']){
            self::loadDirFile(FRAME_PATH.'Core');
            self::loadDirFile(APP_ROOT.'Common');
            self::loadDirFile(APP_PATH.'Controller');
        }
    }

    /**
     * 加载某一目录下所有文件，预加载
     * @param $strDir
     * @param $ext
     */
    public static function loadDirFile($strDir,$ext = CLASS_EXT){
        $arrDir = explode(',',$strDir);
        $intExtLen = strlen($ext);
        foreach($arrDir as $dir){
            $arrCore = new RecursiveDirectoryIterator(rtrim($dir,NS).NS);
            foreach($arrCore as $objFile){
                $strFile = $objFile->getPathname();
                substr($strFile,0 - $intExtLen) == $ext && include_once($strFile);
            }
        }
    }

    /**
     * 提取一个实例
     * @param $className
     * @param array $config
     * @return mixed
     */
    public static function getInstance($className,$config = []){
        if (!isset(self::$arrInstance[$className])){
            self::$arrInstance[$className] = new $className($config);
        }
        return self::$arrInstance[$className];
    }

    /**
     * 设置一个实例
     * @param $className
     * @param $objClass
     * @return mixed
     */
    public static function setInstance($className,$objClass){
        self::$arrInstance[$className] = $objClass;
        return $objClass;
    }

    /**
     * 设置一个提示语言
     * @param string $name
     * @param string $msg
     * @return mixed|string
     */
    public static function setLang($name,$msg=''){
        self::$arrLang[$name] = $msg;
        return $msg;
    }

    /**
     * 返回一个提示语言
     * @param string $msg
     * @return mixed|string
     */
    public static function getLang($msg=''){
        return isset(self::$arrLang[$msg])?self::$arrLang[$msg]:$msg;
    }

    /**
     * 自动载加类
     * @param $class
     */
    public static function autoLoad($class){
        $appName = strstr($class,'\\',true);
        $dirName = strstr($class,'\\', false);
        if ($appName == 'Spartan'){
            $dirName = FRAME_PATH . $dirName;
        }else{//如果不是系统
            $dirName = ($appName == APP_ROOT ? APP_PATH : APP_PATH . '../' . $appName) . $dirName;
        }
        $fileName = str_replace('//','/',str_replace('\\','/',$dirName)) . CLASS_EXT;
        if (IS_CLI && !is_file($fileName)){
            $dirName = realpath(pathinfo($fileName)['dirname']);
            $fileName = $dirName.NS.pathinfo($fileName)['basename'];
        }
        is_file($fileName) && include($fileName);
    }

    /**
     * 错误处理
     * @access public
     * @param  integer $errNo      错误编号
     * @param  integer $errStr     详细错误信息
     * @param  string  $errFile    出错的文件
     * @param  integer $errLine    出错行号
     * @return void
     * @throws ErrorException
     */
    public static function appError($errNo, $errStr, $errFile = '', $errLine = 0){
        self::$arrError[] = Array('message'=>$errStr,'code'=>$errNo,'file'=>$errFile,'line'=>$errLine,'severity'=>E_ERROR);
    }

    /**
     * 异常处理
     * @access public
     * @param  Throwable $e 异常
     * @return void
     */
    public static function appException($e){
        if ($e instanceof \ParseError) {
            $message  = 'Parse error: ' . $e->getMessage();
            $severity = E_PARSE;
        } elseif ($e instanceof \TypeError) {
            $message  = 'Type error: ' . $e->getMessage();
            $severity = E_RECOVERABLE_ERROR;
        } else {
            $message  = 'Fatal error: ' . $e->getMessage();
            $severity = E_ERROR;
        }
        self::$arrError[] = Array('message'=>$message,'code'=>$e->getCode(),'file'=>$e->getFile(),'line'=>$e->getLine(),'severity'=>$severity);
    }

    /**
     * 异常中止处理
     * @access public
     * @return void
     */
    public static function appShutdown(){
        $error = error_get_last();
        if (is_null($error) && !self::$arrError){
            return;
        }
        if (in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            self::$arrError[] = Array('message'=>$error['message'],'code'=>$error['type'],'file'=>$error['file'],'line'=>$error['line']);
            self::halt();
        }
        self::$arrConfig['DEBUG'] && self::halt();
        self::$arrConfig['SAVE_LOG'] && self::saveLog();
    }

    /**
     * 错误输出
     * @param string $title
     * @param string $info
     */
    public static function halt($info = '',$title = 'system error'){
        $error = Array('title'=>self::getLang($title),'message'=>$info);
        $trace = debug_backtrace();
        $error['file'] = $trace[0]['file'];
        $error['line'] = $trace[0]['line'];
        ob_start();
        debug_print_backtrace();
        $error['trace'] = ob_get_clean();
        $arrException = Array();
        sort(self::$arrError);
        foreach (self::$arrError as $v){
            !$error['message'] && $error['message'] = $v['message'];
            $arrException[] = $v['message'] . '<br>' . $v['file'] . '<br>' . 'code:'.$v['code'].',line:'.$v['line'];
        }
        self::$arrError = [];
        if (IS_CLI){//调试模式下输出错误信息
            $error['exception'] = implode(PHP_EOL,$arrException);
            unset($error['title']);
            foreach ($error as $k=>$v){
                print_r(iconv('UTF-8','gbk',$k.'='.str_ireplace('<br>',PHP_EOL,$v).PHP_EOL));
            }
        }else{
            $error['exception'] = '<p>' . implode('</p><p>',$arrException) . '</p>';
            include(FRAME_PATH.'Tpl'.NS.'Exception.html');
        }
        exit(0);
    }

    /**
     * Cli下显错误
     * @param string $msg
     * @param bool $end
     */
    public static function console($msg='',$end = false){
        print_r('+++++++++++++++++++++++++++++++++++++'.PHP_EOL);
        print_r($msg);
        print_r('+++++++++++++++++++++++++++++++++++++'.PHP_EOL);
        $end && exit(0);
    }

    /**
     * 保存错误日志
     * @param string $info
     */
    public static function saveLog($info = 'system error') {
        //TODO
    }

    /**
     * 启动运行一个服务
     */
    private static function runServer(){
        !IS_CLI && self::console('Service only run in cli model.',true);
        (!isset(self::$arrConfig['MAIN_FUN']) || !self::$arrConfig['MAIN_FUN']) && self::$arrConfig['MAIN_FUN'] = 'runMain';
        $strClass = APP_NAME . '\\Controller\\MainController';//入口类
        !class_exists($strClass,true) && self::console($strClass.' not exist.',true);
        $objClass = new $strClass();
        !class_exists($strClass,true) && self::console($strClass.' not exist.',true);
        !method_exists($objClass,self::$arrConfig['MAIN_FUN']) && self::console("{$strClass}'s main function[".self::$arrConfig['MAIN_FUN']."] don't exits.",true);
        $objClass->{self::$arrConfig['MAIN_FUN']}();//入口函数
    }

    /**
     * 运行控制器
     */
    private static function runController() {
        $strPath = (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';//优先从REQUEST_URI
        (!$strPath && isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO']) && $strPath = $_SERVER['PATH_INFO'];//其次从PATH_INFO
        !$strPath && self::halt("This server don't support PathInfo.Unable to get URL address.");//无法得到URL
        //开始整理得到的URL
        (($intPos = strpos($strPath,'?')) !== false) && $strPath = substr($strPath,0,$intPos);//只拿到？号之前
        $strPath = str_ireplace('.php','',str_ireplace('.html','',$strPath));//去掉常见后缀
        $arrPath = array_values(array_filter(explode('/',strip_tags($strPath))));//得到 / 拆分后的干净数组
        !$arrPath && $arrPath[0] = $arrPath[1] = 'index';//默认为index
        (!isset($arrPath[1]) || !$arrPath[1]) && $arrPath[1] = 'index';//默认为index方法
        !preg_match('/^[A-Za-z_]([A-Za-z0-9_])*$/',$arrPath[0]) && $arrPath[0] = 'index';//控制器不合法时设置为index
        $strControl = ucfirst($arrPath[0]);//得到控制器
        $strAction = $arrPath[1];//得到方法
        define('__URL__',implode('/',$arrPath));//定义全局使用的最终URL
        define('__CONTROL__',$strControl);//定义全局使用的最终控制器
        define('__ACTION__',$strAction);//定义全局使用的最终方法
        unset($strPath,$arrPath,$intPos);
        $strModule = APP_NAME . '\\Controller\\' . $strControl . 'Controller';//目标类
        $strEmptyModule = APP_NAME . '\\Controller\\EmptyController';//空类
        $objModule = class_exists($strModule)?new $strModule():null;//实例化目标类
        if (!is_object($objModule)){//如果没有得到指一的，就使用空控制器
            (class_exists($strEmptyModule) && $strControl = 'Empty') && $objModule = new $strEmptyModule();
        }
        !is_object($objModule) && self::halt(//控制器 和 空控制都不存在，退出并提示
            "[".__CONTROL__."Controller]({$strModule}) Controller not existing.<br>".
            "[EmptyController]({$strEmptyModule}) Controller not existing."
        );
        if(!method_exists($objModule,$strAction)){//方法 和 空方法都不存在，退出并提示
            !method_exists($objModule,'_empty') && self::halt(
                "{$strControl}Controller function [{$strAction}] or [_empty] not existing."
            );
            $strAction = '_empty';//真正执行的方法
        }
        $objModule->{$strAction}(__ACTION__);//执行对应控制器的方法，并把预想的方法传入。
    }


} 