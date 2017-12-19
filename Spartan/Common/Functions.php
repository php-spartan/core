<?php
/**
 * 获取和设置配置参数 支持批量定义
 * @param string|array $name 配置变量
 * @param mixed $value 配置值
 * @param mixed $default 默认值
 * @return mixed
 */
function C($name=null, $value=null,$default=null) {
    static $_config = array();
    // 无参数时获取所有
    if (empty($name)) {
        return $_config;
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtoupper($name);
            if (is_null($value)){
                return isset($_config[$name]) ? $_config[$name] : $default;
            }
            $_config[$name] = $value;
            return true;
        }
        // 二维数组设置和获取支持
        $name = explode('.', $name);
        $name[0] = strtoupper($name[0]);
        if (is_null($value)) {
            if(count($name)==2){
                return isset($_config[$name[0]][$name[1]])?$_config[$name[0]][$name[1]]:$default;
            }elseif(count($name)==3){
                return isset($_config[$name[0]][$name[1]][$name[2]])?$_config[$name[0]][$name[1]][$name[2]]:$default;
            }
        }
        $_config[$name[0]][$name[1]] = $value;
        return $value;
    }
    // 批量设置
    if (is_array($name)){
        $name = array_change_key_case($name,CASE_UPPER);
        foreach($name as $k=>$v){
            $_config[$k] = isset($_config[$k])?array_merge($_config[$k],$v):$v;
        }
        return true;
    }
    return null; // 避免非法参数
}

/**
 * session管理函数
 * @param string|array $name session名称 如果为数组则表示进行session设置
 * @param mixed $value session值
 * @param bool $commit 是否马上提交保存
 * @return mixed
 */
function session($name='',$value='',$commit=true) {
    $prefix = C('SESSION.PREFIX');//sso_session:
    if(is_array($name)) {
        isset($name['prefix'])&&C('SESSION.PREFIX',$name['prefix']);
        (isset($name['id'])&&$name['id'])&&session_id($name['id']);
        (isset($name['name'])&&$name['name'])&&session_name($name['name']);
        (isset($name['path'])&&$name['path'])&&session_save_path($name['path']);
        isset($name['domain'])&&ini_set('session.cookie_domain',$name['domain']);//设置HTTP和HTTPS跨域
        isset($name['expire']) && ini_set('session.gc_maxlifetime', $name['expire']);
        isset($name['use_trans_sid']) && ini_set('session.use_trans_sid', $name['use_trans_sid']?1:0);
        isset($name['use_cookies']) && ini_set('session.use_cookies', $name['use_cookies']?1:0);
        isset($name['cache_limiter']) && session_cache_limiter($name['cache_limiter']);
        isset($name['cache_expire']) && session_cache_expire($name['cache_expire']);
        if(C('SESSION.AUTO_START')){session_start();}
    }elseif('' === $value){// 获取全部的session
        if(''===$name){
            return $_SESSION;
        }elseif(0===strpos($name,'[')) { // session 操作
            if('[pause]'==$name){ // 暂停session
                session_write_close();
            }elseif('[start]'==$name){ // 启动session
                session_start();
            }elseif('[destroy]'==$name){ // 销毁session
                $_SESSION =  array();
                session_unset();
                session_destroy();
            }elseif('[regenerate]'==$name){ // 重新生成id
                session_regenerate_id();
            }
        }elseif(0===strpos($name,'?')){ // 检查session
            $name = substr($name,1);
            if(strpos($name,'.')){ // 支持数组
                list($name1,$name2) = explode('.',$name);
                return $prefix?isset($_SESSION[$prefix][$name1][$name2]):isset($_SESSION[$name1][$name2]);
            }else{
                return $prefix?isset($_SESSION[$prefix][$name]):isset($_SESSION[$name]);
            }
        }elseif(is_null($name)){ // 清空session
            if($prefix) {
                unset($_SESSION[$prefix]);
            }else{
                $_SESSION = array();
            }
        }elseif($prefix){ // 获取session
            if(strpos($name,'.')){
                list($name1,$name2) =   explode('.',$name);
                return isset($_SESSION[$prefix][$name1][$name2])?$_SESSION[$prefix][$name1][$name2]:null;
            }else{
                return isset($_SESSION[$prefix][$name])?$_SESSION[$prefix][$name]:null;
            }
        }else{
            if(strpos($name,'.')){
                list($name1,$name2) =   explode('.',$name);
                return isset($_SESSION[$name1][$name2])?$_SESSION[$name1][$name2]:null;
            }else{
                return isset($_SESSION[$name])?$_SESSION[$name]:null;
            }
        }
    }elseif(is_null($value)){ // 删除session
        if($prefix){
            unset($_SESSION[$prefix][$name]);
        }else{
            unset($_SESSION[$name]);
        }
        return null;
    }else{ // 设置session
        if($prefix){
            if (!isset($_SESSION[$prefix])) {
                $_SESSION[$prefix] = array();
            }
            $_SESSION[$prefix][$name] = $value;
        }else{
            $_SESSION[$name] = $value;
        }
        if($commit==true){session_commit();}
    }
    return true;
}

/**
 * Cookie 设置、获取、删除
 * @param string $name cookie名称
 * @param mixed $value cookie值
 * @param mixed $option cookie参数
 * @return mixed
 */
function cookie($name='', $value='', $option=null) {
    // 默认设置
    $config = array(
        'prefix'    =>  C('COOKIE.PREFIX'), // cookie 名称前缀
        'expire'    =>  C('COOKIE.EXPIRE'), // cookie 保存时间
        'path'      =>  C('COOKIE.PATH'), // cookie 保存路径
        'domain'    =>  C('COOKIE.DOMAIN'), // cookie 有效域名
        'httponly'  =>  C('COOKIE.HTTPONLY'), // httponly设置
    );
    // 参数设置(会覆盖黙认设置)
    if (!is_null($option)) {
        if (is_numeric($option))
        {$option = array('expire' => $option);}
        elseif (is_string($option))
        {parse_str($option, $option);}
        $config = array_merge($config, array_change_key_case($option));
    }
    if(!empty($config['httponly'])){
        ini_set("session.cookie_httponly", 1);
    }
    // 清除指定前缀的所有cookie
    if (is_null($name)) {
        if (empty($_COOKIE)){return null;};
        // 要删除的cookie前缀，不指定则删除config设置的指定前缀
        $prefix = empty($value) ? $config['prefix'] : $value;
        if (!empty($prefix)) {// 如果前缀为空字符串将不作处理直接返回
            foreach ($_COOKIE as $key => $val) {
                if (0 === stripos($key, $prefix)) {
                    setcookie($key, '', time() - 3600, $config['path'], $config['domain']);
                    unset($_COOKIE[$key]);
                }
            }
        }
        return null;
    }elseif('' === $name){
        // 获取全部的cookie
        return $_COOKIE;
    }
    $name = $config['prefix'] . str_replace('.', '_', $name);
    if ('' === $value) {
        if(isset($_COOKIE[$name])){
            $value =    $_COOKIE[$name];
            if(0===strpos($value,'spa:')){
                $value = substr($value,4);
                return array_map('urldecode',json_decode($value,true));
            }else{
                return $value;
            }
        }else{
            return null;
        }
    } else {
        if (is_null($value)) {
            setcookie($name, '', time() - 3600, $config['path'], $config['domain']);
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {
            // 设置cookie
            if(is_array($value)){
                $value  = 'spa:'.json_encode(array_map('urlencode',$value),JSON_UNESCAPED_UNICODE);
            }
            $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
            setcookie($name, $value, $expire, $config['path'], $config['domain']);
            $_COOKIE[$name] = $value;
        }
        return true;
    }
}

/**
 * 去除代码中的空白和注释
 * @param string $content 代码内容
 * @return string
 */
function strip_whitespace($content) {
    $stripStr   = '';
    //分析php源码
    $tokens     = token_get_all($content);
    $last_space = false;
    for ($i = 0, $j = count($tokens); $i < $j; $i++) {
        if (is_string($tokens[$i])) {
            $last_space = false;
            $stripStr  .= $tokens[$i];
        } else {
            switch ($tokens[$i][0]) {
                //过滤各种PHP注释
                case T_COMMENT:
                case T_DOC_COMMENT:
                    break;
                //过滤空格
                case T_WHITESPACE:
                    if (!$last_space) {
                        $stripStr  .= ' ';
                        $last_space = true;
                    }
                    break;
                case T_START_HEREDOC:
                    $stripStr .= "<<<St\n";
                    break;
                case T_END_HEREDOC:
                    $stripStr .= "St;\n";
                    for($k = $i+1; $k < $j; $k++) {
                        if(is_string($tokens[$k]) && $tokens[$k] == ';') {
                            $i = $k;
                            break;
                        } else if($tokens[$k][0] == T_CLOSE_TAG) {
                            break;
                        }
                    }
                    break;
                default:
                    $last_space = false;
                    $stripStr  .= $tokens[$i][1];
            }
        }
    }
    return $stripStr;
}
/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * I('id',0); 获取id参数 自动判断get或者post
 * I('post.name','','htmlspecialchars'); 获取$_POST['name']
 * I('get.'); 获取$_GET
 * </code>
 * @param string $name 变量的名称 支持指定类型
 * @param mixed $default 不存在的时候默认值
 * @param mixed $filter 参数过滤方法
 * @param mixed $datas 要获取的额外数据源
 * @return mixed
 */
function I($name,$default='',$filter=null,$datas=null) {
    if(strpos($name,'.')) { // 指定参数来源
        list($method,$name) =   explode('.',$name,2);
    }else{ // 默认为自动判断
        $method =   'param';
    }
    switch(strtolower($method)) {
        case 'get'     :   $input =& $_GET;break;
        case 'post'    :   $input =& $_POST;break;
        case 'put'     :   parse_str(file_get_contents('php://input'), $input);break;
        case 'param'   :
            switch($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input  =  $_POST;
                    break;
                case 'PUT':
                    parse_str(file_get_contents('php://input'), $input);
                    break;
                default:
                    $input  =  $_GET;
            }
            break;
        case 'path'    :
            $input  =   array();
            if(!empty($_SERVER['PATH_INFO'])){
                $depr   =   C('URL_PATHINFO_DEPR');
                $input  =   explode($depr,trim($_SERVER['PATH_INFO'],$depr));
            }
            break;
        case 'request' :   $input =& $_REQUEST;   break;
        case 'session' :   $input =& $_SESSION;   break;
        case 'cookie'  :   $input =& $_COOKIE;    break;
        case 'server'  :   $input =& $_SERVER;    break;
        case 'globals' :   $input =& $GLOBALS;    break;
        case 'data'    :   $input =& $datas;      break;
        default:
            return NULL;
    }
    if(empty($name)) { // 获取全部变量
        $data       =   $input;
        array_walk_recursive($data,'filter_exp');
        $filters    =   isset($filter)?$filter:C('DEFAULT_FILTER');
        if($filters) {
            $filters    =   explode(',',$filters);
            foreach($filters as $filter){
                $data   =   array_map_recursive($filter,$data); // 参数过滤
            }
        }
    }elseif(isset($input[$name])) { // 取值操作
        $data       =   $input[$name];
        is_array($data) && array_walk_recursive($data,'filter_exp');
        $filters    =   isset($filter)?$filter:C('DEFAULT_FILTER');
        if($filters) {
            $filters    =   explode(',',$filters);
            foreach($filters as $filter){
                if(function_exists($filter)) {
                    $data   =   is_array($data)?array_map_recursive($filter,$data):$filter($data); // 参数过滤
                }else{
                    $data   =   filter_var($data,is_int($filter)?$filter:filter_id($filter));
                    if(false === $data) {
                        return   isset($default)?$default:NULL;
                    }
                }
            }
        }
    }else{ // 变量默认值
        $data       =    isset($default)?$default:NULL;
    }
    return $data;
}

function array_map_recursive($filter, $data) {
    $result = array();
    foreach ($data as $key => $val) {
        $result[$key] = is_array($val)
            ? array_map_recursive($filter, $val)
            : call_user_func($filter, $val);
    }
    return $result;
}
/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id   数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root='spa', $item='item', $attr='', $id='id', $encoding='utf-8') {
    if(is_array($attr)){
        $_attr = array();
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr   = trim($attr);
    $attr   = empty($attr) ? '' : " {$attr}";
    $xml    = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml   .= "<{$root}{$attr}>";
    $xml   .= data_to_xml($data, $item, $id);
    $xml   .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed  $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id   数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item='item', $id='id') {
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if(is_numeric($key)){
            $id && $attr = " {$id}=\"{$key}\"";
            $key  = $item;
        }
        $xml    .=  "<{$key}{$attr}>";
        $xml    .=  (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml    .=  "</{$key}>";
    }
    return $xml;
}

/**
 * @description 解析模版内容
 * @param string $content 模版内容，可带{xxx}这样的变量
 * @param array $arrValue 模版变量的值，如：array('user_name') = 'singer',会替换{user_name}的值为singer
 * @return string $content 返回处理完的模版内容
 */
function parseContent($content,$arrValue){
    $content = htmlspecialchars_decode($content);
    preg_match_all('/\{\$(.*?)\}/',$content,$value);
    for($i = 0; $i < count($value[1]); $i++)
    {
        $v = isset($arrValue[$value[1][$i]])?$arrValue[$value[1][$i]]:'';
        $content = str_replace($value[0][$i], $v, $content);
    }
    return $content;
}

/**
 * 返回是否Ajax提交
 * @return bool
 */
function isAjax(){
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || !empty($_POST[C('VAR_AJAX_SUBMIT')]) || !empty($_GET[C('VAR_AJAX_SUBMIT')]);
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0,$adv=true) {
    $type       =  $type ? 1 : 0;
    static $ip  =   NULL;
    if ($ip !== NULL) return $ip[$type];
    if($adv){
        if (isset($_SERVER['HTTP_REMOTE_HOST'])) {
            $ip     =   $_SERVER['HTTP_REMOTE_HOST'];
        }elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos    =   array_search('unknown',$arr);
            if(false !== $pos){unset($arr[$pos]);}
            $ip     =   trim($arr[0]);
        }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip     =   $_SERVER['HTTP_CLIENT_IP'];
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
    }elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip     =   $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u",ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

function redirect($url, $time=0, $msg='') {
    header("Content-type: text/html; charset=utf-8");
    $url  = str_replace(Array("\n", "\r"), '', $url);
    empty($msg) && $msg = "the page will redirect to {$url} after {$time} seconds.";
    if (!headers_sent()){
        if (0 === $time) {
            header('Location: ' . $url);
        } else {
            header("refresh:{$time};url={$url}");
            echo($msg);
        }
        exit();
    } else {
        $str    = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
        $time != 0 && $str .= $msg;
        exit($str);
    }
}

function isEmail($strEmail){
    $strPreg = "/^([a-zA-Z0-9]+[_|\_|\.]?)*[a-zA-Z0-9]+@([A-Za-z0-9]+((\.|-)[A-Za-z0-9]+)*\.)+[a-zA-Z]{2,6}$/i";
    return preg_match($strPreg, $strEmail);
}

function isMobile($strMobile){
    return preg_match('/^1[0-9]{10}$/', $strMobile);
}

function isMobileAgent($strUserAgent){
    return preg_match('/(Phone|iPad|iPod|Android|ios|SymbianOS|mobile)/i',$strUserAgent)?1:0;
}

function isRealName($realName){
    if (preg_match("/[^\x80-\xff]/", $realName)){
        return false;
    }
    if (mb_strlen($realName, 'UTF-8')<2||mb_strlen($realName,'UTF-8')>32){
        return false;
    }
    return true;
}

function isUserName($strUserName){
    if (mb_strlen($strUserName,'utf-8') < 1){
        return false;
    }
    return preg_match("/^[\\x80-\\xff]+[0-9]*$/", $strUserName);
    //return preg_match("/^[^\\x00-\\x80]+[0-9]*$/", $strUserName);
//    return
//    $count = 0;
//    for($i = 0; $i < strlen($strUserName); $i++){
//        $value = ord($strUserName[$i]);
//        if($value > 127) {
//            $count++;
//            if($value >= 192 && $value <= 223) $i++;
//            elseif($value >= 224 && $value <= 239) $i = $i + 2;
//            elseif($value >= 240 && $value <= 247) $i = $i + 3;
//        }
//        $count++;
//    }
//    return $count > 3 && $count < 32;
}


function checkVerifyCode($verifyCode, $verifyName = 'verifyCode'){
    $sessionCode = strtolower(session($verifyName));
    session($verifyName,null);
    if (!$sessionCode || ($sessionCode != strtolower($verifyCode))) {
        return false;
    }else{
        return true;
    }
}

function isIdCard($strCard,$bolSafe=false){
    if (mb_strlen($strCard, 'UTF-8') != 18){
        return false;
    }
    if (!$bolSafe){return true;}
    $wi = array(7,9,10,5,8,4,2,1,6,3,7,9,10,5,8,4,2);//加权因子
    $ai = array('1','0','X','9','8','7','6','5','4','3','2');//校验码串
    $sigma=0;//按顺序循环处理前17位
    for($i = 0;$i < 17;$i++) {
        $b = (int)$strCard{$i};//提取前17位的其中一位，并将变量类型转为实数
        $w = $wi[$i];//提取相应的加权因子
        $sigma += $b * $w;//把从身份证号码中提取的一位数字和加权因子相乘，并累加
    }
    $sNumber = $sigma % 11;//计算序号
    if($strCard{17} == $ai[$sNumber]){//按照序号从校验码串中提取相应的字符。
        return true;
    }else{
        return false;
    }
}

function getCeil($v,$n=2){
    $v = ceil($v*pow(10,$n));
    return $v/pow(10,$n);
}

function getFloor($v,$n=2){
    $v = floor($v*pow(10,$n));
    return $v/pow(10,$n);
}

function packFormat($guid, $msg = "OK", $code = 1, $data = []){
    return Array(
        "guid" => $guid,
        "code" => $code,
        "msg" => $msg,
        "data" => $data,
    );
}

function packEncode($data, $type = "tcp"){
    if ($type == "tcp") {
        $guid = $data["guid"];
        $sendStr = json_encode($data,JSON_HEX_TAG);
        $sendStr = gzencode($sendStr,4);
        $sendStr = pack('N', strlen($sendStr) + 32).$guid.$sendStr;
    } else if ($type == "http") {
        $sendStr = json_encode($data,JSON_HEX_TAG);
    } else {
        $sendStr = packFormat($data["guid"], "packet type wrong", 100006);
    }
    return $sendStr;
}

function packDecode($str,$format=true){
    $header = substr($str, 0, 4);
    $len = unpack("Nlen", $header);
    $len = $len["len"];
    $guid = substr($str, 4, 32);
    $result = substr($str, 36);
    $len = $len - 32;
    if ($len != strlen($result)) {//结果长度不对
        return packFormat($guid, "packet length invalid 包长度非法", 100007);
    }
    $result = json_decode(gzdecode($result),true);
    return $format?packFormat($guid, "OK", 0, $result):$result;
}
/**
 * 把经验值转成等级
 * @param $intScore
 * @return array
 */
function getLvInfo($intScore){
    $arrResult = ['lv'=>0,'lp'=>50];
    $tmpScore = 0;
    for($i=1;$i<100;$i++){
        $score = ceil(50*pow(1.3,$i));
        if ($score > $intScore){
            $arrResult = Array(
                'lv'=>$i,//等级
                'lp'=>$score,
                'pr'=>getCeil(($intScore - $tmpScore) / ($score - $tmpScore),2),
            );
            break;
        }
        $tmpScore = $score;
    }
    return $arrResult;
}

function postData($url,$data=[]){
    $arrPostUrl = [];
    if (!is_array($url)){
        $arrPostUrl[] = Array($url,$data);
    }else if(is_array($url) && !$data){
        foreach ($url as $v){
            $arrPostUrl[] = $v;
        }
    }
    $param = array(
        'api'=>$arrPostUrl,
        'guid'=>0,
        'type'=>1,
        'sys'=>Array(
            '_ver_' => '1.1',
            '_ip_' => get_client_ip(),
            '_agent_'=>I('server.HTTP_USER_AGENT','mobile'),
            '_token_'=>session_id(),
            'page'=> max(0, I('post.page')) + 1,
            'page_size' => I('post.page_size',20),
            'pageIndex' => max(0, I('post.pageIndex')) + 1,
            'pageSize' => I('post.pageSize',20),
            'sortField' => I('post.sortField',''),
            'sortOrder' => I('post.sortOrder',''),
            'search_type' => I('post.search_type',''),
            'search_symbol' => I('post.search_symbol',''),
            'search_key' => I('post.search_key',''),
            '_uri_'=> str_replace('//','/',I('server.HTTP_HOST').'/'.I('server.REQUEST_URI')),
        ),
        'session'=>session(),
    );
    $arrPostUrl = isset($param['api'])?$param['api']:[];
    if (!$arrPostUrl || !is_array($arrPostUrl)){
        return 'request url lost.[]';
    }
    unset($param['api']);
    $arrResult = [];
    foreach ($arrPostUrl as $arrUrl){
        $arrParam = Array(
            '_url_' => $arrUrl[0],
            'session' => $param['session'],
        );
        $arrParam = array_merge($arrParam,$param['sys'],$arrUrl[1]);
        $arrUrl = array_map(function($v){return ucfirst($v);},array_filter(explode('/',$arrUrl[0])));
        $strAction = array_pop($arrUrl);
        $strModule = 'Rpc\\Logic'.'\\'.implode('\\',$arrUrl);
        $objModule = class_exists($strModule)?new $strModule($arrParam):null;
        if (!$objModule || !is_object($objModule)){
            return "Url:{$arrParam['_url_']},class not exist:{$strModule}";
        }
        if (!method_exists($objModule,$strAction)){
            return "Url:{$arrParam['_url_']},class:{$strModule},method not exist:{$strAction}.";
        }
        \Rpc\Common\LogicRequest::$requestData = $arrParam;
        $arrThisResult = $objModule->{$strAction}();
        if (!isset($arrThisResult['info'])){
            list($info,$status,$data) = $arrThisResult;
        }else{
            $info = $arrThisResult['info'];
            $status = isset($arrThisResult['status'])?$arrThisResult['status']:0;
            $data = isset($arrThisResult['data'])?$arrThisResult['data']:0;
        }
        !$status && $status = 0;!$data && $data = [];
        $arrResult[] = Array('info'=>$info,'status'=>$status,'data'=>$data);
    }
    return count($arrPostUrl) == 1 ? $arrResult[0] : $arrResult;
}

/** 根据身份证获取用户性别(第十七位代表性别，奇数为男，偶数为女)0,未知，1男，2女 */
function getSexFromIdCard($idCard='')
{
    if(!isIdCard($idCard)){
        return 0;
    }
    $str = $idCard{16};
    if(intval($str)%2==1)
    {
        return 1;
    }
    return 2;
}

function taskNextRunTime($arrTime=[]){
    //$arrTime = $week_day=-1,$day=-1,$hour=-1,$minute=-1
    if (isset($arrTime['minute']) && $arrTime['minute']){
        $arrTime['minute'] = explode(',',$arrTime['minute']);
    }
    list($n_year,$n_month,$n_day,$n_weekday) = explode('-', date('Y-m-d-w-H-i', time()));

    if($arrTime['week_day'] == -1){//不限制周
        if($arrTime['day'] == -1){//不限制日
            $firstDay = $n_day;
            $secondDay = $n_day + 1;
        }else{
            $firstDay = $arrTime['day'];
            $secondDay = $arrTime['day'] + date('t', time());
        }
    }else{
        $firstDay = $n_day + ($arrTime['week_day'] - $n_weekday);
        $secondDay = $firstDay + 7;
    }
    $firstDay < $n_day && $firstDay = $secondDay;
    //
    if($firstDay == $n_day) {
        $todayTime = todayNextRun($arrTime);
        if($todayTime['hour'] == -1 && $todayTime['minute'] == -1) {
            $arrTime['day'] = $secondDay;
            $nextTime = todayNextRun($arrTime, 0, -1);
            $arrTime['hour'] = $nextTime['hour'];
            $arrTime['minute'] = $nextTime['minute'];
        } else {
            $arrTime['day'] = $firstDay;
            $arrTime['hour'] = $todayTime['hour'];
            $arrTime['minute'] = $todayTime['minute'];
        }
    } else {
        $arrTime['day'] = $firstDay;
        $nextTime = todayNextRun($arrTime, 0, -1);
        $arrTime['hour'] = $nextTime['hour'];
        $arrTime['minute'] = $nextTime['minute'];
    }
    $arrTime['minute'] = max(0,$arrTime['minute']);
    $nextTime = @mktime($arrTime['hour'], $arrTime['minute'], 0, $n_month, $arrTime['day'], $n_year);
    return $nextTime <=time()?0:$nextTime;
}

function todayNextRun($arrTime, $hour = -2, $minute = -2) {
    $hour = $hour == -2 ? date('H', time()) : $hour;
    $minute = $minute == -2 ? date('i', time()) : $minute;
    $nextTime = array();
    if($arrTime['hour'] == -1 && !$arrTime['minute']) {
        $nextTime['hour'] = $hour;
        $nextTime['minute'] = $minute + 1;
    } elseif($arrTime['hour'] == -1 && $arrTime['minute'] != '') {
        $nextTime['hour'] = $hour;
        if(($nextMinute = nextMinute($arrTime['minute'], $minute)) === false) {
            ++$nextTime['hour'];
            $nextMinute = $arrTime['minute'][0];
        }
        $nextTime['minute'] = $nextMinute;
    } elseif($arrTime['hour'] != -1 && $arrTime['minute'] == '') {
        if($arrTime['hour'] < $hour) {
            $nextTime['hour'] = $nextTime['minute'] = -1;
        } elseif($arrTime['hour'] == $hour) {
            $nextTime['hour'] = $arrTime['hour'];
            $nextTime['minute'] = $minute + 1;
        } else {
            $nextTime['hour'] = $arrTime['hour'];
            $nextTime['minute'] = 0;
        }
    } elseif($arrTime['hour'] != -1 && $arrTime['minute'] != '') {
        $nextMinute = nextMinute($arrTime['minute'], $minute);
        if($arrTime['hour'] < $hour || ($arrTime['hour'] == $hour && $nextMinute === false)) {
            $nextTime['hour'] = -1;
            $nextTime['minute'] = -1;
        } else {
            $nextTime['hour'] = $arrTime['hour'];
            $nextTime['minute'] = $nextMinute;
        }
    }
    return $nextTime;
}

function nextMinute($nextMinutes, $minuteNow) {
    foreach($nextMinutes as $nextMinute) {
        if($nextMinute > $minuteNow) {
            return $nextMinute;
        }
    }
    return false;
}

/**
 * 判断是否为银行卡
 * @param $card
 * @return bool
 */
function isBankCard($card)
{
    $card = str_replace('.','',$card);
    $card = strrev($card);
    if(!is_numeric($card) || strlen($card) < 10)
    {
        return false;
    }
    $ood = $even = 0;
    for($i=1;$i<strlen($card);$i++)
    {
        if ($i%2==0){
            $ood+=$card[$i];
        }else{
            $even+=($card[$i]*2)>9?($card[$i]*2)-9:($card[$i]*2);
        }
    }
    return ($ood+$even+$card[0])%10==0?true:false;
}
/**
 * 区分大小写的文件存在判断
 * @param string $filename 文件地址
 * @return boolean
 */
function file_exists_case($filename) {
    if (is_file($filename)) {
        if (APP_DEBUG) {
            if (basename(realpath($filename)) != basename($filename))
                return false;
        }
        return true;
    }
    return false;
}
/**
 * 优化的require_once
 * @param string $filename 文件地址
 * @return boolean
 */
function require_cache($filename) {
    static $_importFiles = array();
    if (!isset($_importFiles[$filename])) {
        if (file_exists_case($filename)) {
            require $filename;
            $_importFiles[$filename] = true;
        } else {
            $_importFiles[$filename] = false;
        }
    }
    return $_importFiles[$filename];
}
/**
 * 导入所需的类库 同java的Import 本函数有缓存功能
 * @param string $class 类库命名空间字符串
 * @param string $baseUrl 起始路径
 * @param string $ext 导入的文件扩展名
 * @return boolean
 */
function import($class,$baseUrl='',$ext=CLASS_EXT) {
    static $_file = array();
    $class = str_replace(array('.', '#'), array('/', '.'), $class);
    if (isset($_file[$class . $baseUrl]))
    {return true;}
    else
    {$_file[$class . $baseUrl] = true;}
    $class_strut = explode('/', $class);
    if (empty($baseUrl)) {
        if ('@' == $class_strut[0] || __CONTROL__ == $class_strut[0]) {
            $baseUrl = __CONTROL__;//加载当前模块的类库
            $class = substr_replace($class, '', 0, strlen($class_strut[0]) + 1);
        }else { // 加载其他模块的类库
            $baseUrl = APP_PATH;
        }
    }
    if (substr($baseUrl, -1) != '/')
    {$baseUrl .= '/';}
    $classfile = $baseUrl . $class . $ext;
    if (!class_exists(basename($class),false)) {
        // 如果类不存在 则导入类库文件
        return require_cache($classfile);
    }
    return false;
}

function outExcel($array,$fileName='',$fileType='xls'){
    if(!$fileName){
        $fileName = date('Y-m-d',time());
    }
    $fileName.='.'.$fileType;
    $data = '';
    $data_header = array();
    import("PHPExcel.PHPExcel",FRAME_PATH.'Extend/','.php');

    // Create new PHPExcel object
    $objPHPExcel = new PHPExcel();

    // Set properties
    $objPHPExcel->getProperties()->setCreator("jinfax")
        ->setLastModifiedBy("jinfax")
        ->setTitle($fileName);
    $numToEng = "A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z,AA,AB,AC,AD,AE,AF,AG,AH,AI,AJ,AK,AL,AM,AN,AO,AP,AQ,AR,AS,AT,AU,AV,AW,AX,AY,AZ";
    $numToEng = explode(',',$numToEng);
    // set table  工作簿
    $objPHPExcel->setActiveSheetIndex(0);
    if(is_array($array)){
        if (isset ($array [0])) {
            $i = 0 ;
            //设置标头
            foreach($array [0] as $key => $value){
                $objPHPExcel->getActiveSheet()->setCellValue($numToEng[$i].'1',$key);
                $i++;
            }
            $data .= implode(',',$data_header)."\r\n";
        }

        //填充内容
        for($i = 0; $i < count($array); $i++){
            $k = 0;
            foreach($array[$i] as $value){
                $value = str_replace('\'','',$value);
                $objPHPExcel->getActiveSheet()->setCellValueExplicit($numToEng[$k].($i+2),$value,PHPExcel_Cell_DataType::TYPE_STRING);
                $k++;
            }
        }
    }
    header("Content-type:application/vnd.ms-excel");
    header("Content-Disposition:attachment;filename=".$fileName);
    header('Cache-Control: max-age=0');
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
    $objWriter->save('php://output');
    echo iconv('UTF-8','GBK//IGNORE',$data);
    die();
}
function log_result($file, $word)
{
    $fp = fopen($file, "a");
    flock($fp, LOCK_EX);
    fwrite($fp, "执行日期：" . strftime("%Y-%m-%d-%H：%M：%S", time()) . "\n" . $word . "\n\n");
    flock($fp, LOCK_UN);
    fclose($fp);
}
