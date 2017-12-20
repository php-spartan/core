<?php
namespace Spartan\Core;

defined('APP_PATH') OR die('404 Not Found');
/**
 * @description
 * @author singer
 * @version v1
 * @date 14-5-24 下午12:03
 */

abstract class Controller{
    public $sessionId = '';
    public $expire = '';
    private $cachePath = '/Runtime/Cache/';
    protected $tVar  = array();// 模板输出变量
    /**
     * 构造有函数时，启动session
     * @param $startSession
     */
    public function __construct($startSession=true){
        (!IS_CLI && $startSession) && $this->startSession();
    }
    /**
     * 返回URL的第几个
     * @param int $number
     * @param string $default
     * @return string
     */
    public function getUrl($number=0,$default=''){
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
     * @return mixed
     */
    public function get($name=''){
        if('' === $name) {
            return $this->tVar;
        }
        return isset($this->tVar[$name])?$this->tVar[$name]:'';
    }
    /**
     * 加载模板和页面输出 可以返回输出内容
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $charset 模板输出字符集
     * @param string $content 模板输出内容
     * @return void
     */
    public function display($templateFile='',$charset='',$content='') {
        $content = $this->fetch($templateFile,$content);//解析并获取模板内容
        $this->render($content,$charset);//输出模板内容
    }

    /**
     * 若是cookie已经存在则以它为session的id
     */
    private function startSession(){
        $arr = Array(
            'name' => C('SESSION.VAR_ID'),
            'expire' => C('SESSION.EXPIRE'),
            'domain' => '.' . DOMAIN,
        );
        $this->sessionId && $arr['id'] = $this->sessionId;
        $this->expire && $arr['expire'] = $this->expire;
        session($arr);
    }
    /**
     * 输出内容文本可以包括Html
     * @access private
     * @param string $content 输出内容
     * @param string $charset 模板输出字符集
     * @return void
     */
    private function render($content,$charset=''){
        if(empty($charset)){$charset = C('DEFAULT_CHARSET');}
        header('Content-Type:text/html; charset='.$charset);//网页字符编码
        header('Cache-control: '.C('HTTP_CACHE_CONTROL'));//页面缓存控制
        header('X-Powered-By:SpartanFramework');
        echo $content;//输出模板文件
    }
    /**
     * 解析和获取模板内容 用于输出
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $content 模板输出内容
     * @return string
     */
    private function fetch($templateFile='',$content='') {
        if(empty($content))
        {
            $templateFile = $this->parseTemplate($templateFile);
            if(!is_file($templateFile)){//模板文件不存在直接返回
                \St::halt($templateFile);
            }
        }
        ob_start();//页面缓存
        ob_implicit_flush(0);//视图解析标签
        // 编译并加载模板文件
        if((!empty($content)&&$this->checkContentCache($content))
            ||$this->checkCache($templateFile))
        {//缓存有效,载入模版缓存文件
            File::instance()->load(
                APP_BASE.__APP_NAME__.$this->cachePath.md5($content?$content:$templateFile).'.php',
                $this->tVar
            );
        }else{
            Template::instance()->fetch($content?$content:$templateFile,$this->tVar);
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
        $module   =  __CONTROL__;// 获取当前模块
        if(strpos($template,'@')){ //跨模块调用模版文件
            $arr = explode('@',$template);
            $module = ucfirst($arr[0]);
            unset($arr[0]);
            $template = implode(NS,$arr);
        }else{
            $template = $template?$template:strtolower(__ACTION__);
        }
        return APP_BASE.__APP_NAME__.'/View/'.$module.'/'.$template.C('TMPL_TEMPLATE_SUFFIX');
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
    public function ajaxMessage($message,$id=0){
        if(is_array($message)){
            $arr = Array('status'=>$message[1],'info'=>$message[0],'id'=>$id);
        }else{
            $arr = Array('status'=>$id,'info'=>$message);
        }
        $this->ajaxReturn($arr);
        die();
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
            case 'XML'  :
                // 返回xml格式数据
                header('Content-Type:text/xml; charset=utf-8');
                exit(xml_encode($data));
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($data);
            case 'JS':
                // 返回可执行的js脚本
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
     * @param string $tmplTemplateFile  模板文件名
     * @return boolean
     */
    private function checkCache($tmplTemplateFile) {
        if (!C('TMPL_CACHE_ON')||APP_DEBUG){return false;} // 优先对配置设定检测
        $tmplCacheFile = APP_BASE.__APP_NAME__.$this->cachePath.md5($tmplTemplateFile).'.php';
        if(!File::instance()->has($tmplCacheFile)){
            return false;
        }elseif (filemtime($tmplTemplateFile)>File::instance()->get($tmplCacheFile,'mtime')){// 模板文件如果有更新则缓存需要更新
            return false;
        }elseif (C('TMPL_CACHE_TIME') != 0 && time() > File::instance()->get($tmplCacheFile,'mtime')+C('TMPL_CACHE_TIME')) {// 缓存是否在有效期
            return false;
        }
        return true;// 缓存有效
    }
    /**
     * 检查缓存内容是否有效
     * 如果无效则需要重新编译
     * @param string $tmplContent  模板内容
     * @return boolean
     */
    private function checkContentCache($tmplContent) {
        if(File::instance()->has(APP_BASE.__APP_NAME__.$this->cachePath.md5($tmplContent).'.php')){
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
