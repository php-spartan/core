<?php
namespace Spartan\Driver\Cache;

defined('APP_PATH') OR exit('404 Not Found');

class Redis {
    /**@var $this ->instance \Redis */
    private $instance = null;
    private $config = null;
    private $preSession = 'PHPREDIS_SESSION:';

    public static function getInstance($arrConfig=[]){
        static $_instance = null;
        !$_instance && $_instance = new Redis($arrConfig);
        return $_instance;
    }

    public function __construct($arrConfig=[]) {
        if ( !extension_loaded('redis') ) {
            \St::halt('_NOT_SUPPERT_EXTENSION_REDIS');
        }
        $this->config = C('REDIS_SERVER');
        $arrConfig && $this->config = array_merge($this->config,$arrConfig);
        $this->instance = new \Redis();
        $this->connection();
    }

    public function connection(){
        $conn = $this->config['PCONNECT']?'pconnect':'connect';
        $status = $this->instance->$conn($this->config['HOST'],$this->config['PORT'],$this->config['TIMEOUT']);
        if (isset($this->config['AUTH']) && $this->config['AUTH']){
            $this->instance->auth($this->config['AUTH']);
        }
        if(!$status){
            print_r($this->config);
            \St::halt('REDIS_SERVER_CANNOT_CONNECT'.$this->config);
        }
    }

    public function keys($pattern){
        return $this->instance->keys($pattern);
    }
    public function lPush($key, $value1){
        return $this->instance->lPush($key, $value1);
    }
    public function rPush($key, $value1){
        return $this->instance->rPush($key, $value1);
    }
    public function LPOP($key){
        return $this->instance->LPOP($key);
    }
    public function ltrim($key, $start, $stop){
        return $this->instance->ltrim($key, $start, $stop);
    }
    public function lInsert($key, $position, $pivot, $value){
        return $this->instance->lInsert($key, $position, $pivot, $value);
    }

    public function LLEN( $key){
        return $this->instance->LLEN( $key );
    }

    public function lRange( $key, $start, $end ){
        return $this->instance->lRange( $key, $start, $end );
    }

    public function sMembers($key){
        return $this->instance->sMembers($key);
    }

    public function sAdd($key,$value){
        return $this->instance->sAdd($key,$value);
    }

    public function get($key){
        return $this->instance->get($key);
    }

    public function set($key,$value){
        is_array($value) && $value = serialize($value);
        return $this->instance->get($key);
    }

    public function del($key){
        return $this->instance->del($key);
    }

    public function zAdd($key,$score,$value){
        return $this->instance->zAdd($key,$score,$value);
    }

    public function hSet($key,$hashKey,$value){
        return $this->instance->hSet($key,$hashKey,$value);
    }

    public function hGet($key,$hashKey){
        return $this->instance->hGet($key,$hashKey);
    }

    public function hMset($key,$hashKey){
        return $this->instance->hMset($key,$hashKey);
    }

    public function hMGet($key,$hashKey){
        return $this->instance->hMGet($key,$hashKey);
    }

    public function hGetAll($key){
        return $this->instance->hGetAll($key);
    }

    public function hVals($key){
        return $this->instance->hVals($key);
    }

    public function hIncrBy( $key, $hashKey, $value){
        return $this->instance->hIncrBy($key, $hashKey, $value);
    }

    public function HLEN( $key){
        return $this->instance->HLEN($key);
    }

    public function hDel( $key, $hashKey1, $hashKey2 = null, $hashKey3 = null ){
        return $this->instance->hDel($key, $hashKey1, $hashKey2,$hashKey3);
    }

    public function zRangeByScore($key, $start, $end, array $options = array()){
        return $this->instance->zRangeByScore($key, $start, $end,$options);
    }

    public function zIncrBy($key, $value, $member){
        return $this->instance->zIncrBy($key, $value, $member);
    }

    public function getSession($session_id,$name){
        $strInfo = $this->get($this->preSession.$session_id);
        if ($strInfo && stripos($strInfo,$name.'|')!==false){
            $strInfo = (explode(';}',explode($name.'|',$strInfo)[1])[0]).';}';
            $arrInfo = unserialize($strInfo);
            return $arrInfo?$arrInfo:$strInfo;
        }else{
            return '';
        }
    }

    public function close() {
        $this->instance->close();
    }

    public function flush() {
        $this->instance->flushDB();
    }
    public function select($dbindex){
        return $this->instance->select($dbindex);
    }
}