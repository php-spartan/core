<?php
namespace Spartan\Driver\Image;
/**
 * @description
 * @author singer
 * @version v1
 * @date 14-6-17 上午10:34
 */
defined('APP_PATH') OR exit('404 Not Found');

class ImageVerify {
	/**
	 * 取得当前例实例
	 * @param array $config
	 * @return ImageVerify
	 */
	public static function instance($config=Array()) {
		return \St::getInstance('Spartan\\Driver\\Image\\ImageVerify',$config);
	}

	public function verify($config=Array()) {
		$width = isset($config['width'])?$config['width']:60;
		$height = isset($config['height'])?$config['height']:28;
		$verifyName = isset($config['verifyCode'])?$config['verifyCode']:'verifyCode';
		$length = isset($config['length'])?$config['length']:4;
		$mode = isset($config['mode'])?$config['mode']:1;
		$type = isset($config['type'])?$config['type']:'png';
		$randval = $this->getText($mode,$length);
		session($verifyName, $randval);
		$width = ($length * 10 + 10) > $width ? $length * 10 + 10 : $width;
		if ($type != 'gif' && function_exists('imagecreatetruecolor')) {
			$im = imagecreatetruecolor($width, $height);
		} else {
			$im = imagecreate($width, $height);
		}
		$r = Array(225, 255, 255, 223);
		$g = Array(225, 236, 237, 255);
		$b = Array(225, 236, 166, 125);
		$key = mt_rand(0, 3);

		$backColor = imagecolorallocate($im, $r[$key], $g[$key], $b[$key]);    //背景色（随机）
		$borderColor = imagecolorallocate($im, 100, 100, 100);                    //边框色
		imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $backColor);
		imagerectangle($im, 0, 0, $width - 1, $height - 1, $borderColor);
		$stringColor = imagecolorallocate($im, mt_rand(0, 200), mt_rand(0, 120), mt_rand(0, 120));
		// 干扰
		for ($i = 0; $i < 10; $i++) {
			imagearc($im,mt_rand(-10, $width), mt_rand(-10, $height), mt_rand(30, 300), mt_rand(20, 200), 55, 44, $stringColor);
		}
		for ($i = 0; $i < 25; $i++) {
			imagesetpixel($im, mt_rand(0, $width), mt_rand(0, $height), $stringColor);
		}
		for ($i = 0; $i < $length; $i++) {
			imagestring($im, 5, $i * 10 + 5, mt_rand(1, 8), $randval{$i}, $stringColor);
		}
		$this->output($im, $type);

	}

	private function output($im, $type='png', $filename=''){
		header("Content-type: image/" . $type);
		$ImageFun = 'image' . $type;
		if (empty($filename)) {
			$ImageFun($im);
		} else {
			$ImageFun($im, $filename);
		}
		imagedestroy($im);
	}

	/**
	 * 取得字符串
	 * @param $textType 1为数字，2为文本
	 * @param $textCount
	 * @return string
	 */
	private function getText($textType,$textCount){
		$text = '';
		if($textType==1){
			for($i=0;$i<$textCount;$i++){
				$text .= chr(mt_rand(48,57));
			}
		}elseif($textType==2){
			for($i=0;$i<$textCount;$i++){
				$text .= chr(mt_rand(97,122));
			}
		}
		return strtolower($text);
	}
} 