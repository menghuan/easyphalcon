<?php
require dirname(__DIR__).'/common/library/vendor/PhpCurl/vendor/autoload.php';
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line as LineFormatter;

use Common\Services\SmsPlatForm\StatisticsDayService;
use Common\Services\SmsPlatForm\StatisticsHourService;
use Common\Services\SmsPlatForm\SendTaskDetailService;
use Common\Services\SmsPlatForm\ChannelService;
use Common\Services\SmsPlatForm\SendingNumberService;

/**
 * 发送短信
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-6-13
 */
class WarningTask
{
    /**
     * 配置信息
     * @var type
     */
    private $config = null;

    public function __construct()
    {
        $this->config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取黑名单有效期。;
    }

    /**
     * 发送短信每小时提交短信通道成功率，对应预警条件 营销短信1小时内后，channel_task_id字段30%为空
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-07-18
     * @return null;
     */
    public function submitSuccessPercentAction()
    {
        $statisticsHourService = new StatisticsHourService();
        $startTime = strtotime(date('Y-m-d H',$_SERVER['REQUEST_TIME']-3600).':00:00')-1;
        $where = ['type'=>1];
        $data = $statisticsHourService->getByHourTimes($startTime, $startTime+3600, $where, ['sum(received_number) as received_number','sum(real_send_number) as real_send_number']);
        $timeStr = date('Y-m-d H:00', $startTime + 1) . '-' . date('H:00', $startTime + 1 + 3600);
        if(!empty($data[0]->received_number)) {
            $percent = intval((1-($data[0]->real_send_number / $data[0]->received_number)) * 100);
            $response = $this->sendToMonitor(3, $timeStr, $percent);
            exit($timeStr.'本时段下发失败率：'.$percent.'发送预警平台完毕,预警平台返回结果'.$response.PHP_EOL);
        }else{
            exit($timeStr.'本时段无发送数据'.PHP_EOL);
        }
    }

    /**
     * 提交短信成功率 短信发送2小时后，成功率低于70%
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-07-18
     * @return null;
     */
    public function sendSuccessPercentAction()
    {
        $statisticsHourService = new StatisticsHourService();
        $startTime = strtotime(date('Y-m-d H',$_SERVER['REQUEST_TIME']-3600*2).':00:00')-1;
        $where = ['type'=>1];
        $data = $statisticsHourService->getByHourTimes($startTime, $startTime+2*3600, $where, ['sum(succeed_number) as succeed_number','sum(real_send_number) as real_send_number']);
        $timeStr = date('Y-m-d H:00', $startTime + 1) . '-' . date('H:00', $startTime + 1 + 7200);
        if(!empty($data[0]->real_send_number)) {
            $percent = intval(($data[0]->succeed_number / $data[0]->real_send_number) * 100);
            $response = $this->sendToMonitor(4, $timeStr, $percent);
            exit($timeStr.'本时段发送成功率：'.$percent.'发送预警平台完毕,预警平台返回结果'.$response.PHP_EOL);
        }else{
            exit($timeStr.'本时段无发送数据'.PHP_EOL);
        }
    }

    /**
     * 提交短信成功率 短信发送2小时后，成功率低于70%
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-07-18
     * @return null;
     */
    public function daySuccessPercentAction()
    {
        $statisticsHourService = new StatisticsDayService();
        $timeStr = strtotime(date('Y-m-d',$_SERVER['REQUEST_TIME']));
        $data = $statisticsHourService->getByDayTimestamp($timeStr, 1);
        if(!empty($data->real_send_number)) {
            $percent = intval(($data->succeed_number / $data->real_send_number) * 100);
            $response = $this->sendToMonitor(5, date('Y-m-d H:00',$_SERVER['REQUEST_TIME']), $percent);
            exit(date('Y-m-d H:i:s').'本时段发送成功率：'.$percent.'发送预警平台完毕,预警平台返回结果'.$response.PHP_EOL);
        }else{
            exit(date('Y-m-d H:i:s').'本时段无发送数据'.PHP_EOL);
        }
    }

    /**
     * 扫描表内，3分钟还没有回状态的
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-08-01
     */
    public function triggerSmsBackAction()
    {
        //一小时内，单一通道只发送一次预警，单次任务查出多条通道，发送风多次。
        $sendTaskDetailService = new SendTaskDetailService();
        $timeStr = strtotime(date('Y-m-d H:i',$_SERVER['REQUEST_TIME']))-240;
        $condition = [
            'conditions'=>'send_time>=:stime: and send_time<:etime: and type=:type: and back_time=:back_time:',
            'bind'=>[
                'stime'=>$timeStr,
                'etime'=>$timeStr+60,
                'type'=>2,
                'back_time'=>0
            ]
        ];
        $detailInfo = $sendTaskDetailService->getInfoByCondition($condition)->toArray();
        if(!empty($detailInfo)) {
            $channelId = array_column($detailInfo, 'channel_id');
            $channelService = new ChannelService();
            $channelInfo = $channelService->getByPrimaryKeyList($channelId);
            if(!empty($channelInfo)){
                foreach ($channelInfo as $c) {
                    $sendingNumberService = new SendingNumberService();
                    $warningSendNumber = $sendingNumberService->getWarningSendingNumber($c->id);
                    if (!$warningSendNumber) {
                        $sendingNumberService->incrWarningSendingNumber($c->id);
                        $this->sendToMonitor(7, '', '', $c->name);
                    }
                }
            }
            exit(date('Y-m-d H:i').'本时段触发短信无状态预警处理完毕'.PHP_EOL);
        }else{
            exit(date('Y-m-d H:i').'本时段触发短信无预警数据'.PHP_EOL);
        }
    }

    /**
     * 给预警平台发送信息
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-07-18
     * @return;
     */
    private function sendToMonitor($indexId = 0, $time = '', $value = '',$product = '',$project = '')
    {
        //给预警平台发送请求
        $curl = new \Curl\Curl();
        $url =  $this->config->monitorUrl;
        $data = array(
            "index_id"=>$indexId,
            "time"=>$time,
            "value"=>$value,
            "product"=>$product,
            "project"=>$project
        );
        $response = $curl->post($url,$data);
        return $response;
    }
}
