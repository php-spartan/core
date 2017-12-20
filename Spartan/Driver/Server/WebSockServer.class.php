<?php
namespace Spartan\Core;
use Spartan\Driver\Cache\Redis;

abstract class WebSockServer extends Server {
    /** @var $logic \Socket\Common\DriverLogic */
    public $logic = null;

    public function __construct(){
        parent::__construct();
        $this->serverHandle['Open'] = 'onOpen';
        $this->serverHandle['Message'] = 'onMessage';
    }

    public function onOpen(\swoole_websocket_server $server, \swoole_http_request $request){
        $arrClientInfo = explode('/',trim($request->server['request_uri'],'/'));
        $arrResult = $this->checkAccess($request->fd,$arrClientInfo);
        if ($arrResult['status'] != 1){
            $arrResult['guid_class'] = 'TabsPage.getOut';
            $this->sendData($request->fd,$arrResult);
            $this->close($request->fd);
        }
        //连接成功，写入内存表
        $this->server->table->set($arrClientInfo[1], ['cid' => $request->fd]);
        $this->insertConnectLog($request->fd,1);//写入连接成功的日志，
    }

    /**
     * @description 全部的交互，都是使用同步，后期可改为异步，速度即可质变
     * @param \swoole_server $server
     * @param \swoole_websocket_frame $frame
     */
    public function onMessage(\swoole_server $server, \swoole_websocket_frame $frame){
        $arrData = json_decode($frame->data,true);
        $arrData['fd'] = $frame->fd;
        $arrUserInfo = $this->logic->getUserInfoByClientId($frame->fd,['id']);
        $arrData['me_user_id'] = isset($arrUserInfo['id'])?$arrUserInfo['id']:0;
        $strGuidClass = isset($arrData['guid_class'])?$arrData['guid_class']:'';
        $arrData = $this->mvcResult($arrData);
        if ($strGuidClass && $arrData){
            $this->sendData($frame->fd,$arrData);
        }
    }

    /**
     * 使用MVC来取得运行结果
     * @param $arrData
     * @return mixed
     */
    private function mvcResult($arrData){
        if (!is_array($arrData)){
            return Array('info'=>'mvcResult need a array data.','status'=>0,[]);
        }
        $strUrl = isset($arrData['url'])?$arrData['url']:'';
        unset($arrData['url']);
        $strGuidClass = isset($arrData['guid_class'])?$arrData['guid_class']:'';
        $param = Array(
            'api'=>Array(
                $strUrl,
                $arrData,
            ),
        );
        if ($strUrl == 'user.logout'){
            $objRedis = Redis::getInstance($this->logic->getSessionRedisConfig());
            $this->close($arrData['fd']);
            $this->insertConnectLog($arrData['fd'],2);//写入断开连接
            $objRedis->del($this->logic->getKey('user_client').$arrData['fd']);
            if ($this->server->table->get($arrData['me_user_id'])['cid'] == $arrData['fd']){
                $this->server->table->del($arrData['me_user_id']);
            }
            \St::log('关掉退出连接。'.$arrData['me_user_id'].'='.$arrData['fd']);
            return [];
        }else{
            $arrThisResult = $this->runMvc($param);
        }
        if (!isset($arrThisResult['info'])){
            list($info,$status,$data) = $arrThisResult?$arrThisResult:['未知错误',0,[]];
        }else{
            $info = $arrThisResult['info'];
            $status = isset($arrThisResult['status'])?$arrThisResult['status']:0;
            $data = isset($arrThisResult['data'])?$arrThisResult['data']:0;
        }
        !$status && $status = 0;!$data && $data = [];
        return Array('guid_class'=>$strGuidClass,'info'=>$info,'status'=>$status,'data'=>$data);
    }

    /**
     * @description 外发任务处理接口
     * @param $data
     * @return array
     */
    public function doTaskWork($data){
        return $this->mvcResult($data);
    }

    /**
     * 给指定的客户端发送信息
     * @param $fd
     * @param $msg
     * @return bool
     */
    public function sendData($fd,$msg){
        is_array($msg) && $msg = json_encode($msg,JSON_UNESCAPED_UNICODE);
        return $this->server->push($fd,$msg);
    }

    /**
     * 检查是否可以被连接
     * @param $intClientId int
     * @param $arrClientInfo array = session_id,user_id,rnd_id,md5
     * @return array
     */
    public function checkAccess($intClientId,$arrClientInfo){
        if (count($arrClientInfo) != 4){
            return Array('info'=>'URL is not invalid.'.count($arrClientInfo),'status'=>0,'action'=>'invalid');
        }
        $objRedis = Redis::getInstance($this->logic->getSessionRedisConfig());
        $arrUserInfo = $objRedis->getSession($arrClientInfo[0],'user_info');
        if (!$arrUserInfo){
            return Array('info'=>'login time out.'.$arrClientInfo[0],'status'=>0,'action'=>'invalid');
        }
        if ($arrClientInfo[3] != md5($arrClientInfo[0].$arrUserInfo['id'].$arrClientInfo[2])){
            return Array('info'=>'Md5 key has fail in URL.','status'=>0,'action'=>'invalid');
        }
        $arrUserInfo = $this->logic->getRedisSaveUserInfo($arrUserInfo);
        $arrUserInfo['fd'] = $intClientId;
        $arrUserInfo['online'] = 1;//1为在钱
        $arrOldFile = Array('online','fd');
        $arrOldUserInfo = $objRedis->hMGet($this->logic->getKey('user_info_info').$arrUserInfo['id'],$arrOldFile);
        $result = $objRedis->hMset($this->logic->getKey('user_info_info').$arrUserInfo['id'],$arrUserInfo);
        if (!$result){
            return Array('info'=>'Input user_info_key data error.','status'=>0,'action'=>'invalid');
        }
        $result = $objRedis->hMset($this->logic->getKey('user_client').$intClientId,$arrUserInfo);
        if (!$result){
            return Array('info'=>'Input user_client_key data error.','status'=>0,'action'=>'invalid');
        }
        //发送当前状态
        foreach ($arrOldUserInfo as &$v){
            !$v && $v = 0;
        };
        unset($v);
        $this->sendData($intClientId,Array(
            'info'=>'当前状态',
            'status'=>1,
            'data'=>['fd'=>$intClientId,'online'=>1],
            'guid_class'=>'TabsPage.loginSuccess'
            )
        );
        //如果有旧连接，就关掉
        $intCloseClientId = 0;
        if (isset($arrOldUserInfo['fd']) && $arrOldUserInfo['fd'] > 0 && $arrOldUserInfo['fd'] != $intClientId){
            $this->getOutUser($arrOldUserInfo['fd'],$objRedis);
        }
        if ($intCloseClientId == 0 && $this->server->table->get($arrUserInfo['id'])['cid'] != $intClientId){
            $this->getOutUser($this->server->table->get($arrUserInfo['id'])['cid'],$objRedis);
        }
        return Array('info'=>'OK','status'=>1,'action'=>'invalid');
    }

    /**
     * @param $intCloseClientId
     * @param $objRedis Redis
     */
    private function getOutUser($intCloseClientId,&$objRedis){
        $this->sendData($intCloseClientId,Array(
                'info'=>'帐号在其它设备登录。',
                'status'=>1,
                'guid_class'=>'TabsPage.getOut')
        );
        $this->close($intCloseClientId);
        \St::log('关掉旧连接。'.$intCloseClientId);
        $objRedis->del($this->logic->getKey('user_client').$intCloseClientId);
    }

    /**
     * 退出后，删除相应的信息
     * @param \swoole_server $server
     * @param $fd
     * @param $reactorId
     */
    public function closeFun(\swoole_server $server,$fd,$reactorId){
        $objRedis = Redis::getInstance($this->logic->getSessionRedisConfig());
        $this->insertConnectLog($fd,2);//写入断开连接
        $result = $objRedis->del($this->logic->getKey('user_client').$fd);
        if (!$result){
            \St::log('清理关闭信息失败。'.$fd);
        }
    }

    /**
     * 记录用户连接的日志
     * @param $intClient
     * @param int $intStatus
     * @return array
     */

    private function insertConnectLog($intClient,$intStatus=1){
        $arrUserInfo = $this->logic->getUserInfoByClientId($intClient,['id','user_name','real_name']);
        if ($arrUserInfo['id'] < 1){
            return Array('user id is empty.',0);
        }
        $strMsg = ($intStatus==1?'连接':'断开').'('.$intClient.') '.$arrUserInfo['user_name'].' 于 '.date('Y-m-d H:i:s',time());
        $objRedis = Redis::getInstance($this->logic->getSessionRedisConfig());
        if ($intStatus == 2){//删除他的打卡信息
            $arrKeyValue = Array(
                'online'=>0,//1为在线
                'fd'=>0
            );
            $objRedis->hMSet($this->logic->getKey('user_info_info').$arrUserInfo['id'],$arrKeyValue);
            if ($this->server->table->get($arrUserInfo['id'])['cid'] == $intClient){
                $this->server->table->del($arrUserInfo['id']);
            }
            \ST::log('退出:'.$arrUserInfo['id'].'=='.$this->server->table->get($arrUserInfo['id'])['cid']);
        }
        $result = $objRedis->LPUSH($this->logic->getKey('user_info_connect').$arrUserInfo['id'],$strMsg);
        if (!$result){
            return Array('插入日志失败。',0);
        }
        $objRedis->ltrim($this->logic->getKey('user_info_connect').$arrUserInfo['id'],0,49);
        return Array('日志成功。',1);
    }
}
