<?php
namespace Spartan\Extend;
if(!defined('APP_PATH')){exit('404 Not Found');}

class Attachment {
    /**
     * @return \Spartan\Extend\Attachment
     */
    public static function instance() {
        return \Spt::getInstance(__CLASS__);
    }

    public function savePicture($strContent,$strPath){
        if (!$strContent||!$strPath)
        {
            return Array('目录或者内容为空。',0);
        }
        $strContent = base64_decode(str_replace('-','+',$strContent));
        if (!$strContent)
        {
            return Array('文件内容不是base64。',0);
        }
        $arrResult = $this->writeFile($strPath,$strContent);

        if ($arrResult[1]!=1)
        {
            return Array($arrResult[0],0);
        }
        return Array('保存成功。'.$strPath,1,$arrResult);
    }
    /**
     * 向目标文件写入指定内容
     * @param string $filename = 要写入的文件路径
     * @param string $content = 要写入的内容
     * @param string $type = 写入模式 r只读-指向文件头 r+读写-指向文件头 w写入-清空并指向文件头+创建 w+读写-清空并指向文件头 a写入-指向文件末尾+创建 a+读写-指向文件末尾 x写入-指向文件头-文件不能存在 x+ 读写-指向文件头-文件不能存在
     * @param bool $check true为自动建立不存在的目录,false为手动建立不存在的目录
     * @return bool
     */
    public function writeFile($filename, $content, $type = 'w+', $check = true){
        if($check == true && !$this->createDir(@dirname($filename))) {
            return Array("建立目录{$filename}失败。",0);
        }
        if($handle = @fopen($filename, $type)){
            @fwrite($handle, $content);
            @fclose($handle);
            @chmod($filename, 0777);
            return Array("写入完成{$filename}。",1);
        }
        else {
            return Array("文件{$filename}写入失败。",0);
        }
    }

    /**
     * 循环建立目录
     * @param string $dir 要建立的目录
     * @return bool
     */
    public function createDir($dir){
        if (!@is_dir($dir)) {
            if(!mkdir($dir, 0777,true))
            {
                return Array("目录{$dir}权限不可写。",0);
            }
            //建立默认文档，防止目录浏览功能
            if(!@touch($dir . '/index.html'))
            {
                return Array("建立index文档出错。",0);
            }
        }
        return Array('建立完成。',1);
    }

    /**
     * 取得文件名后缀
     * @param string $filename 文件路径
     * @return mixed
     */
    public function getExt($filename){
        $pathInfo = pathinfo($filename);
        return isset($pathInfo['extension']) ? $pathInfo['extension'] : null;
    }
} 