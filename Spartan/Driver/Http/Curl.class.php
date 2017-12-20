<?php
namespace Spartan\Driver\Http;

defined('APP_PATH') OR exit('404 Not Found');
/**
 * @description
 * @author singer
 * @version v1
 * @date 14-6-11 下午3:30
 */

class Curl{
	private $cookies = null;
	private $curlHandle = null;
	private $content = null;
	private $headers = null;
	private $config = [];

	public function __construct($config=null){
		!function_exists('curl_init') && die('CURL_INIT_ERROR');
		$config = array_change_key_case($config,CASE_LOWER);
		!isset($config['ssl']) && $config['ssl'] = false;
		!isset($config['access_agent']) && $config['access_agent'] = '/Spartan V5.42/';
		!isset($config['port']) && $config['port'] = '80';
		!isset($config['user_key']) && $config['user_key'] = 'who';
		!isset($config['pass_key']) && $config['pass_key'] = 'noPass';
		!isset($config['auth_way']) && $config['auth_way'] = 'Basic';
		!isset($config['accept']) && $config['accept'] = '';
		!isset($config['content_type']) && $config['content_type'] = '';
		!isset($config['content_length']) && $config['content_length'] = false;
		$this->config = $config;
		$this->init();
	}

	public function setConfig($config=null){
		$config = array_change_key_case($config,CASE_LOWER);
		$config && $this->config = array_merge($this->config,$config);
		$this->init();
	}

	public function resetConfig($config=null){
		$this->config = [];
		$this->setConfig($config);
	}

	public function getConfig(){
		return $this->config;
	}

	public function setCookies($fileName){
		$this->cookies = $fileName;
	}

	private function init(){
		$this->curlHandle = curl_init();
		$options = array(
			CURLOPT_RETURNTRANSFER => true,//结果为文件流
			CURLOPT_TIMEOUT => 15,//超时时间，为秒。
			CURLOPT_HEADER => true,//是否需要头部信息，如果去掉头部信息，请求会快很多。
			CURLOPT_PORT => $this->config['port'],//请求的端口
			CURLOPT_FRESH_CONNECT => true,//每次请求都是新的，不缓存
			CURLINFO_HEADER_OUT => true,//启用时追踪句柄的请求字符串。
			CURLOPT_FORBID_REUSE => true,//在完成交互以后强迫断开连接，不能重用
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,//强制使用 HTTP/1.1
			CURLOPT_POST => true,//使用POST提交
			CURLOPT_USERAGENT => $this->config['access_agent'],//使用的浏览器
			CURLOPT_ENCODING => 'gzip,deflate',//是否支持压缩
			CURLOPT_HTTPHEADER => array('Expect:100-continue'),//大于1024K
			CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
		);
		foreach ($options as $key => $value){
			curl_setopt($this->curlHandle,$key,$value);
		}
	}

	/**
	 * 关闭本次请求
	 */
	public function close(){
		curl_close($this->curlHandle);
		$this->init();
	}

	/**
	 * 提交请请求。
	 * @param array $data
	 * @param string $postField 提交的內容字段
	 * @return null
	 */
	public function doPost($data=[],$postField='data'){
		if(!isset($data['_url_'])){return null;}
		$url = sprintf('http%s://%s:%s%s',($this->config['ssl']==true?'s':''),$this->config['host_ip'],$this->config['port'],$data['_url_']);
		$arrHead = $this->buildHeard($data['_url_']);
		unset($data['_url_']);
		if ($this->config['ssl']==true){
			curl_setopt($this->curlHandle,CURLOPT_SSL_VERIFYPEER, false); //不验证证书下同
			curl_setopt($this->curlHandle,CURLOPT_SSL_VERIFYHOST, false); //
		}
		if($postField=='data'){
			$strData = 'data='.urlencode(json_encode($data,JSON_UNESCAPED_UNICODE));
		}else{
			$strData = json_encode($data,JSON_UNESCAPED_UNICODE);
		}
		$this->config['content_length']==true && $arrHead[] = "Content-Length: ".strlen($strData);
		if ($this->cookies){
			curl_setopt($this->curlHandle, CURLOPT_COOKIEJAR, $this->cookies);
			is_file($this->cookies) && curl_setopt($this->curlHandle, CURLOPT_COOKIEFILE, $this->cookies);
		}
		curl_setopt($this->curlHandle,CURLOPT_HTTPHEADER,$arrHead);
		curl_setopt($this->curlHandle,CURLOPT_URL,$url);
		curl_setopt($this->curlHandle,CURLOPT_POSTFIELDS ,$strData);
		$data = explode("\r\n\r\n",curl_exec($this->curlHandle));
		//$requestInfo = curl_getinfo($this->curlHandle);
		count($data) > 2 && array_shift($data);
		$this->headers = $data[0];
		$this->content = $data[1];
		return $this->content;
	}
	/**
	 * 设置请求的头部，如果不设置头部，cURL的效率会好很多，因为以下几点原因，在使用：
	 * 1）要设置HOST，因为是使用内网IP访问，无法使用HOST指向。
	 * ２）需要用到Authorization验证。
	 * 3）要设置COOKIES。
	 * @param $url
	 * @return array
	 */
	private function buildHeard($url) {
		$header = Array();
		$header[] = "POST {$url} HTTP/1.1";
		$header[] = "Host: " . $this->config['host'];
		$this->config['accept'] && $header[] = "Accept: " . $this->config['accept'];
		$this->config['content_type'] && $header[] = "Content-Type: " . $this->config['content_type'];
		$header[] = "application/x-www-form-urlencoded; charset=utf-8";
		$header[] = "Accept-language: zh-CN,zh;q=0.8,en;q=0.6,ja;q=0.4,da;q=0.2";
		$header[] = "Authorization: ".$this->config['auth_way'].' '.base64_encode($this->config['user_key'].":".$this->config['pass_key']);
		return $header;
	}

	public function __destruct(){
		$this->close();
		$this->curlHandle = null;
	}
}