<?php
namespace Spartan\Extend;

defined('APP_PATH') OR exit('404 Not Found');
/**
 * @description
 * @author singer
 * @version v1
 * @date 14-7-14 下午3:09
 */
class Mailer {
	/**
	 * 取得数据库类实例
	 * @param array $config
	 * @return \Spartan\Extend\Mailer\SmtpMailer
	 */
	public static function instance($config=Array()) {
		$config = array_merge(C('EMAIL'),$config);
		$strMailer = isset($config['MAILER'])?$config['MAILER']:'SmtpMailer';
		return \Spt::getInstance('Spartan\\Extend\\Mailer\\'.$strMailer,$config);
	}
} 