<?php
namespace Spartan\Core;
defined('APP_PATH') OR die('404 Not Found');

class Db
{
    protected $dbType     = null;// 数据库类型
    protected $pconnect   = false;// 是否使用永久连接
    protected $queryStr   = '';// 当前SQL指令
	protected $sql        = Array();//所有SQL指令
    protected $lastInsID  = null;// 最后插入ID
    protected $numRows    = 0;// 返回或者影响记录数
    protected $numCols    = 0;// 返回字段数
    protected $transTimes = 0;// 事务指令数
    protected $error      = '';// 错误信息
    protected $linkID     = Array();// 数据库连接ID 支持多个连接
    protected $_linkID    = null;// 当前连接ID
    protected $queryID    = null;// 当前查询ID
    protected $connected  = false;// 是否已经连接数据库
    protected $config     = Array();// 数据库连接参数配置
    protected $comparison = array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN');// 数据库表达式
    protected $selectSql  = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%COMMENT%';// 查询表达式
    protected $build = false;
	protected $transName = '';//事务名称
    protected $reTest   = 0;//重连接次数
	/**
	 * 取得数据库类实例
	 * @param array $config
	 * @return Db 返回数据库驱动类
	 */
	public static function instance($config=Array()) {
        $config = array_merge(C('DB'),$config);
        return \St::getInstance('Spartan\\Driver\\Db\\'.ucwords(strtolower($config['TYPE'])),$config);
    }
    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function initConnect($master=true) {
        if(1 == C('DB.DEPLOY_TYPE')){// 采用分布式数据库
	        $this->_linkID = $this->multiConnect($master);
        }else{// 默认单数据库
	        if ( !$this->connected ){$this->_linkID = $this->connect();}
        }
    }

    /**
     * 连接分布式服务器
     * @access protected
     * @param boolean $master 主服务器
     * @return int
     */
    protected function multiConnect($master=false) {
	    $_config = Array();
	    foreach ($this->config as $key=>$val){
            $_config[$key] = explode(',',$val);
        }
        // 数据库读写是否分离
        if(C('DB.RW_SEPARATE')){
            // 主从式采用读写分离
            if($master)// 主服务器写入
                $r  =   floor(mt_rand(0,C('DB.MASTER_NUM')-1));
            else{
                if(is_numeric(C('DB.SLAVE_NO'))) {// 指定服务器读
                    $r = C('DB.SLAVE_NO');
                }else{// 读操作连接从服务器,每次随机连接的数据库
                    $r = floor(mt_rand(C('DB.MASTER_NUM'),count($_config['HOST'])-1));
                }
            }
        }else{// 读写操作不区分服务器// 每次随机连接的数据库
            $r = floor(mt_rand(0,count($_config['HOST'])-1));
        }
        $db_config = array(
            'USER'  =>  isset($_config['USER'][$r])?$_config['USER'][$r]:$_config['USER'][0],
            'PWD'  =>  isset($_config['PWD'][$r])?$_config['PWD'][$r]:$_config['PWD'][0],
            'HOST'  =>  isset($_config['HOST'][$r])?$_config['HOST'][$r]:$_config['HOST'][0],
            'PORT'  =>  isset($_config['PORT'][$r])?$_config['PORT'][$r]:$_config['PORT'][0],
            'NAME'  =>  isset($_config['NAME'][$r])?$_config['NAME'][$r]:$_config['NAME'][0],
            'CHARSET'   =>  isset($_config['CHARSET'][$r])?$_config['CHARSET'][$r]:$_config['CHARSET'][0],
        );
        return $this->connect($db_config,$r);
    }

    /**
     * 设置锁机制
     * @param $lock
     * @return string
     */
    protected function parseLock($lock=false) {
        if(!$lock) return '';
        return ' FOR UPDATE ';
    }

    /**
     * set分析
     * @access protected
     * @param array $data
     * @return string
     */
    protected function parseSet($data) {
	    $set = Array();
	    foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $set[]  =   $this->parseKey($key).'='.$val[1];
            }elseif(is_scalar($val) || is_null($val)) { // 过滤非标量数据
              $set[]  =   $this->parseKey($key).'='.$this->parseValue($val);
            }
        }
        return ' SET '.implode(',',$set);
    }

    /**
     * 字段名分析
     * @access protected
     * @param string $key
     * @return string
     */
    protected function parseKey($key) {
        return $key;
    }

    /**
     * value分析
     * @access protected
     * @param mixed $value
     * @return string
     */
    protected function parseValue($value) {
        if(is_string($value)) {
            $value =  '\''.$this->escapeString($value).'\'';
        }elseif(isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp'){
            $value =  $this->escapeString($value[1]);
        }elseif(is_array($value)) {
            $value =  array_map(array($this, 'parseValue'),$value);
        }elseif(is_bool($value)){
            $value =  $value ? '1' : '0';
        }elseif(is_null($value)){
            $value =  'null';
        }
        return $value;
    }

    /**
     * field分析
     * @access protected
     * @param mixed $fields
     * @return string
     */
    protected function parseField($fields) {
        if(is_string($fields) && strpos($fields,',')) {
            $fields = explode(',',$fields);
        }
        if(is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array   =  array();
            foreach ($fields as $key=>$field){
                if(!is_numeric($key))
                    $array[] =  $this->parseKey($key).' AS '.$this->parseKey($field);
                else
                    $array[] =  $this->parseKey($field);
            }
            $fieldsStr = implode(',', $array);
        }elseif(is_string($fields) && !empty($fields)) {
            $fieldsStr = $this->parseKey($fields);
        }else{
            $fieldsStr = '*';
        }
        return $fieldsStr;
    }

    /**
     * table分析
     * @access protected
     * @param mixed $tables
     * @return string
     */
    protected function parseTable($tables) {
        if(is_array($tables)) {// 支持别名定义
	        $tables = $this->parseTrueTable($tables[0]).' '.$tables[1];
        }elseif(is_string($tables)){
	        $tables = $this->parseTrueTable($tables);
        }
        return $tables;
    }

	/**
	 * 返回正真正的数据表名
	 * @param $table
	 * @return string
	 */
	protected function parseTrueTable($table){
		if($this->config['PREFIX'] && stripos($table,$this->config['PREFIX'])!==0)
		{
			$table = $this->config['PREFIX'].$table;
		}
		return $table;
	}
    /**
     * where分析
     * @access protected
     * @param mixed $where
     * @return string
     */
    protected function parseWhere($where) {
        $whereStr = '';
        if(is_string($where))// 直接使用字符串条件
        {
            $whereStr = $where;
        }else{ // 使用数组表达式
            $operate  = isset($where['_logic'])?strtoupper($where['_logic']):'';
            if(in_array($operate,array('AND','OR','XOR')))
            {// 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate    =   ' '.$operate.' ';
                unset($where['_logic']);
            }else{// 默认进行 AND 运算
                $operate    =   ' AND ';
            }
            foreach ($where as $key=>$val){
                $whereStr .= '( ';
                if(is_numeric($key)){
                    $key  = '_complex';
                }
                if(0===strpos($key,'_')){// 解析特殊条件表达式
                    $whereStr   .= $this->parseThinkWhere($key,$val);
                }else{// 查询字段的安全过滤
                    if(!preg_match('/^[A-Z_\|\&\-.a-z0-9\(\)\,@]+$/',trim($key))){
                        \St::halt('_EXPRESS_ERROR_:'.$key);
                    }
                    // 多条件支持
                    $multi  = is_array($val) &&  isset($val['_multi']);
                    $key    = trim($key);
                    if(strpos($key,'|')) { // 支持 name|title|nickname 方式定义查询字段
                        $array =  explode('|',$key);
                        $str   =  array();
                        foreach ($array as $m=>$k){
                            $v =  $multi?$val[$m]:$val;
                            $str[]   = '('.$this->parseWhereItem($this->parseKey($k),$v).')';
                        }
                        $whereStr .= implode(' OR ',$str);
                    }elseif(strpos($key,'&')){
                        $array =  explode('&',$key);
                        $str   =  array();
                        foreach ($array as $m=>$k){
                            $v =  $multi?$val[$m]:$val;
                            $str[]   = '('.$this->parseWhereItem($this->parseKey($k),$v).')';
                        }
                        $whereStr .= implode(' AND ',$str);
                    }else{
                        $whereStr .= $this->parseWhereItem($this->parseKey($key),$val);
                    }
                }
                $whereStr .= ' )'.$operate;
            }
            $whereStr = substr($whereStr,0,-strlen($operate));
        }
        return empty($whereStr)?'':' WHERE '.$whereStr;
    }

    // where子单元分析
    protected function parseWhereItem($key,$val) {
        $whereStr = '';
        if(is_array($val)) {
            if(is_string($val[0])) {
                if(preg_match('/^(EQ|NEQ|GT|EGT|LT|ELT)$/i',$val[0])) { // 比较运算
                    $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
                }elseif(preg_match('/^(NOTLIKE|LIKE)$/i',$val[0])){// 模糊查找
                    if(is_array($val[1])) {
                        $likeLogic  =   isset($val[2])?strtoupper($val[2]):'OR';
                        if(in_array($likeLogic,array('AND','OR','XOR'))){
                            $likeStr    =   $this->comparison[strtolower($val[0])];
                            $like       =   array();
                            foreach ($val[1] as $item){
                                $like[] = $key.' '.$likeStr.' '.$this->parseValue($item);
                            }
                            $whereStr .= '('.implode(' '.$likeLogic.' ',$like).')';
                        }
                    }else{
                        $whereStr .= $key.' '.$this->comparison[strtolower($val[0])].' '.$this->parseValue($val[1]);
                    }
                }elseif('exp'==strtolower($val[0])){ // 使用表达式
                    $whereStr .= ' ('.$key.' '.$val[1].') ';
                }elseif(preg_match('/IN/i',$val[0])){ // IN 运算
                    if(isset($val[2]) && 'exp'==$val[2]) {
                        $whereStr .= $key.' '.strtoupper($val[0]).' '.$val[1];
                    }else{
                        (is_numeric($val[1])||is_string($val[1])) && $val[1] = explode(',',$val[1]);
                        $zone = implode(',',$this->parseValue($val[1]));
                        $whereStr .= $key.' '.strtoupper($val[0]).' ('.$zone.')';
                    }
                }elseif(preg_match('/BETWEEN/i',$val[0])){ // BETWEEN运算
                    $data = is_string($val[1])? explode(',',$val[1]):$val[1];
                    $whereStr .=  ' ('.$key.' '.strtoupper($val[0]).' '.$this->parseValue($data[0]).' AND '.$this->parseValue($data[1]).' )';
                }else{
                    \St::halt('_EXPRESS_ERROR_:'.$val[0]);
                }
            }else {
                $count = count($val);
                $rule  = isset($val[$count-1]) ? (is_array($val[$count-1]) ? strtoupper($val[$count-1][0]) : strtoupper($val[$count-1]) ) : '' ;
                if(in_array($rule,array('AND','OR','XOR'))) {
                    $count  = $count -1;
                }else{
                    $rule   = 'AND';
                }
                for($i=0;$i<$count;$i++) {
                    $data = is_array($val[$i])?$val[$i][1]:$val[$i];
                    if('exp'==strtolower($val[$i][0])) {
                        $whereStr .= '('.$key.' '.$data.') '.$rule.' ';
                    }else{
                        $whereStr .= '('.$this->parseWhereItem($key,$val[$i]).') '.$rule.' ';
                    }
                }
                $whereStr = substr($whereStr,0,-4);
            }
        }else {
            $whereStr .= $key.' = '.$this->parseValue($val);
        }
        return $whereStr;
    }

    /**
     * 特殊条件分析
     * @access protected
     * @param string $key
     * @param mixed $val
     * @return string
     */
    protected function parseThinkWhere($key,$val) {
        $whereStr   = '';
        switch($key) {
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = is_string($val)? $val : substr($this->parseWhere($val),6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val,$where);
                if(isset($where['_logic'])) {
                    $op   =  ' '.strtoupper($where['_logic']).' ';
                    unset($where['_logic']);
                }else{
                    $op   =  ' AND ';
                }
                $array   =  array();
                foreach ($where as $field=>$data)
                    $array[] = $this->parseKey($field).' = '.$this->parseValue($data);
                $whereStr   = implode($op,$array);
                break;
        }
        return $whereStr;
    }

    /**
     * join分析
     * @access protected
     * @param array $join
     * @return string
     */
    protected function parseJoin($join) {
        $joinStr = '';
        if(!empty($join)) {
            if(is_array($join)) {
                foreach ($join as $_join){
                    if(false !== stripos($_join,'JOIN'))
                        $joinStr .= ' '.$_join;
                    else
                        $joinStr .= ' LEFT JOIN ' .$_join;
                }
            }else{
                $joinStr .= ' LEFT JOIN ' .$join;
            }
        }
        return $joinStr;
    }

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @return string
     */
    protected function parseOrder($order) {
        if(is_array($order)) {
            $array   =  array();
            foreach ($order as $key=>$val){
                if(is_numeric($key)) {
                    $array[] =  $this->parseKey($val);
                }else{
                    $array[] =  $this->parseKey($key).' '.$val;
                }
            }
            $order   =  implode(',',$array);
        }
        return !empty($order)?  ' ORDER BY '.$order:'';
    }

    /**
     * group分析
     * @access protected
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group) {
        return !empty($group)? ' GROUP BY '.$group:'';
    }

    /**
     * having分析
     * @access protected
     * @param string $having
     * @return string
     */
    protected function parseHaving($having) {
        return  !empty($having)?   ' HAVING '.$having:'';
    }

    /**
     * comment分析
     * @access protected
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment) {
        return  !empty($comment)?   ' /* '.$comment.' */':'';
    }

    /**
     * distinct分析
     * @access protected
     * @param mixed $distinct
     * @return string
     */
    protected function parseDistinct($distinct) {
        return !empty($distinct)?   ' DISTINCT ' :'';
    }

    /**
     * union分析
     * @access protected
     * @param mixed $union
     * @return string
     */
    protected function parseUnion($union) {
        if(empty($union)) return '';
        if(isset($union['_all'])) {
            $str  =   'UNION ALL ';
            unset($union['_all']);
        }else{
            $str  =   'UNION ';
        }
        $sql = Array();
        foreach ($union as $u){
            $sql[] = $str.(is_array($u)?$this->buildSelectSql($u):$u);
        }
        return implode(' ',$sql);
    }

	/**
	 * 提取所有的SQL语句。
	 * @param null $type
	 * @return array
	 */
	public function getAllSql($type=null){
		return $type&&isset($this->sql[$type])?$this->sql[$type]:$this->sql;
	}

    /**
     * 生成SQL的标识，所有的增删修的动作只会生成SQL，而不会运行
     * @return bool
     */
    public function buildSql(){
        $this->build = true;
        $this->sql = Array();
        return true;
    }

    /**
     * 取消生成SQL的标识，生成的SQL会运行
     * @param $type string 取回哪一种SQL的类型
     * @return array
     */
    public function cancelBuildSql($type=null){
        $this->build = false;
        return $this->getAllSql($type);
    }
    /**
     * 插入记录
     * @history 1、insert 没有锁表操作 by singer
     * @param string $table 表名
     * @param mixed $data 数据
     * @param array $options 参数表达式
     * @return false | integer
     */
    public function insert($table,$data,$options=array()) {
        $values = $fields = array();
        $options['table'] = $table;
	    $replace = isset($options['replace'])?$options['replace']:false;//是否replace
        foreach ($data as $key=>$val){
            if(is_array($val) && 'exp' == $val[0]){
                $fields[]   =  $this->parseKey($key);
                $values[]   =  $val[1];
            }elseif(is_scalar($val) || is_null($val)) { // 过滤非标量数据
              $fields[]   =  $this->parseKey($key);
              $values[]   =  $this->parseValue($val);
            }
        }
        $sql = ($replace?'REPLACE':'INSERT').' INTO '.$this->parseTable($options['table']).
	        ' ('.implode(',', $fields).') VALUES ('.implode(',', $values).')';
        $sql   .= $this->parseComment(!empty($options['comment'])?$options['comment']:'');
        if(!in_array($table,['sys_sql_log'])){$this->sql['insert'][] = $sql;}
        if($this->build){return false;}
	    $result = $this->execute($sql);
	    if(false !== $result ){
		    $result = $this->getLastInsID();
	    }
	    return $result;
    }

    /**
     * 更新记录,
     * @history 1、update没有锁表操作 by singer
     * @param string $table 表名
     * @param mixed $data 数据
     * @param array $options 表达式
     * @return false | integer
     */
    public function update($table,$data,$options){
        if(!isset($options['where'])){//防止误操作，条件不能为空，不使用条件时，where==false
	        return false;
        }elseif(isset($options['where']) && $options['where']==false){
            $options['where'] = '';
        }
        $table = isset($options['alias'])?Array($table,$options['alias']):$table;
	    $sql = 'UPDATE '
            .$this->parseTable($table)
            .$this->parseSet($data)
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseLimit(!empty($options['limit'])?$options['limit']:'')
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        $this->sql['update'][] = $sql;
        if($this->build){return false;}
        return $this->execute($sql);
    }

    /**
     * 删除记录
     * @param string $table 表名
     * @param array $options 表达式
     * @return false | integer
     */
    public function delete($table,$options=array()){
        if(!isset($options['where'])){//防止误操作，条件不能为空，不使用条件时，where==false
            return false;
        }elseif(isset($options['where']) && $options['where']==false){
            $options['where'] = '';
        }
        $this->initConnect(false);
        $sql = 'DELETE FROM '
            .$this->parseTable($table)
            .$this->parseWhere(!empty($options['where'])?$options['where']:'')
            .$this->parseComment(!empty($options['comment'])?$options['comment']:'');
        $this->sql['delete'][] = $sql;
        if ($this->build){return false;}
        return $this->execute($sql);
    }
	/**
	 * 选择查询语句
	 * @param $table
	 * @param array $options
	 * @return mixed
	 */
	public function select($table,$options=array()){
		$options['table'] = isset($options['alias'])?Array($table,$options['alias']):$table;
        $sql = $this->buildSelectSql($options);
        $this->sql['select'][] = $sql;
        $result = $this->query($sql);
        return $result;
    }

	/**
	 * 选择一条记录的查询语句
	 * @param $table
	 * @param array $options
	 * @param string $math 是否运算
	 * @return mixed
	 */
	public function find($table,$options=array(),$math=null){
		if (!is_null($math)){
            $math = explode('(',$math);//count(id). sum(money). min(id)
            if (!in_array($math[0],['count','sum','min','max','avg'])){
                $math = null;
            }else{
                $math[1] = isset($math[1])?str_replace(')','',$math[1]):'*';
                $options['field'] = "$math[0]($math[1]) as tmp";
	            unset($options['order']);
            }
        }
		unset($options['page']);
		$options['limit'] = 1;
		$options['table']=(isset($options['alias'])&&!$math)?Array($table,$options['alias']):$table;
		if($math){unset($options['lock']);}
		$options['table'] = isset($options['alias'])?Array($table,$options['alias']):$table;
		$sql = $this->buildSelectSql($options);
        $this->sql['select'][] = $sql;
        $result = $this->query($sql);
		if(false === $result){
			return false;
		}
		if(empty($result) || !isset($result[0])){
			return null;
		}
		return $math?$result[0]['tmp']:$result[0];
	}

    /**
     * 生成查询SQL
     * @param array $options 表达式
     * @return string
     */
	protected function buildSelectSql($options=array()) {
        $this->initConnect(false);
        if(isset($options['page']))// 根据页数计算limit
        {
            if(strpos($options['page'],',')) {
                list($page,$listRows) =  explode(',',$options['page']);
            }else{
                $page = $options['page'];
            }
            $page    =  $page?:1;
            $listRows=  isset($listRows)?$listRows:(is_numeric($options['limit'])?$options['limit']:20);
            $offset  =  $listRows*((int)$page-1);
            $options['limit'] =  $offset.','.$listRows;
        }
        $sql  =     $this->parseSql($this->selectSql,$options);
        $sql .=     $this->parseLock(isset($options['lock'])?$options['lock']:false);
	    $sql = str_ireplace('@.',$this->config['PREFIX'],$sql);
        return $sql;
    }

    /**
     * 替换SQL语句中表达式
     * @param string $sql 原SQL
     * @param array $options 表达式
     * @return string
     */
	protected function parseSql($sql,$options=array()){
        $sql   = str_replace(
            array('%TABLE%','%DISTINCT%','%FIELD%','%JOIN%','%WHERE%','%GROUP%','%HAVING%','%ORDER%','%LIMIT%','%UNION%','%COMMENT%'),
            array(
                $this->parseTable($options['table']),
                $this->parseDistinct(isset($options['distinct'])?$options['distinct']:false),
                $this->parseField(!empty($options['field'])?$options['field']:'*'),
                $this->parseJoin(!empty($options['join'])?$options['join']:''),
                $this->parseWhere(!empty($options['where'])?$options['where']:''),
                $this->parseGroup(!empty($options['group'])?$options['group']:''),
                $this->parseHaving(!empty($options['having'])?$options['having']:''),
                $this->parseOrder(!empty($options['order'])?$options['order']:''),
                $this->parseLimit(!empty($options['limit'])?$options['limit']:''),
                $this->parseUnion(!empty($options['union'])?$options['union']:''),
                $this->parseComment(!empty($options['comment'])?$options['comment']:'')
            ),$sql);
        return $sql;
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql() {
        return $this->queryStr;
    }

    /**
     * 获取最近插入的ID
     * @return string
     */
    public function getLastInsID() {
        return $this->lastInsID;
    }

    /**
     * 获取最近的错误信息
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     * 析构方法
     */
    public function __destruct(){
        if ($this->queryID){
            $this->free();
        }
        $this->close();
    }


	/**以下函数，需要在子类根据不同的数据库完成。*/
	protected function connect($config=[],$linkNum=0){unset($config);return $linkNum;}//连接数据库，$linkNum，连接编号
	protected function parseLimit($limit){return $limit;}//limit分析
	protected function escapeString($str){return addslashes($str);}//SQL指令安全过滤
	public function execute($sql){return $sql;}//执行SQL语句。
	public function query($sql){return $sql;}//执行SQL语句。
    protected function close(){}// 关闭数据库 由驱动类定义
	protected function free(){}//  释放查询结果 由驱动类定义
	public function startTrans($name=''){}
	public function commit($name=''){}
	public function rollback(){}
}