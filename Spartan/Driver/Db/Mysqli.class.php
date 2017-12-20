<?php
namespace Spartan\Driver\Db;
use Spartan\Core\Db;

defined('APP_PATH') or exit();
/**
 * Mysql数据库驱动
 * @category   Extend
 * @package  Extend
 * @subpackage  Driver.Db
 * @author    liu21st <liu21st@gmail.com>
 */
class Mysqli extends Db {

    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param string $config 数据库配置数组
     */
    public function __construct($config='') {
        if ( !extension_loaded('mysqli') ) {
	        \St::halt('_NOT_SUPPERT_EXTENSION_mysqli');
        }
        if(!empty($config)) {
            $this->config = $config;
            if(empty($this->config['params'])) {
                $this->config['params'] = array();
            }
        }
    }

    public function reConnect(){
        $this->close();
        $this->reTest = 0;
        $this->initConnect(true);
    }

    public function reTry(){
        $errNo = mysqli_errno($this->_linkID);
        if ($errNo == 2013 || $errNo == 2006){
            return true;
        }else{
            return false;
        }
    }
    /**
     * @description 连接数据库方法
     * @param string $config
     * @param int $linkNum
     * @return int
     */
    public function connect($config='',$linkNum=0) {
	    if ( !isset($this->linkID[$linkNum]) ) {
            if(empty($config)){$config = $this->config;}
	        $this->linkID[$linkNum] = mysqli_connect($config['HOST'], $config['USER'],$config['PWD'],$config['NAME'],$config['PORT']);
	        if ( !$this->linkID[$linkNum] || (!empty($config['NAME']) && !mysqli_select_db($this->linkID[$linkNum],$config['NAME'])) ) {
	            \St::halt($this->error());
            }
            //设置编码
	        //使用UTF8存取数据库
	        mysqli_query($this->linkID[$linkNum],"SET NAMES '".$config['CHARSET']."'");
	        //设置 sql_model

	        mysqli_query($this->linkID[$linkNum],"SET sql_mode=''");
            // 标记连接成功
            $this->connected    =   true;
            //注销数据库安全信息
            //if(1 != C('DB_DEPLOY_TYPE')) unset($this->config);
        }
        return $this->linkID[$linkNum];
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free() {
	    mysqli_free_result($this->queryID);
        $this->queryID = null;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $str  sql指令
     * @return mixed
     */
    public function query($str) {
	    if(0===stripos($str, 'call')){ // 存储过程查询支持
		    $this->close();
	    }
	    $this->initConnect(false);
	    if ( !$this->_linkID ) { return false;}
	    $this->queryStr = $str;
	    //释放前次的查询结果
	    if ( $this->queryID ) {$this->free();}
	    $this->queryID = mysqli_query($this->_linkID,$str);
	    if ( false === $this->queryID ) {
            if ($this->reTest >= 3 || !$this->reTry()){
                $this->error();
                return false;
            }else{
                $this->reTest++;
                $this->reConnect();
                return $this->query($str);
            }
	    } else {
		    $this->numRows = mysqli_num_rows($this->queryID);
		    return $this->getAll();
	    }
    }

    /**
     * 执行语句
     * @access public
     * @param string $str  sql指令
     * @return integer
     */
    public function execute($str) {
	    $this->initConnect(true);
	    if ( !$this->_linkID ) return false;
	    $this->queryStr = $str;
	    //释放前次的查询结果
	    if ( $this->queryID ) {$this->free();}
	    $result =   mysqli_query($this->_linkID,$str) ;
	    if ( false === $result) {
            if ($this->reTest >= 3 || !$this->reTry()){
                $this->error();
                return false;
            }else{
                $this->reTest++;
                $this->reConnect();
                return $this->execute($str);
            }
	    } else {
		    $this->numRows = mysqli_affected_rows($this->_linkID);
		    $this->lastInsID = mysqli_insert_id($this->_linkID);
		    return $this->numRows;
	    }
    }

    /**
     * 用于获取最后插入的ID
     * @access public
     * @return integer
     */
    public function last_insert_id() {
	    $this->lastInsID = mysqli_insert_id($this->_linkID);
        return $this->lastInsID;
    }

    /**
     * 启动事务
     * @access public
     * @param string $name 事务的名称
     * @return boolean
     */
    public function startTrans($name='') {
	    $this->initConnect(true);
	    if ( !$this->_linkID ){return false;}
	    //数据rollback 支持
	    if ($this->transTimes == 0) {
		    if (false == mysqli_query($this->_linkID,'START TRANSACTION')){
                if ($this->reTest >= 3 || !$this->reTry()){
                    $this->error();
                    return false;
                }else{
                    $this->reTest++;
                    $this->reConnect();
                    return $this->startTrans($name);
                }
            }
	    }
	    $this->transTimes++;
        (!$this->transName && $name) && $this->transName = $name;
        $this->sql['trans'][] = "startTrans,Master:{$this->transName},Current:{$name}";
	    return true;
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @param string $name 事务的名称
     * @return boolean
     */
    public function commit($name='') {
        $this->sql['trans'][] = "commit,Master:{$this->transName},Current:{$name}";
        if (((!$name && !$this->transName)||($this->transName==$name)) && $this->transTimes > 0){
		    $result = mysqli_query($this->_linkID,'COMMIT');
		    if(!$result){
			    $this->error();
			    return false;
		    }
            $this->sql['trans'][] = "commit finish on:{$name}";
            $this->transTimes = 0;
            $this->transName = '';
	    }
	    return true;
    }

    /**
     * 事务回滚
     * @access public
     * @return boolean
     */
    public function rollback() {
	    if ($this->transTimes > 0) {
		    $result = mysqli_query($this->_linkID,'ROLLBACK');
		    $this->transTimes = 0;
		    if(!$result){
			    $this->error();
			    return false;
		    }
	    }
	    return true;
    }

    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getAll() {
	    //返回数据集
	    $result = array();
	    if($this->numRows >0) {
		    while($row = mysqli_fetch_assoc($this->queryID)){
			    $result[]   =   $row;
		    }
		    mysqli_data_seek($this->queryID,0);
	    }
	    return $result;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     */
    public function getFields($tableName) {
	    $result =   $this->query('SHOW COLUMNS FROM '.$this->parseKey($tableName));
	    $info   =   array();
	    if($result) {
		    foreach ($result as $key => $val) {
			    $info[$val['Field']] = array(
				    'name'    => $val['Field'],
				    'type'    => $val['Type'],
				    'notnull' => (bool) ($val['Null'] === ''), // not null is empty, null is yes
				    'default' => $val['Default'],
				    'primary' => (strtolower($val['Key']) == 'pri'),
				    'autoinc' => (strtolower($val['Extra']) == 'auto_increment'),
			    );
		    }
	    }
	    return $info;
    }

    /**
     * 取得数据库的表信息
     * @access public
     */
    public function getTables($dbName='') {
	    if(!empty($dbName)) {
		    $sql    = 'SHOW TABLES FROM '.$dbName;
	    }else{
		    $sql    = 'SHOW TABLES ';
	    }
	    $result =   $this->query($sql);
	    $info   =   array();
	    foreach ($result as $key => $val) {
		    $info[$key] = current($val);
	    }
	    return $info;
    }

    /**
     * 关闭数据库
     * @access public
     */
    public function close() {
	    if ($this->_linkID){
		    @mysqli_close($this->_linkID);
	    }
	    $this->_linkID = null;
        $this->linkID = [];
        $this->connected = false;
        $this->reTest = 0;
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @return string
     */
    public function error() {
	    $this->error = mysqli_error($this->_linkID);
	    if('' != $this->queryStr){
		    $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
	    }
	    \St::halt($this->error,'','ERR');
	    return $this->error;
    }

    /**
     * SQL指令安全过滤
     * @access public
     * @param string $str  SQL指令
     * @return string
     */
    public function escapeString($str) {
	    if(!$this->_linkID) {
            $this->initConnect(true);
	    }
        return mysqli_real_escape_string($this->_linkID,$str);
    }

    /**
     * limit
     * @param int
     * @return string
     */
    public function parseLimit($limit) {
	    return !empty($limit)?' LIMIT '.$limit.' ':'';
    }

	/**
	 * 字段和表名处理添加`
	 * @access protected
	 * @param string $key
	 * @return string
	 */
	protected function parseKey(&$key) {
		$key   =  trim($key);
		if(!preg_match('/[,\'\"\*\(\)`.\s]/',$key)) {
			$key = '`'.$key.'`';
		}
		return $key;
	}
}