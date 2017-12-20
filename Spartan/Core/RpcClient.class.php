<?php
namespace Spartan\Core;
defined('APP_PATH') OR die('404 Not Found');
/**
    * @description PRC请求类
    * @author singer
    * @version v1
    * @date 17-2-28 上午10:00
*/
class RpcClient {
    /** @var array \swoole_client */
    private static $arrClient = Array();//client obj pool
    private static $arrAsyncList = array();//for the async task list
    private static $arrAsyncResult = array();//for the async task result
    protected $serverConfig = Array();//connect info
    protected $data = Array();//固定发送的数据
    protected $request = Array();//每一次发送的数据，发送后清空。
    protected $sendData = Array();//当次所有发送的数据
    protected $response = Array();//所有的返回内容

    public static function getInstance($config=[]){
        return \St::getInstance('Spartan\\Core\\RpcClient',$config);
    }

    public function __construct($config=[]){
        $this->serverConfig = C('RPC_SERVER');
        $this->serverConfig = array_merge($this->serverConfig,$config);
        if (count($this->serverConfig) == 0 || !isset($this->serverConfig["HOST"]) || !$this->serverConfig["PORT"]) {
            \St::log("cant found config on the RPC server info..",true);
        }
        $this->data = Array(
            'sys'=>Array(
                '_ver_' => '1.1',
                '_ip_' => get_client_ip(),
                '_agent_'=>I('server.HTTP_USER_AGENT','mobile'),
                '_token_'=>session_id(),
                '_uri_'=> urlencode(str_replace('//','/',I('server.HTTP_HOST').'/'.I('server.REQUEST_URI'))),
            ),
            'session'=>session(),
            'auth'=>Array(
                'user'=>$this->serverConfig['USER'],
                'pass'=>$this->serverConfig['PASS'],
            )
        );
    }

    /**
     * @description 收集发送数据。('/index',['name'=>'lang','sex'=>1]);
     * @param $url
     * @param array $data
     * @return $this
     */
    public function setRequest($url,$data=[]){
        if (!is_array($url) && $data){
            $this->request[] = Array($url,$data);
        }else if(is_array($url) && !$data){
            foreach ($url as $v){
                $this->request[] = $v;
            }
        }
        return $this;
    }

    /**
     * @description 发起一个请求
     * @param int $type 默认为1，同步等结果
     * @param int $retry 重试次数
     * @return string 返回此次请求的gid
     */
    public function send($type = 1,$retry = 0){
        $guid = $this->generateGuid();
        $packet = array(
            'api'=>$this->request,
            'guid'=>$guid,
            'type'=>$type,
        );
        $sendData = packEncode(array_merge($packet,$this->data));
        $result = $this->doRequest($sendData, $packet["type"], $guid);
        while ((!isset($result["code"]) || $result["code"] != 0) && $retry > 0) {
            $result = $this->doRequest($sendData, $packet["type"], $guid);
            $retry--;
        }
        $this->sendData[$guid] = $this->request;
        $this->response[$guid] = $result;
        $this->request = [];
        return $guid;
    }

    /**
     * @description 连接，发送，接受数据
     * @param $sendData
     * @param $type
     * @param $guid
     * @return array|mixed|string
     */
    private function doRequest($sendData, $type, $guid){
        try {//get client obj
            $strClientKey = $this->getClientObj();
            $client = self::$arrClient[$strClientKey];
        } catch (\Exception $e) {
            return packFormat($guid, $e->getMessage(), $e->getCode());
        }
        $ret = $client->send($sendData);
        if (!$ret) {
            $errorCode = $client->errCode;
            $client->close(true);
            unset(self::$arrClient[$strClientKey]);
            if ($errorCode == 0) {
                $msg = "connect fail.check host dns.{$strClientKey}.";
                $errorCode = -1;
                $packet = packFormat($guid, $msg, $errorCode);
            } else {
                $msg = \socket_strerror($errorCode);
                $packet = packFormat($guid, $msg, $errorCode);
            }
            return $packet;
        }
        if ($type == 3){
            self::$arrAsyncList[$guid] = $strClientKey;
        }
        while (1) {
            $result = $client->recv();
            if (!($result !== false && $result != "")) {
                return packFormat($guid, "the recive wrong or timeout", 100009);
            }
            $result = packDecode($result,false);
            if(!is_array($result) || !isset($result['guid']) || !$result['guid']){
                return packFormat($guid, "the data decode wrong.", 100010);
            }
            if (max(0,isset($result['data']['isresult'])?$result['data']['isresult']:0) == 1){
                unset($result['data']['isresult']);
                unset(self::$arrAsyncList[$result['guid']]);
                self::$arrAsyncResult[$result['guid']] = $result;
            }
            if ($result['guid'] !== $guid){
                continue;
            }else{
                return $result;
            }
        }
        return 'not data.';
    }

    /**
     * @description 等待所有的异步都返回
     */
    public function waitAsync(){
        while (1) {
            if (count(self::$arrAsyncList) == 0){
                break;
            }
            foreach (self::$arrAsyncList as $k => $strClientKey) {
                if (!self::$arrClient[$strClientKey]->isConnected()) {
                    unset(self::$arrAsyncList[$k]);
                    self::$arrAsyncResult[$k] = packFormat($k, "Get Async Result Fail: Client Closed.", 100012);
                    continue;
                }
                $data = self::$arrClient[$strClientKey]->recv();
                if (!($data !== false && $data != "")) {
                    unset(self::$arrAsyncList[$k]);
                    self::$arrAsyncResult[$k] = packFormat($k, "the recive wrong or timeout", 100009);
                    continue;
                }
                $data = packDecode($data,false);
                if (max(0,isset($data['data']['isresult'])?$data['data']['isresult']:0) == 1){
                    unset($data['data']['isresult']);
                    unset(self::$arrAsyncList[$data['guid']]);
                    self::$arrAsyncResult[$data['guid']] = $data;
                    continue;
                }
            }
        }
    }

    /**
     * @description 得到异步的数据
     * @param string $guid
     * @return array|mixed|string
     */
    public function getAsyncData($guid=''){
        if (self::$arrAsyncList && (!self::$arrAsyncResult || array_diff_key(self::$arrAsyncList,self::$arrAsyncResult))){
            $this->waitAsync();
        }
        if (!$guid){
            return self::$arrAsyncResult;
        }
        return isset(self::$arrAsyncResult[$guid])?self::$arrAsyncResult[$guid]:'';
    }

    /**
     * @description 得取一个请求结果
     * @param string $guid
     * @return array|mixed|string
     */
    public function getResponse($guid=''){
        if (!$guid){
            return $this->response;
        }
        return isset($this->response[$guid])?$this->response[$guid]:'';
    }

    /**
     * @description 得到一个发送的数据
     * @param string $guid
     * @return array|mixed|string
     */
    public function getSendData($guid=''){
        if (!$guid){
            return $this->sendData;
        }
        return isset($this->sendData[$guid])?$this->sendData[$guid]:'';
    }

    //get current client
    private function getClientObj(){
        $clientKey = $this->serverConfig["HOST"]."_".$this->serverConfig["PORT"];//config obj key
        if (!isset(self::$arrClient[$clientKey]) || !self::$arrClient[$clientKey]->isConnected()) {
            self::$arrClient[$clientKey] = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP);
            self::$arrClient[$clientKey]->set(Array(
                'open_length_check' => 1,
                'package_length_type' => 'N',
                'package_length_offset' => 0,
                'package_body_offset' => 4,
                'package_max_length' => 1024 * 1024 * 2,
                'open_tcp_nodelay' => 1,
                'socket_buffer_size' => 1024 * 1024 * 4,
            ));
            if (!self::$arrClient[$clientKey]->connect($this->serverConfig["HOST"], $this->serverConfig["PORT"], 2)){
                $errorCode = self::$arrClient[$clientKey]->errCode;
                if ($errorCode == 0) {
                    $msg = "connect fail.check host dns.";
                    $errorCode = -1;
                } else {
                    $msg = \socket_strerror($errorCode);
                }
                unset(self::$arrClient[$clientKey]);
                throw new \Exception($msg . " " . $clientKey, $errorCode);
            }
        }
        return $clientKey;
    }

    //clean up the async list and result
    public function clearAsyncData(){
        self::$arrAsyncList = array();
        self::$arrAsyncResult = array();
    }

    //to make sure the guid is unique for the async result
    private function generateGuid(){
        while (1) {
            $guid = md5(microtime(true) . mt_rand(1, 1000000) . mt_rand(1, 1000000));
            if (!isset(self::$arrAsyncList[$guid])) {
                return $guid;
            }
        }
        return md5(microtime(true) . mt_rand(1, microtime(true)) . mt_rand(1, microtime(true)));
    }

	public function __destruct(){
        $this->clearAsyncData();
		self::$arrClient = null;
	}
}
