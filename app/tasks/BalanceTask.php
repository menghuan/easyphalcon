<?php
require dirname(__DIR__).'/common/library/vendor/PhpCurl/vendor/autoload.php';
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line as LineFormatter;

use Common\Services\SmsPlatForm\ChannelApiService;
use Common\Services\SmsPlatForm\ChannelService;
use Common\Services\SmsPlatForm\ParamFactoryService;
use Common\Services\SmsPlatForm\ParseResultFactoryService;
/**
 * 发送短信
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-3-2 11:25:11
 */
class BalanceTask
{
    /**
     * 配置信息
     * @var type
     */
    private $config = null;

    private $startTime = 0;

    public function __construct()
    {
        $this->startTime = time();
        $this->config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取黑名单有效期。;
    }

    /**
     * 查询通道余额
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-9-15
     * @return null;
     */
    public function getBalanceAction()
    {
        //获取需要查询余额的通道id
        $channels = $this->config->getBalanceChannel;
        $channelService = new ChannelService();
        $channelApiService = new ChannelApiService();
        $curl = new \Curl\Curl();
        if(!empty($channels)) {
            foreach ($channels as $c) {
                //获取通道信息
                $channel = $channelService->getByPrimaryKey($c);
                //获取Api信息
                $channelApiInfo = $channelApiService->getChannelApiByType($c, 3);
                if (!empty($channelApiInfo)) {
                    //组装API参数
                    $parameterService = ParamFactoryService::createParamServiceInstance($channel);
                    $param = $parameterService->createBalanceParam($channelApiInfo->id);
                    switch ($channelApiInfo->method) {
                        case 1:
                            $response = $curl->get($channelApiInfo->url, $param);
                            break;
                        case 2:
                            $response = $curl->post($channelApiInfo->url, $param);
                            break;
                    }
                    //解析返回的信息
                    $this->parseResultService = ParseResultFactoryService::createParseServiceInstance($channel);
                    $result = $this->parseResultService->parseBalance($response);
                    //修改通道表余额信息
                    $channelService->updateByPrimaryKey($c, ['balance' => $result['balance'], 'update_time' => $_SERVER['REQUEST_TIME']]);
                }
            }
        }
        exit('查询完毕，本次更新'.count($channels).'条通道余额');
    }
}
