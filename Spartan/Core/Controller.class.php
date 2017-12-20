<?php
namespace Spartan\Core;
use Spartan\Driver\Controller\File;
use Spartan\Driver\Controller\Template;

defined('APP_PATH') OR die('404 Not Found');
/**
 * @description
 * @author singer
 * @version v1
 * @date 14-5-24 下午12:03
 */

class Controller{
    public $sessionId = '';//自定义session_id
    public $sessionExpire = 0;//session的有效期，0为默认
    public $cachePath = 'Runtime/Cache/';//模板缓存位置
    protected $tVar  = Array();// 模板输出变量

    /**
     * 构造函数，启动session
     * @param $startSession
     */
    public function __construct($startSession = true){
        (!IS_CLI && $startSession) && $this->startSession();
    }

    /**
     * 启动session，并设计id
     */
    public function startSession(){
        $arrConfig = Array(
            'name' => C('SESSION.VAR_ID'),
            'expire' => C('SESSION.EXPIRE'),
            'domain' => '.' . DOMAIN,
        );
        $this->sessionId && $arrConfig['id'] = $this->sessionId;
        $this->sessionExpire && $arrConfig['expire'] = $this->sessionExpire;
        session($arrConfig);
    }

    /**
     * 返回URL的第几个
     * @param int $number
     * @param string $default
     * @return string
     */
    public function getUrl($number = 0,$default = ''){
        if (!defined('__URL__')){return $default;}
        $arrUrl = explode('/',__URL__);
        return isset($arrUrl[$number])?$arrUrl[$number]:$default;
    }

    /**
     * 模板变量赋值
     * @access public
     * @param mixed $name
     * @param mixed $value
     */
    public function assign($name,$value=''){
        if(is_array($name)) {
            $this->tVar = array_merge($this->tVar,$name);
        }else {
            $this->tVar[$name] = $value;
        }
    }

    /**
     * 取得模板变量的值
     * @access public
     * @param string $name
     * @param string $default
     * @return mixed
     */
    public function get($name = '',$default = ''){
        if($name === '') {
            return $this->tVar;
        }
        return isset($this->tVar[$name])?$this->tVar[$name]:$default;
    }

    /**
     * 加载模板和页面输出 可以返回输出内容
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $charset 模板输出字符集
     * @param string $content 模板输出内容
     * @return void
     */
    public function display($templateFile = '',$charset = '',$content = '') {
        $content = $this->fetch($templateFile,$content);//解析并获取模板内容
        //输出内容文本可以包括Html
        !$charset && $charset = C('DEFAULT_CHARSET','utf-8');
        header('Content-Type:text/html; charset='.$charset);//网页字符编码
        header('Cache-control: '.C('HTTP_CACHE_CONTROL'));//页面缓存控制
        header('X-Powered-By:Spartan Framework');
        echo $content;//输出模板文件
    }

    /**
     * 解析和获取模板内容 用于输出
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $content 模板输出内容
     * @return string
     */
    private function fetch($templateFile = '',$content = '') {
        if(!$content) {
            $templateFile = $this->parseTemplate($templateFile);
            if(!is_file($templateFile)){//模板文件不存在直接返回
                \Spt::halt($templateFile,'template file not exiting');
            }
        }
        ob_start();//页面缓存
        ob_implicit_flush(0);//视图解析标签
        // 编译并加载模板文件，缓存有效,载入模版缓存文件
        if((!empty($content)&&$this->checkContentCache($content)) || $this->checkCache($templateFile)){
            File::instance()->load(
                APP_PATH.$this->cachePath.md5($content?$content:$templateFile).'.php',
                $this->tVar
            );
        }else{
            Template::instance(['CACHE_PATH'=>$this->cachePath])->fetch(
                $content?$content:$templateFile,$this->tVar
            );
        }
        $content = ob_get_clean();//获取并清空缓存
        return $content;//输出模板文件
    }

    /**
     * 自动定位模板文件
     * @access protected
     * @param string $template 模板文件规则
     * @return string
     */
    private function parseTemplate($template='') {
        if(is_file($template)) {
            return $template;
        }
        $strModule = __CONTROL__;// 获取当前模块
        if(strpos($template,'@')){ //跨模块调用模版文件
            $arrTmp = explode('@',$template);
            $strModule = ucfirst($arrTmp[0]);
            unset($arrTmp[0]);
            $template = implode(NS,$arrTmp);
        }else{
            $template = $template?$template:strtolower(__ACTION__);
        }
        return APP_PATH.'View/'.$strModule.'/'.$template.C('TMPL_TEMPLATE_SUFFIX');
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param string $message 错误信息
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @return void
     */
    protected function error($message='',$jumpUrl='',$ajax=false) {
        $this->dispatchJump($message,0,$jumpUrl,$ajax);
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param string $message 提示信息
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @return void
     */
    protected function success($message='',$jumpUrl='',$ajax=false) {
        $this->dispatchJump($message,1,$jumpUrl,$ajax);
    }

    /**
     * @param $message
     * @param int $id
     */
    public function ajaxMessage($message,$id = 0){
        if(is_array($message)){
            $arrTmp = Array('status'=>$message[1],'info'=>$message[0]);
        }else{
            $arrTmp = Array('status'=>$id,'info'=>$message);
        }
        $this->ajaxReturn($arrTmp);
        exit(0);
    }

    /**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type AJAX返回数据格式
     * @return void
     */
    protected function ajaxReturn($data,$type='JSON'){
        switch (strtoupper($type)){
            case 'XML'  :// 返回xml格式数据
                header('Content-Type:text/xml; charset=utf-8');
                exit(xml_encode($data));
            case 'EVAL' :// 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($data);
            case 'JS':// 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                echo '<script language="javascript">';
                echo $data;
                exit('</script>');
            default :// 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($data,JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 默认跳转操作 支持错误导向和正确跳转
     * 调用模板显示 默认为public目录下面的success页面
     * 提示页面为可配置 支持模板标签
     * @param string $message 提示信息
     * @param int $status 状态
     * @param string $jumpUrl 页面跳转地址
     * @param mixed $ajax 是否为Ajax方式 当数字时指定跳转时间
     * @return void
     */
    private function dispatchJump($message,$status=1,$jumpUrl='',$ajax=false) {
        if(true === $ajax || isAjax()) {// AJAX提交
            $data           =   is_array($ajax)?$ajax:array();
            $data['info']   =   $message;
            $data['status'] =   $status;
            $data['url']    =   $jumpUrl;
            $this->ajaxReturn($data);
        }
        if(is_int($ajax)){$this->assign('waitSecond',$ajax);}
        if(!empty($jumpUrl)){$this->assign('jumpUrl',$jumpUrl);}
        // 提示标题
        $this->assign('msgTitle',$status?'操作成功':'操作失败');
        //如果设置了关闭窗口，则提示完毕后自动关闭窗口
        if($this->get('closeWin')){$this->assign('jumpUrl','javascript:window.close();');}
        $this->assign('status',$status);   // 状态
        //保证输出不受静态缓存影响
        if($status) { //发送成功信息
            $this->assign('message',$message);// 提示信息
            // 成功操作后默认停留1秒
            if(!$this->get('waitSecond')){$this->assign('waitSecond','1');}
            // 默认操作成功自动返回操作前页面
            if(!isset($jumpUrl)) $this->assign("jumpUrl",$_SERVER["HTTP_REFERER"]);
            $this->display(C('TMPL_ACTION_SUCCESS'));
        }else{
            $this->assign('error',$message);// 提示信息
            //发生错误时候默认停留3秒
            if(!$this->get('waitSecond')){$this->assign('waitSecond','3');}
            // 默认发生错误的话自动返回上页
            if(!$jumpUrl) $this->assign('jumpUrl',"javascript:history.back(-1);");
            $this->display(C('TMPL_ACTION_ERROR'));
        }
        exit();
    }

    /**
     * 检查缓存文件是否有效
     * 如果无效则需要重新编译
     * @param string $tmpTemplateFile  模板文件名
     * @return boolean
     */
    private function checkCache($tmpTemplateFile) {
        if (!C('TMPL_CACHE_ON') || \Spt::$arrConfig['DEBUG']){return false;} //优先对配置设定检测
        $tmpCacheFile = APP_PATH.$this->cachePath.md5($tmpTemplateFile).'.php';
        if(!File::instance()->has($tmpCacheFile)){
            return false;
        }elseif (filemtime($tmpTemplateFile)>File::instance()->get($tmpCacheFile,'mtime')){// 模板文件如果有更新则缓存需要更新
            return false;
        }elseif (C('TMPL_CACHE_TIME') != 0 && time() > File::instance()->get($tmpCacheFile,'mtime')+C('TMPL_CACHE_TIME')) {// 缓存是否在有效期
            return false;
        }
        return true;// 缓存有效
    }

    /**
     * 检查缓存内容是否有效
     * 如果无效则需要重新编译
     * @param string $tmpContent  模板内容
     * @return boolean
     */
    private function checkContentCache($tmpContent) {
        if(File::instance()->has(APP_PATH.$this->cachePath.md5($tmpContent).'.php')){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @return null|\Spartan\Driver\Db\Mysql|\Spartan\Driver\Db\Pgsql;
     */
    public function Db(){
        static $dbInstance = null;
        !$dbInstance && $dbInstance = Db::instance();
        return $dbInstance;
    }
}
