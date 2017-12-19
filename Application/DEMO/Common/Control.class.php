<?php
namespace Demo\Common;
use Spartan\Core\Controller\Controller;

!defined('APP_PATH') && exit('404 Not Found');
/**
 * @description
 * @author singer
 * @date 15-4-2 下午2:27
 */
abstract class Control extends Controller {
	protected $userInfo = null;
	//protected $rpcClient = null;

    public function __construct($startSession=true){
        $strPhpId = trim(I('param.php_id'));
        mb_strlen($strPhpId,'utf-8') > 20 && $this->sessionId = $strPhpId;
        parent::__construct(true);
        $this->userInfo = session('?user_info')?session('user_info'):['id'=>0];
    }

} 