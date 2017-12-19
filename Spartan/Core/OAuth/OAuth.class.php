<?php
namespace Spartan\Core;

defined('APP_PATH') OR exit('404 Not Found');
/**
    * @description 
    * @author singer
    * @version v1
    * @date 2015/4/27 15:36
*/
class OAuth {
	/** @var $clientHandle \Spartan\Driver\Http\SmallCurl */
	private $clientHandle = null;
	private $config = [];
	private $token = null;//token或code,下一步请求access_token需要的
	private $access_token = null;
	private $baseConfig = [];
	private $redirect_uri = 'http://www.jerseys.com.hk/callback/';
	static $arrServer = Array(
		'GOOGLE'=>Array(
			'auth_url'=>'https://accounts.google.com/o/oauth2/auth?',
			'token_url'=>'https://accounts.google.com/o/oauth2/token',
			'api_url'=>'https://www.googleapis.com/oauth2/v1/',
		    'token_name'=>'code',
		    'access_token_name'=>'access_token',
		    'user_info_url'=>'https://www.googleapis.com/plus/v1/people/me',
		),
		'FACEBOOK'=>Array(

		),
	);

	/**
	 * 取得数据库类实例
	 * @param $name string 名称
	 * @return OAuth 返回数据库驱动类
	 */
	public static function instance($name){
		if (!C("OAUTH.$name") || !isset(self::$arrServer[$name])){
			return null;
		}else{
			return \St::getInstance('Spartan\\Core\\OAuth',$name);
		}
	}
    /*
     * 初始化
     */
	public function __construct($name){
		$this->config = C("OAUTH.$name");
		$this->baseConfig = self::$arrServer[$name];
		$this->redirect_uri .= strtolower($name);
	}
	/*
	 * 取得一个CURL对像
	 */
	private function getClient(){
		if (!$this->clientHandle){
			$this->clientHandle = \St::getInstance('Spartan\\Driver\Http\\SmallCurl',[]);
		}
		return $this->clientHandle;
	}

	/**
	 * 生成生成授权网址
	 * @return string
	 */
	public function loginUrl(){
		$params = Array(
			'response_type'=>'code',
			'client_id'=>$this->config['client_id'],
			'redirect_uri'=>$this->redirect_uri,
			'scope'=>$this->config['scope'],
			'state'=>'profile',
			'access_type'=>$this->config['access_type']?$this->config['access_type']:'offline',
		);
		return $this->baseConfig['auth_url']. http_build_query($params);
	}

	public function checkToken(){
		$this->token = trim(I($this->baseConfig['token_name'],''));
		return !$this->token?false:true;
	}
	//获取access token
	public function getAccessToKen(){
		$params = Array(
			'grant_type'=>'authorization_code',
			'code'=>$this->token,
			'client_id'=>$this->config['client_id'],
			'client_secret'=>$this->config['client_secret'],
			'redirect_uri'=>$this->redirect_uri,
		);
		$result = $this->getClient()->send($this->baseConfig['token_url'],http_build_query($params),'POST');

        if (isset($result[$this->baseConfig['access_token_name']])){
            return $result[$this->baseConfig['access_token_name']];
        }else{
            print_r($result);
            return '';
        }
	}
	//获取登录用户信息
	public function userInfo(){
		$params = Array();
		return $this->callAPI($this->baseConfig['user_info_url'], $params);
	}
	/**
	 * 示例：获取登录用户信息 $result=$google->api('userinfo', array(), 'GET');
	 * @param $url
	 * @param array $params
	 * @param string $method
	 * @param array $headers
	 * @return null
	 */
	public function callAPI($url,$params=[],$method='GET',$headers=[]){
		$this->checkToken();
        if (!$this->access_token){
			$this->access_token = $this->getAccessToKen();
		}
		if(!$this->access_token){
			exit('access token fail.');
		}
		$url = $this->config['api_url'] . $url;
		$headers[]='Authorization: Bearer '.$this->access_token;
		!isset($params['access_token']) && $params['access_token'] = $this->access_token;
		$method=='GET' && $url .= '?'.http_build_query($params);
		//print_r($url);
		$result = $this->getClient()->send($url,http_build_query($params),$method,$headers);
		print_r($result);
		return $result;
	}
} 