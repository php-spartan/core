<?php
namespace Demo\Controller;
use Demo\Common\Control;

!defined('APP_PATH') && exit('404 Not Found');

class IndexController extends Control {

    public function index(){
        if (isAjax()){
            $this->ajaxMessage('登录超时。',99);
        }else{
            $this->display();
        }
    }
}