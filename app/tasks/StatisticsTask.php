<?php
use Phalcon\Cli\Task;
use Phalcon\Config\Adapter\Php as ConfigPhp;

use Common\Services\SmsPlatForm\SendTaskDetailService;
use Common\Services\SmsPlatForm\SendTaskService;
use Common\Services\SmsPlatForm\StatisticsDetailService;
use Common\Services\SmsPlatForm\StatisticsDayService;
use Common\Services\SmsPlatForm\StatisticsChannelService;
use Common\Services\SmsPlatForm\StatisticsSignService;
use Common\Services\SmsPlatForm\StatisticsHourService;

/**
 * 后台任务统计发送的数据
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-06-28
 */
class StatisticsTask extends Task
{
    private $stime = 0;
    private $etime = 0;
    /**
     * 获取统计数据， 接收数量和实际下发数量
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @return
     */
    public function receivedNumberAction()
    {
        $config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取统计数据的时间间隔
        //获取一段时间内需要统计的数据，send_task表中
        $sendTaskService = new SendTaskService();
        $this->etime = strtotime(date('Y-m-d H:i',$_SERVER['REQUEST_TIME']).':00');
        $this->stime = $this->etime - $config['statistics']['time'];
        $detailInfos = $sendTaskService->getInfoByCreateTime($this->stime,$this->etime);
        if(empty(count($detailInfos))){
            exit(date('Y-m-d H:i:s').'_本次无要处理的数据');
        }
        $day = $channel = $sign = $hour = $detail = [];
        foreach($detailInfos as $k=>$info){
            //按日统计接收到的短信数量和实际下发数量
            $dayTime = strtotime(date('Y-m-d',$info->create_time));
            $dayKey = $dayTime.$info->type;
            $day = $this->getNumber($info,$dayKey,$day);
            $day[$dayKey]['day_timestamp'] = $dayTime;

            //按通道统计接收到的数量和实际下发数量
            $channelKey = $info->channel_id.$info->type;
            $channel = $this->getNumber($info,$channelKey,$channel);
            $channel[$channelKey]['channel_id'] = $info->channel_id;

            //按签名统计接收到的数量和实际下发数量
            $signKey = $info->sign_id.$info->type;
            $sign = $this->getNumber($info,$signKey,$sign);
            $sign[$signKey]['sign_id'] = $info->sign_id;

            //按小时统计接收到的数量和实际下发数量
            $hourTime = strtotime(date('Y-m-d H:00:00',$info->create_time));
            $hourKey = $hourTime.$info->type;
            $hour = $this->getNumber($info,$hourKey,$hour);
            $hour[$hourKey]['sign_id'] = $info->sign_id;
            $hour[$hourKey]['channel_id'] = $info->channel_id;
            $hour[$hourKey]['hour_timestamp'] = $hourTime;

            //按小时统计接收到的数量和实际下发数量
            $detailKey = $dayTime.$info->sign_id.$info->channel_id.$info->type;
            $detail = $this->getNumber($info,$detailKey,$detail);
            $detail[$detailKey]['sign_id'] = $info->sign_id;
            $detail[$detailKey]['channel_id'] = $info->channel_id;
            $detail[$detailKey]['day_timestamp'] = $dayTime;
        }
        //执行更新方法，把数据存入到数据库中
        $this->addStatisticsDay($day);
        $this->addStatisticsChannel($channel);
        $this->addStatisticsSign($sign);
        $this->addStatisticsHour($hour);
        $this->addStatisticsDetail($detail);
        exit(date('Y-m-d H:i:s').'本次处理完成，共统计'.count($detailInfos).'条数据');
    }

    /**
     * 获取时间段内成功和失败的统计数量
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     */
    public function getSuccessFailAction()
    {
        $config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取统计数据的时间间隔
        $day = $channel = $sign = $hour = $detail = [];
        $this->etime = strtotime(date('Y-m-d H:i',$_SERVER['REQUEST_TIME']).':00');
        $this->stime = $this->etime-$config['statistics']['time'];
        //获取detail表一段时间内的数据，统计下发成功和失败
        $sendTaskDetailService = new SendTaskDetailService();
        $condition = [
            'conditions'=>'update_time>=:stime: and update_time<:etime: and send_status IN ({send_status:array})',
            'bind'=>[
                'stime'=>$this->stime,
                'etime'=>$this->etime,
                'send_status'=>[3,4]
            ]
        ];
        $detailInfos = $sendTaskDetailService->getInfoByCondition($condition);
        if(empty(count($detailInfos))){
            exit(date('Y-m-d H:i:s').'本次无要处理的数据');
        }
        foreach($detailInfos as $info){
            //按日统计下发成功和失败的数量
            $dayTime = strtotime(date('Y-m-d',$info->create_time));
            $dayKey = $dayTime.$info->type;
            $day = $this->getSuccessFailNumber($info,$dayKey,$day);
            $day[$dayKey]['day_timestamp'] = $dayTime;

            //按通道统计下发成功和失败的数量
            $channelKey = $info->channel_id.$info->type;
            $channel = $this->getSuccessFailNumber($info,$channelKey,$channel);
            $channel[$channelKey]['channel_id'] = $info->channel_id;

            //按签名统计下发成功和失败的数量
            $signKey = $info->sign_id.$info->type;
            $sign = $this->getSuccessFailNumber($info,$signKey,$sign);
            $sign[$signKey]['sign_id'] = $info->sign_id;

            //按小时统计下发成功和失败的数量
            $hourTime = strtotime(date('Y-m-d H:00:00',$info->create_time));
            $hourKey = $hourTime.$info->type;
            $hour = $this->getSuccessFailNumber($info,$hourKey,$hour);
            $hour[$hourKey]['sign_id'] = $info->sign_id;
            $hour[$hourKey]['channel_id'] = $info->channel_id;
            $hour[$hourKey]['hour_timestamp'] = $hourTime;

            //按小时统计下发成功和失败的数量
            $detailKey = $dayTime.$info->sign_id.$info->channel_id.$info->type;
            $detail = $this->getSuccessFailNumber($info,$detailKey,$detail);
            $detail[$detailKey]['sign_id'] = $info->sign_id;
            $detail[$detailKey]['channel_id'] = $info->channel_id;
            $detail[$detailKey]['day_timestamp'] = $dayTime;
        }
        $this->addStatisticsDay($day);
        $this->addStatisticsChannel($channel);
        $this->addStatisticsSign($sign);
        $this->addStatisticsHour($hour);
        $this->addStatisticsDetail($detail);
        exit(date('Y-m-d H:i:s').'本次处理完成，共统计'.count($detailInfos).'条数据');
    }


    /**
     * 获取时间段内实际发出的数量
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     */
    public function getRealSendAction()
    {
        $config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php'); //读取配置文件，获取统计数据的时间间隔
        $day = $channel = $sign = $hour = $detail = [];
        $this->etime = strtotime(date('Y-m-d H:i',$_SERVER['REQUEST_TIME']).':00');
        $this->stime = $this->etime-$config['statistics']['time'];
        //获取detail表一段时间内的数据，统计下发成功和失败
        $sendTaskDetailService = new SendTaskDetailService();
        $condition = [
            'conditions'=>'send_time>=:stime: and send_time<:etime: and send_status IN ({send_status:array})',
            'bind'=>[
                'stime'=>$this->stime,
                'etime'=>$this->etime,
                'send_status'=>[1,3,4]
            ]
        ];
        $detailInfos = $sendTaskDetailService->getInfoByCondition($condition);
        if(empty(count($detailInfos))){
            exit(date('Y-m-d H:i:s').'本次无要处理的数据');
        }
        foreach($detailInfos as $info){
            //按日统计下发成功和失败的数量
            $dayTime = strtotime(date('Y-m-d',$info->create_time));
            $dayKey = $dayTime.$info->type;
            $day = $this->getRealSendNumber($info,$dayKey,$day);
            $day[$dayKey]['day_timestamp'] = $dayTime;

            //按通道统计下发成功和失败的数量
            $channelKey = $info->channel_id.$info->type;
            $channel = $this->getRealSendNumber($info,$channelKey,$channel);
            $channel[$channelKey]['channel_id'] = $info->channel_id;

            //按签名统计下发成功和失败的数量
            $signKey = $info->sign_id.$info->type;
            $sign = $this->getRealSendNumber($info,$signKey,$sign);
            $sign[$signKey]['sign_id'] = $info->sign_id;

            //按小时统计下发成功和失败的数量
            $hourTime = strtotime(date('Y-m-d H:00:00',$info->create_time));
            $hourKey = $hourTime.$info->type;
            $hour = $this->getRealSendNumber($info,$hourKey,$hour);
            $hour[$hourKey]['sign_id'] = $info->sign_id;
            $hour[$hourKey]['channel_id'] = $info->channel_id;
            $hour[$hourKey]['hour_timestamp'] = $hourTime;

            //按小时统计下发成功和失败的数量
            $detailKey = $dayTime.$info->sign_id.$info->channel_id.$info->type;
            $detail = $this->getRealSendNumber($info,$detailKey,$detail);
            $detail[$detailKey]['sign_id'] = $info->sign_id;
            $detail[$detailKey]['channel_id'] = $info->channel_id;
            $detail[$detailKey]['day_timestamp'] = $dayTime;
        }
        $this->addStatisticsDay($day);
        $this->addStatisticsChannel($channel);
        $this->addStatisticsSign($sign);
        $this->addStatisticsHour($hour);
        $this->addStatisticsDetail($detail);
        exit(date('Y-m-d H:i:s').'本次处理完成，共统计'.count($detailInfos).'条数据');
    }


    /**
     * 记录详细统计
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @return int | boolean
     */
    private function addStatisticsDetail($data = [])
    {
        if(empty($data)){
            return false;
        }
        foreach($data as $d) {
            $d = $this->getData($d);
            (new StatisticsDetailService())->insertOnDuplicate($d);
        }
    }

    /**
     * 记录按小时统计数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @return int | boolean
     */
    private function addStatisticsHour($data = [])
    {
        if(empty($data)){
            return false;
        }
        foreach($data as $d) {
            $d = $this->getData($d);
            (new StatisticsHourService())->insertOnDuplicate($d);
        }
    }

    /**
     * 按通道统计
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @return int | boolean
     */
    private function addStatisticsChannel($data = [])
    {
        if(empty($data)){
            return false;
        }
        foreach($data as $d) {
            $d = $this->getData($d);
            (new StatisticsChannelService())->insertOnDuplicate($d);
        }
    }

    /**
     * 每个签名的统计数
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @return int | boolean
     */
    private function addStatisticsSign($data = [])
    {
        if(empty($data)){
            return false;
        }
        foreach($data as $d) {
            $d = $this->getData($d);
            (new StatisticsSignService())->insertOnDuplicate($d);
        }
    }

    /**
     * 每天的总数量统计
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @return int | bool
     */
    private function addStatisticsDay($data = [])
    {
        if(empty($data)){
            return false;
        }
        foreach($data as $d) {
            $d = $this->getData($d);
            (new StatisticsDayService())->insertOnDuplicate($d);
        }
    }

    /**
     * 格式化要存入数据库的数据，统一空或者不存在的字段值为0
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     * @param $info
     * @return mixed
     */
    private function getData($info)
    {
        $info['received_number'] = empty($info['received_number'])?0:$info['received_number'];
        $info['succeed_number'] = empty($info['succeed_number'])?0:$info['succeed_number'];
        $info['failed_number'] = empty($info['failed_number'])?0:$info['failed_number'];
        $info['real_send_number'] = empty($info['real_send_number'])?0:$info['real_send_number'];
        $info['update_time'] = $_SERVER['REQUEST_TIME'];
        $info['create_time'] =  $_SERVER['REQUEST_TIME'];
        return $info;
    }

    /**
     * 获取接收和实际下发数量，从send_task表中查找
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     */
    private function getNumber($info,$key,$return)
    {
        if(empty($return[$key]['received_number'])){
            $return[$key]['received_number'] = $info->sms_total;
        }else{
            $return[$key]['received_number'] += $info->sms_total;
        }
        $return[$key]['type'] = $info->type;
        return $return;
    }

    /**
     * 获取接收和实际下发数量，从send_task表中查找
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     */
    private function getSuccessFailNumber($info,$key,$return)
    {
        $success = $fail = $wait = 0;
        if($info->send_status == 3){
            $success = 1;
        }else if($info->send_status == 4){
            $fail = 1;
        }else{
            $wait = 1;
        }
        if(empty($return[$key]['succeed_number'])){
            $return[$key]['succeed_number'] = $success;
        }else{
            $return[$key]['succeed_number'] += $success;
        }
        if(empty($return[$key]['failed_number'])){
            $return[$key]['failed_number'] = $fail;
        }else{
            $return[$key]['failed_number'] += $fail;
        }
        $return[$key]['type'] = $info->type;
        return $return;
    }
    /**
     * 获取实际下发数量
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-28
     */
    private function getRealSendNumber($info,$key,$return)
    {
        if(empty($return[$key]['real_send_number'])){
            $return[$key]['real_send_number'] = 1;
        }else{
            $return[$key]['real_send_number'] += 1;
        }
        $return[$key]['type'] = $info->type;
        return $return;
    }
}
