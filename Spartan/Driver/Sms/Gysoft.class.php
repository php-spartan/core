<?php
namespace Spartan\Extend\SmsSender;
use Spartan\Extend\SmsSender;
defined('APP_PATH') OR exit('404 Not Found');

class Gysoft extends SmsSender{
	/** 初始化 */
	public function __construct($config=''){
		$this->server = 'www.gysoft.cn';
		$this->userName = 'jinyudai';
		$this->passWord = 'admin535000';
		$this->port = '80';
		$this->uri = '/smspost_utf8/send.aspx';
		$this->intervalTime = 3;
		$this->charset = 'UTF-8';
	}
	/**
	 * 发送动作
	 * @return bool
	 */
	public function send(){
		if (!$this->mobile || !$this->body) {
			$this->errors[] = '手机号码或内容为空。';
			return false;
		}
		//DEBUG 去掉后为正常发送
		$result = "OK2";
		$this->result[$this->mobile[0]] = Array($result,substr($result,0,2)!='OK'?false:true);
		return $this->result;
		//DEBUG

		$handle = fsockopen($this->server,$this->port, $error_number, $error_message, 30);
		if (!$handle){
			$this->errors[] = $this->server.'打开失败:'.$error_message;
			return false;
		}
		foreach($this->mobile as $v) {
			$strMessageInfo = "username=".$this->userName."&password=".$this->passWord.
				"&mobile={$v}&content=".$this->body."";

			$header = "POST ".$this->uri." HTTP/1.1\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Host: ".$this->server."\r\n";
			$header .= "Content-Length: ".strlen($strMessageInfo)."\r\n";
			$header .= "Connection: Close\r\n\r\n";
			$header .= $strMessageInfo;
			fwrite($handle, $header);
			$result = '';
			$inHeader = 1;
			while (!feof($handle)){
				$result = fgets($handle,1024); //去除请求包的头只显示页面的返回数据
				if ($inHeader && ($result == "\n" || $result == "\r\n")) {
					$inHeader = 0;
				}
			}
			$this->result[$v] = Array($result,substr($result,0,2)!='OK'?false:true);
			count($this->mobile) > 1 && sleep($this->intervalTime);
		}
		fclose($handle);
		return $this->result;
	}
}