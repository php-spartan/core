<?php
namespace Spartan\Driver\Http;

defined('APP_PATH') OR exit('404 Not Found');
/**
 * @description
 * @author singer
 * @version v1
 * @date 14-6-11 下午3:30
 */

class SmallCurl{
	private $curlHandle = null;
	private $content = null;
	private $headers = null;
	private $config = [];
	private $openCookie = false;//是否开启COOKIES
	private $cookies = '';//开启COOKIES时的变量

	public function __construct($config=null){
		!function_exists('curl_init') && die('CURL_INIT_ERROR');
		$config = array_change_key_case($config,CASE_LOWER);
		!isset($config['access_agent'])&&$config['access_agent']='User-Agent:'.DOMAIN.' OAuth2.0;';
		$this->config = $config;
		$this->init();
	}
	private function init(){
		$this->curlHandle = curl_init();
		$options = array(
			CURLOPT_RETURNTRANSFER => true,//结果为文件流
			CURLOPT_TIMEOUT => 30,//超时时间，为秒。
			CURLOPT_HEADER => true,//是否需要头部信息，如果去掉头部信息，请求会快很多。
			CURLOPT_FRESH_CONNECT => true,//每次请求都是新的，不缓存
			CURLINFO_HEADER_OUT => true,//启用时追踪句柄的请求字符串。
			CURLOPT_FORBID_REUSE => true,//在完成交互以后强迫断开连接，不能重用
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,//强制使用 HTTP/1.1
			CURLOPT_USERAGENT => $this->config['access_agent'],//使用的浏览器
			CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1',//使用的浏览器
			CURLOPT_ENCODING => 'gzip,deflate',//是否支持压缩
			CURLOPT_HTTPHEADER => array('Expect:100-continue'),//大于1024K
			CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
		);
		if (isset($this->config['referer']) && $this->config['referer']){
		    $options['CURLOPT_REFERER'] = $this->config['referer'];
        }
		foreach ($options as $key => $value){
			$this->setOpt($key,$value);
		}
	}

	public function setOpt($key,$value){
        curl_setopt($this->curlHandle,$key,$value);
        return $this;
    }
    public function startCookie($cookies='not null'){
        $this->openCookie = true;
        $cookies != 'not null' && $this->cookies = $cookies;
        if ($this->openCookie && $this->cookies){
            $this->setOpt(CURLOPT_COOKIE,$this->cookies);
        }
        return $this;
    }
    public function getCookie(){
        return $this->cookies;
    }
	/**
	 * 关闭本次请求
	 */
	public function close(){
		curl_close($this->curlHandle);
		$this->init();
	}

	/**提交请请求。
	 * @param $url
	 * @param string $postFields
	 * @param string $method
	 * @param array $headers
	 * @return null
	 */
	public function send($url,$postFields='',$method='GET', $headers=[]){
		if($method=='POST'){
			curl_setopt($this->curlHandle, CURLOPT_POST, TRUE);
			if($postFields!=''){
				curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $postFields);
			}
		}else{
			curl_setopt($this->curlHandle, CURLOPT_POST, false);
		}
		if (stripos($url,'https://')===0){
			curl_setopt($this->curlHandle,CURLOPT_SSL_VERIFYPEER, false); //不验证证书下同
			curl_setopt($this->curlHandle,CURLOPT_SSL_VERIFYHOST, false); //
		}
        $headers && curl_setopt($this->curlHandle,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($this->curlHandle,CURLOPT_URL,$url);
		$data = explode("\r\n\r\n",curl_exec($this->curlHandle));
		foreach ($data as $v){
            $this->headers = array_shift($data);
            if (stripos($this->headers,'HTTP/1.1 200 OK')===0 || stripos($this->headers,'Content-Type:')>0){break;}
        }
        $this->content = implode("\r\n\r\n",$data);
        //$requestInfo = curl_getinfo($this->curlHandle);print_r($requestInfo);die();
		if(stripos($this->headers,'charset=GBK')!==false){
            $this->content = iconv('GBK','utf-8//IGNORE',$this->content);
        }
        if ($this->openCookie){
            preg_match("/set\-cookie:([^\r\n]*)/i", $this->headers, $matches);
            (isset($matches[1]) && $matches[1]) && $this->cookies = $matches[1];
        }
		$arrJson = json_decode($this->content,true);
		return !$this->content?[]:(!$arrJson?$this->content:$arrJson);
	}

	public function __destruct(){
		$this->close();
		$this->curlHandle = null;
	}
}