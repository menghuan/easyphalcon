<?php
namespace Common\Services\SmsPlatForm;
require '../app/common/library/vendor/PhpCurl/vendor/autoload.php';
use Common\Models\SmsPlatForm\Whitelist;

/**
 * 管理员
 * @author wangjianghua
 * @date 2018-2-25 11:25:22
 */
class WarningService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取列表数据 分页代码
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function sendWarningSms($message = '')
    {
        //进程报警接口
        $url = '10.18.99.242:8084';
        $data = [
            'service'=>'短信平台',
            'reason'=>$message,
            'mobile'=>15210089171,
            'email'=>'suyunlei@qiaodata.com'
        ];
        $curl = new \Curl\Curl();
        $curl->post($url,$data);
    }
}
