<?php
namespace Demo\Common;

!defined('APP_PATH') && exit('404 Not Found');
/**
 * @description
 * @author singer
 * @date 15-4-2 下午2:27
 */
abstract class UserControl extends Control {

	public function __construct(){
		parent::__construct(true);
		if ($this->userInfo['id'] < 1){
		    if (isAjax()){
		        $this->ajaxMessage('登录超时。',99);
            }else{
                redirect('/');
            }
        }
	}

} 