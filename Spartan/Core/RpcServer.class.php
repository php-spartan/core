<?php
namespace Spartan\Core;
defined('APP_PATH') OR die('404 Not Found');

/**
    * @description Rpc服务器
    * @author singer
    * @version v1
    * @date 17-2-28 上午10:00
    * @access 只能继承
*/

abstract class RpcServer {
    protected $_ver = '1.1';//当前RPC的版本。
    protected $nameSpace = 'Rpc';//工作区域
    protected $serverName = '';
    protected $serverIP = '';
    protected $serverPort = '';
    /** @var $tcpServer \swoole_server */
    protected $tcpServer = null;
    protected $taskInfo = Array();
    protected $tcpConfig = Array();
    protected $tcpSetting = Array();
    protected $tcpHandle = Array(
        'Start'=>'onStart',
        'WorkerStart'=>'onWorkerStart',
        'Receive'=>'onReceive',
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

    final public function initServer(){
        foreach ($this->tcpConfig as &$v){
            $v = str_replace('{swoole}',$this->serverName,$v);
        }
        unset($v);
        foreach ($this->tcpSetting as &$v){
            $v = str_replace('{swoole}',$this->serverName,$v);
        }
        unset($v);
        if (!isset($this->tcpSetting['HOST']) || !isset($this->tcpSetting['PORT'])){
            \St::log('RPC server config err : HOST or IP is lost.',true);
        }
        $this->tcpServer = new \swoole_server($this->tcpSetting['HOST'],$this->tcpSetting['PORT']);
        $this->tcpServer->set($this->tcpConfig);
        if (isset($this->tcpConfig['DISCOVERY']) && $this->tcpConfig['DISCOVERY'] == true){
            $this->discovery();
        }
    }
	/*
	 * RPC 的访问IP检测。
	 * @param $requestInfo array
	 */
	private function checkAccess($requestInfo,$fd){
        $arrClientInfo = $this->tcpServer->connection_info($fd);
        if(!$arrClientInfo || !isset($arrClientInfo['remote_ip'])){
            return 'The client info err.';
        }
        $arrAllowIp = C('ALLOW_IP');
        if ($arrClientInfo['remote_ip'] != $this->tcpConfig['HOST'] &&
            (!$arrAllowIp && !in_array($arrAllowIp,$arrClientInfo['remote_ip']))
        ){
            return "The client ip : {$arrClientInfo['remote_ip']} not allow connect list.";
        }
        if (!isset($requestInfo['auth']['user']) || !isset($requestInfo['auth']['pass'])){
            return 'The client userName or passWord is lost..';
        }
        if ($requestInfo['access']['user'] != $this->tcpConfig['HOST'] ||
            $requestInfo['access']['pass'] != $this->tcpConfig['PASS']
        ){
            return 'User name and password authentication failed.';
        }
        if (!is_array($requestInfo["api"]) && count($requestInfo["api"]) == 0){
            return 'param api is empty.';
        }
        return 'OK';
	}

    /**
     * 启动服务发现服务
     */
    public function discovery(){

    }

    //invoke the start
    public function beforeStart(){}

    /**
     * Start Server.
     * @param $handle array
     */
    final public function start($handle=[]){
        $this->tcpHandle = array_merge($this->tcpHandle,$handle);
        foreach ($this->tcpHandle as $k=>$v){
            $this->tcpServer->on($k,Array($this,$v));
        }
        $this->serverIP = $this->getLocalIp();
        $this->serverPort = $this->tcpSetting['PORT'];
        $this->beforeStart();
        $this->tcpServer->start();
        \St::log('if you see this,so swoole start fail.please check port is busy or not.');
    }

    final public function onStart(\swoole_server $server){
        swoole_set_process_name($this->serverName . ": master");
        \St::log("MasterPid={$server->master_pid},".
                "ManagerPid={$server->manager_pid}.\n".
                "Server: start.Swoole version is [" . SWOOLE_VERSION . "],".
                "IP: {$this->serverIP}:{$this->serverPort}"
        );
        file_put_contents($this->tcpSetting["MASTER_PID"],$server->master_pid);
        file_put_contents($this->tcpSetting["MANAGER_PID"],$server->manager_pid);
    }

    final public function onManagerStart(\swoole_server $server){
        swoole_set_process_name($this->serverName . ": manager");
    }

    final public function onManagerStop(\swoole_server $server){
        \St::log($this->serverName . ':manager Stop,shutdown server.');
        $server->shutdown();
    }

    final public function onWorkerStart($server, $worker_id){
        $isTask = $server->taskworker;
        if (!$isTask){//worker
            swoole_set_process_name($this->serverName . ": worker {$worker_id}");
        }else{//task
            swoole_set_process_name($this->serverName . ": task {$worker_id}");
            $this->initTask($server, $worker_id);
        }
    }

    abstract public function initTask($server, $worker_id);

    final public function onWorkerError(\swoole_server $server, $worker_id, $worker_pid, $exit_code){
        //using the swoole error log output the error this will output to the swtmp log
        var_dump($this->serverName." workererror", Array($this->taskInfo, $server, $worker_id, $worker_pid,$exit_code));
    }

    /**
     * @description tcp 的请求进程
     * @param \swoole_server $server
     * @param $fd
     * @param $from_id
     * @param $data
     * @return bool
     */
    final public function onReceive(\swoole_server $server, $fd, $from_id, $data){
        $requestInfo = packDecode($data);
        if ($requestInfo["code"] != 0) {#decode error
            $server->send($fd, packEncode($requestInfo));
            return true;
        } else {
            $requestInfo = $requestInfo["data"];
        }
        $strAccessInfo = $this->checkAccess($requestInfo,$fd);
        unset($requestInfo['auth']);
        if ($strAccessInfo !== 'OK'){
            $pack = packFormat($requestInfo["guid"], $strAccessInfo, 100003);
            $server->send($fd, packEncode($pack));
            return true;
        }
        $arrTask = array(//prepare the task parameter
            "type" => $requestInfo["type"],
            "guid" => $requestInfo["guid"],
            "fd" => $fd,
        );
        switch ($requestInfo["type"]) {
            case 1://SW_MODE_WAITRESULT:同步
                foreach ($requestInfo["api"] as $k=>$v) {
                    $arrTask["api"] = $v;//Array(url,param);
                    $intTaskId = $server->task($arrTask);
                    $this->taskInfo[$fd][$arrTask["guid"]]['task_key'][$intTaskId] = $k;
                }
                break;
            case 2://SW_MODE_NORESULT 异步不要结果
                foreach ($requestInfo["api"] as $v) {
                    $arrTask["api"] = $v;//Array(url,param);
                    $server->task($arrTask);
                }
                $pack = packFormat($arrTask["guid"], "transfer success.已经成功投递", 100001);
                $pack["guid"] = $arrTask["guid"];
                $server->send($fd, packEncode(packFormat($arrTask["guid"],'OK',0,$pack)));
                break;
            case 3://SW_MODE_ASYNCRESULT 异步获取结果
                foreach ($requestInfo["api"] as $k=>$v) {
                    $arrTask["api"] = $v;//Array(url,param);
                    $intTaskId = $server->task($arrTask);
                    $this->taskInfo[$fd][$arrTask["guid"]]['task_key'][$intTaskId] = $k;
                }
                $pack = packFormat($arrTask["guid"], "transfer async success.已经成功投递", 100001);
                $pack["guid"] = $arrTask["guid"];
                $server->send($fd, packEncode(packFormat($arrTask["guid"],'OK',0,$pack)));
                break;
            default:
                $server->send($fd, packEncode(packFormat($arrTask["guid"], "unknow task type.未知类型任务", 100002)));
        }
        return true;
    }

    /**
     * @description 任务发放动作
     * @param \swoole_server $server
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed
     */
    final public function onTask(\swoole_server $server, $task_id, $from_id, $data){
        try {
            $data["result"] = packFormat($data["guid"], "OK", 0, $this->doTaskWork($data));
        } catch (\Exception $e) {
            $data["result"] = packFormat($data["guid"], $e->getMessage(), $e->getCode());
        }
        return $data;
    }

    /**
     * @description 处理任务的动作，可重写
     * @param $param
     * @return string
     */
    public function doTaskWork($param){
        $arrUrl = isset($param['api'])?$param['api']:[];
        if (!$arrUrl || !is_array($arrUrl)){
            return 'request url lost.[]';
        }
        unset($param['api']);
        $arrParam = $arrUrl[1];
        $arrParam['_url_'] = $arrUrl[0];
        $arrUrl = array_map(function($v){return ucfirst($v);},array_filter(explode('/',$arrUrl[0])));
        $strAction = array_pop($arrUrl);
        $strModule = $this->nameSpace.'\\Logic'.'\\'.implode('\\',$arrUrl);
        $objModule = class_exists($strModule)?new $strModule():null;
        if (!$objModule || !is_object($objModule)){
            return "Url:{$arrParam['_url_']},class not exist:{$strModule}";
        }
        if (!method_exists($objModule,$strAction)){
            return "Url:{$arrParam['_url_']},class:{$strModule},method not exist:{$strAction}.";
        }
        return $objModule->{$strAction}($arrParam);
    }

    /**
     * @description 任务完成的通知
     * @param \swoole_server $server
     * @param $task_id
     * @param $data
     * @return bool
     */
    final public function onFinish(\swoole_server $server, $task_id, $data){
        $fd = $data["fd"];$guid = $data["guid"];
        if (!isset($this->taskInfo[$fd][$guid]) || !isset($this->taskInfo[$fd][$guid]["task_key"][$task_id])) {
            return true;//if the guid not exists .it's mean the api no need return result
        }
        $key = $this->taskInfo[$fd][$guid]["task_key"][$task_id];
        unset($this->taskInfo[$fd][$guid]["task_key"][$task_id]);
        $this->taskInfo[$fd][$guid]["result"][$key] = $data["result"];
        if (count($this->taskInfo[$fd][$guid]["task_key"]) == 0) {
            $this->taskInfo[$fd][$guid]["result"]["isresult"] = 1;//标识结果
            $packet = packFormat($guid, "OK", 0, $this->taskInfo[$fd][$guid]["result"]);
            $server->send($fd, packEncode($packet));
            //print_r('回发了'.$guid."\r\n");
            //$server->close($fd);
            unset($this->taskInfo[$fd][$guid]);
            return true;
        }else{//waiting other result
            return true;
        }
    }

    /**
     * 获取当前服务器ip
     * @return string
     */
    final public function getLocalIp(){
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

    final public function __destruct(){
        if (!$this->tcpServer){return;}
        \St::log("Server Was Shutdown...");
        $this->tcpServer->shutdown();
    }
} 