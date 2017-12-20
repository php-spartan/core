<?php
namespace Spartan\Core;
defined('APP_PATH') OR die('404 Not Found');

/**
    * @description 服务基础类
    * @author singer
    * @version v1
    * @date 17-3-28 上午10:00
    * @access 只能继承
*/

abstract class Server {
    protected $nameSpace = 'Rpc';//工作区域
    protected $serverName = '';
    protected $serverIP = '';
    protected $serverPort = '';
    /** @var $server \swoole_websocket_server */
    protected $server = null;
    protected $serverConfig = Array();
    protected $serverSetting = Array();
    protected $serverHandle = Array(
        'Start'=>'onStart',
        'Close'=>'onClose',
        'WorkerStart'=>'onWorkerStart',
        'Task'=>'onTask',
        'Finish'=>'onFinish',
        'WorkerError'=>'onWorkerError',
        'ManagerStart'=>'onManagerStart',
        'ManagerStop'=>'onManagerStop',
    );

	public function __construct(){
        if ( !extension_loaded('swoole') || !class_exists('\swoole_server',false) ) {
            \St::log('please install swoole extension.',true);
        }
	}

    /**
     * @description 启动服务发现服务
     */
    public function discovery(){}

    /**
     * @description 服务开始之前做的准备工作
     */
    public function beforeStart(){}

    /**
     * @description 服务开始之后的准备工作
     * @param $worker_id
     */
    public function afterStart($worker_id){

    }
    /**
     * @description 一定要实现的任务类，是某一个任务进程的初始化，比如加载所有的类
     * @param $server
     * @param $worker_id
     */
    public function initTask($server, $worker_id){}

    /**
     * @description 检测是否可以被连接
     * @param $intClientId
     * @param $arrClientInfo
     */
    public function checkAccess($intClientId,$arrClientInfo){

    }
    /**
     * @description 外发任务处理接口
     * @param $data
     * @return array
     */
    public function doTaskWork($data){

        return $data;
    }

    /**
     * @description 外发的任务完成的通知
     * @param \swoole_server $server
     * @param $task_id
     * @param $data
     */
    public function finish(\swoole_server $server, $task_id, $data){

    }

    /**
     * @description 外发的可自定义的进程错误类
     * @param \swoole_server $server
     * @param $worker_id
     * @param $worker_pid
     * @param $exit_code
     */
    public function workerError(\swoole_server $server, $worker_id, $worker_pid, $exit_code){
        print_r($this->serverName." workererror", Array( $server, $worker_id, $worker_pid,$exit_code));
    }

    /**
     * @param \swoole_server $server
     * @param $fd
     * @param $reactorId
     */
    public function closeFun(\swoole_server $server,$fd,$reactorId){

    }
    /**
     * 给指定的客户端发送信息
     * @param $fd
     * @param $msg
     * @return bool
     */
    public function sendData($fd,$msg){
        is_array($msg) && $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
        return $this->server->send($fd,$msg);
    }

    public function sendTask($arrData,$dst_worker_id = -1){
        return $this->server->task($arrData,$dst_worker_id);
    }

    public function close($fd){
        $this->server->close($fd);
    }

    /**
     * @description 初始化服务器
     */
    final function initServer(){
        foreach ($this->serverConfig as &$v){
            $v = str_replace('{swoole}',$this->serverName,$v);
        }
        unset($v);
        foreach ($this->serverSetting as &$v){
            $v = str_replace('{swoole}',$this->serverName,$v);
        }
        unset($v);
        if (!in_array($this->serverSetting['CLASS_NAME'],['\swoole_websocket_server','\swoole_http_server','\swoole_server'])){
            \St::log('The server class '. $this->serverSetting['CLASS_NAME'] .' not exits.',true);
        }
        if (!isset($this->serverSetting['HOST']) || !isset($this->serverSetting['PORT'])){
            \St::log('RPC server config err : HOST or IP is lost.',true);
        }
        $this->server = new $this->serverSetting['CLASS_NAME']($this->serverSetting['HOST'],$this->serverSetting['PORT']);
        $this->server->set($this->serverConfig);
        if (isset($this->serverConfig['DISCOVERY']) && $this->serverConfig['DISCOVERY'] == true){
            $this->discovery();
        }
    }

    /**
     * @description 启动服务
     * @param $handle array
     */
    final function start($handle=[]){
        $this->serverHandle = array_merge($this->serverHandle,$handle);
        foreach ($this->serverHandle as $k=>$v){
            $this->server->on($k,Array($this,$v));
        }
        $this->serverIP = $this->getLocalIp();
        $this->serverPort = $this->serverSetting['PORT'];
        $this->beforeStart();
        $this->server->start();
        \St::log('if you see this,so swoole start fail.please check port is busy or not.');
    }

    /**
     * @description 服务成功运行之后
     * @param \swoole_server $server
     */
    final function onStart(\swoole_server $server){
        swoole_set_process_name($this->serverName . ":master");
        \St::log("MasterPid={$server->master_pid},".
                "ManagerPid={$server->manager_pid}.\n".
                "Server: start.Swoole version is [" . SWOOLE_VERSION . "],".
                "IP: {$this->serverIP}[{$this->serverSetting['HOST']}]:{$this->serverPort}"
        );
        file_put_contents($this->serverSetting["MASTER_PID"],$server->master_pid);
        file_put_contents($this->serverSetting["MANAGER_PID"],$server->manager_pid);
    }

    /**
     * @description 服务的管理进程运行之后
     * @param \swoole_server $server
     */
    final function onManagerStart(\swoole_server $server){
        swoole_set_process_name($this->serverName . ": manager");
    }

    /**
     * @description 服务的管理进程结束
     * @param \swoole_server $server
     */
    final public function onManagerStop(\swoole_server $server){
        \St::log($this->serverName . ':manager Stop,shutdown server.');
        $server->shutdown();
    }

    /**
     * @description 工作进程的重命名
     * @param \swoole_server $server
     */
    final function onWorkerStart($server, $worker_id){
        $isTask = $server->taskworker;
        if (!$isTask){//worker
            swoole_set_process_name($this->serverName . ": worker {$worker_id}");
            $this->afterStart($worker_id);
        }else{//task
            swoole_set_process_name($this->serverName . ": task {$worker_id}");
            $this->initTask($server, $worker_id);
        }
    }

    /**
     * @description 任务进程里的错误，还没有做日志管理
     * @param \swoole_server $server
     * @param $worker_id
     * @param $worker_pid
     * @param $exit_code
     */
    final function onWorkerError(\swoole_server $server, $worker_id, $worker_pid, $exit_code){
        $this->workerError($server, $worker_id, $worker_pid, $exit_code);
    }

    /**
     * @description 任务发放动作
     * @param \swoole_server $server
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed
     */
    final function onTask(\swoole_server $server, $task_id, $from_id, $data){
        try {
            $data["result"] = Array("send task success",1,$this->doTaskWork($data));
        } catch (\Exception $e) {
            $data["result"] = Array($e->getMessage(),$e->getCode(),[]);
        }
        return $data;
    }

    /**
     * @description 任务完成的通知
     * @param \swoole_server $server
     * @param $task_id
     * @param $data
     */
    final public function onFinish(\swoole_server $server, $task_id, $data){
        $this->finish($server, $task_id, $data);
    }

    /**
     * @description 服务器关闭的通知
     * @param \swoole_server $server
     * @param $fd
     * @param $reactorId
     */
    final public function onClose(\swoole_server $server, $fd, $reactorId){
        $this->closeFun($server,$fd, $reactorId);
    }
    /**
     * @description 处理任务的动作，可重写
     * @param $param
     * @return array|string
     */
    final function runMvc($param){
        $arrUrl = isset($param['api'])?$param['api']:[];
        if (!$arrUrl || !is_array($arrUrl)){
            return Array('request url lost.',0);
        }
        unset($param['api']);
        $arrParam = $arrUrl[1];
        $arrParam['_url_'] = $arrUrl[0];
        $arrUrl = array_map(function($v){return ucfirst($v);},array_filter(explode('/',$arrUrl[0])));
        $strAction = array_pop($arrUrl);
        $strModule = $this->nameSpace.'\\Logic'.'\\'.implode('\\',$arrUrl);
        $objModule = class_exists($strModule)?new $strModule($arrParam):null;
        if (!$objModule || !is_object($objModule)){
            return Array("Url:{$arrParam['_url_']},class not exist:{$strModule}");
        }
        if (!method_exists($objModule,$strAction)){
            return Array("Url:{$arrParam['_url_']},class:{$strModule},method not exist:{$strAction}.");
        }
        return $objModule->{$strAction}($arrParam);
    }

    /**
     * @description获取当前服务器ip
     * @return string
     */
    final function getLocalIp(){
        static $currentIP = '';
        if (!$currentIP) {
            $serverIps = \swoole_get_local_ip();
            $patternArray = array(
                '10\.',
                '172\.1[6-9]\.',
                '172\.2[0-9]\.',
                '172\.31\.',
                '192\.168\.'
            );
            foreach ($serverIps as $serverIp){// 匹配内网IP
                if (preg_match('#^' . implode('|', $patternArray) . '#', $serverIp)) {
                    $currentIP = $serverIp;
                    return $currentIP;
                }
            }
            return '';
        }
        return $currentIP;
    }

    /**
     * @description 任务结束类
     */
    final function __destruct(){
        if (!$this->server){return;}
        \St::log("Server Was Shutdown...");
        $this->server->shutdown();
    }
} 