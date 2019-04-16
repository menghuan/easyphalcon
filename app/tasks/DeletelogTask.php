<?php
require dirname(__DIR__).'/common/library/vendor/PhpCurl/vendor/autoload.php';
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line as LineFormatter;

use Common\Services\SmsPlatForm\SendStatusLogService;

/**
 * 删除无用的日志记录， 保留60天内的记录信息
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-10-17
 */
class DeletelogTask
{
    /**
     * 配置信息
     * @var type
     */
    private $config = null;
    private $startTime = 0;

    public function __construct()
    {
        $this->config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取黑名单有效期。;
        $this->startTime = $_SERVER['REQUEST_TIME']-$this->config->logDays*86400;
    }

    /**
     * 删除发送状态推送日志
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-10-17
     */
    public function delSendStatusLogAction()
    {
        $sendStatusLogService = new SendStatusLogService();
        $checkInfo = $sendStatusLogService->getCountByTime(0, $this->startTime)->toArray();
        if($checkInfo[0]) {
            $delNum = ceil($checkInfo[0] / 1000);
            if (!empty($delNum)) {
                for ($i = 0; $i < $delNum; $i++) {
                    $delResult = $sendStatusLogService->deleteByTime(0, $this->startTime);
                    echo '删除结果：'.$delResult.PHP_EOL;
                }
            }
            exit(date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME']).'：删除完毕，共删除'.$checkInfo[0].'条'. PHP_EOL);
        }else{
            exit(date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME']).'：删除完毕,本次无删除数据'.PHP_EOL);
        }
    }
}
