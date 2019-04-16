<?php
require "/opt/www/sms_platform_2.0.0/app/common/library/vendor/PhpCurl/vendor/autoload.php";
use Phalcon\Config\Adapter\Php as ConfigPhp;

use Common\Services\SmsPlatForm\ChannelApiService;
use Common\Services\SmsPlatForm\ChannelApiParamService;
use Common\Services\SmsPlatForm\StatisticsDayService;
/**
 * 拉取短信下发状态
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-6-27
 */
class GetsendstatusTask
{
    /**
     * 配置信息
     * @var type
     */
    private $config = null;
    /**
     * 运行日志
     * @var array
     */
    private $runLog = [
        'error_code' => 0,
        'message' => '',
        'task' => '', //发送任务json数据
        'send_result' => [],
        'run_time' => 0, //运行时间，单位秒。
    ];

    public function __construct()
    {
        $this->startTime = time();
        $this->config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取黑名单有效期。;
    }

    /**
     * 获取短信下发状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-29
     * @return null;
     */
    public function sendAction()
    {
        $statisticsDayService = new StatisticsDayService();
        $dayTimeStamp = strtotime(date('Y-m-d',$_SERVER['REQUEST_TIME']));
        $dayInfo = $statisticsDayService->getByDayTimestamp($dayTimeStamp,2);
        if(empty($dayInfo) || ($dayInfo->real_send_number - $dayInfo->succeed_number - $dayInfo->failed_number) == 0){
            exit('本次无需获取下发状态');
        }
        $channelId = json_decode(json_encode($this->config->getStatusChannel),true);
        //获取拉取状态的api地址
        $channelApiService = new ChannelApiService();
        $channelApiInfo = $channelApiService->getChannelApiByType($channelId,4);
        $apiIds = $paramArr = [];
        if(!empty($channelApiInfo)) {
            foreach ($channelApiInfo as $apiInfo) {
                $apiIds[] = $apiInfo->id;
                $paramArr[$apiInfo->id]['url'] = $apiInfo->url;
                $paramArr[$apiInfo->id]['channel'] = $apiInfo->channel_id;
            }
            $channelParamService = new ChannelApiParamService();
            $paramsInfo = $channelParamService->getByApiIds($apiIds);
            if (!empty($paramsInfo)) {
                foreach ($paramsInfo as $k => $param) {
                    $paramArr[$param->api_id][$param->param_key] = $param->param_value;
                }
            }
        }
        //给通道发送请求
        $curl = new \Curl\Curl();
        if(!empty($paramArr)) {
            foreach ($paramArr as $p) {
                $url = $p['url'];
                $response = $curl->post($url, $p);
                if (empty($response)) {
                    echo date('Y-m-d H:i:s').'本次无数据,通道APIID：'.$p['api_id'].PHP_EOL;
                }else {
                    $handleUrl = $this->config->baseUrl . '/replay/setStatusTrigger/' . $p['channel'];
                    $curl->post($handleUrl, ['response' => $response]);
                }
            }
        }
        exit('本次拉取完毕');
    }
}
