<?php
namespace Spartan\Extend\SmsSender;
use Spartan\Extend\SmsSender;
defined('APP_PATH') OR exit('404 Not Found');

class Entinfo extends SmsSender{
	/** 初始化 */
	public function __construct($config=''){
		$this->server = 'sdk.entinfo.cn';
		$this->userName = 'SDK-WSS-010-05954';//SDK-BJR-010-00689，序号：7
		$this->passWord = '32dca-Ef';//4a584-f7
		$this->port = '8060';
		$this->uri = '/webservice.asmx/mdSmsSend_u';
		$this->intervalTime = 3;
		$this->charset = 'GBK';
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
		$handle = fsockopen($this->server,$this->port, $error_number, $error_message, 30);
		if (!$handle){
			$this->errors[] = $this->server.'打开失败:'.$error_message;
			return false;
		}
		foreach($this->mobile as $v) {
            $this->result[$v] = true;//DEBUG 去除该行为真正发送
			$strMessageInfo = "SN=".$this->userName."&PWD=".
				strtoupper(md5($this->userName.$this->passWord)).
				"&Mobile={$v}&Content=".$this->body."&ext=&stime=&rrid=";

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
			$result = str_replace("<string xmlns=\"http://tempuri.org/\">","",$result);
			$result = str_replace("</string>","",$result);
			$this->result[$v] = Array($result,stripos($result,'-')>0?false:true);//得到干净的内容
			count($this->mobile) > 1 && sleep($this->intervalTime);
		}
		fclose($handle);
		return $this->result;
	}
}