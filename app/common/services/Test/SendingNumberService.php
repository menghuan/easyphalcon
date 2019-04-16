<?php
namespace Common\Services\SmsPlatForm;

use Common\Services\RedisService;

/**
 * 每个手机号码每天发送次数记录
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-5-4 10:01:59
 */
class SendingNumberService
{
    /**
     *
     * @\Common\Services\RedisService object
     */
    private $redisService = null;

    /**
     * redis key前缀
     * @var string
     */
    private $redisKeyPrefix = 'sms_platform:';
    private  $redisKey = 'sms_platform:';

    public function __construct()
    {
        $this->redisService = new RedisService();
    }

    /**
     * 获取手机号码的发送次数。
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-04 10:25
     * @param int $signId 签名ID
     * @param int $channelId 通道ID
     * @param array $mobileList 手机号码列表
     * @return array
     * [
     *      'mobile-1' => 1, //手机号码 => 当天发送次数
     *      'mobile-2' => 2
     * ]
     */
    public function getSendingNumber($signId, $channelId, $mobileList)
    {
        $signId = intval($signId);
        $channelId = intval($channelId);
        if ( 0 >= $signId || 0 >= $channelId || empty($mobileList) ) {
            return false;
        }

        //生成redis key 获取次数
        $sendingNumber = [];
        foreach ( $mobileList as $key => $mobile ) {
            $redisKey = $this->createKey($signId, $channelId, $mobile);
            $number = $this->redisService->hGet($redisKey, 'number');
            $sendingNumber[$mobile] = $number;
        }
        return $sendingNumber;
    }

    /**
     * 发送次数增长
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-08 17:28
     * @param int $signId 签名ID
     * @param int $channelId 通道ID
     * @param array $mobileList 手机号码列表
     * @return array
     * [
     *      'mobile-1' => 1, //手机号码 => 当天发送次数
     *      'mobile-2' => 2
     * ]
     */
    public function incrSendingNumber($signId, $channelId, $mobileList)
    {
        $signId = intval($signId);
        $channelId = intval($channelId);
        if ( 0 >= $signId || 0 >= $channelId || empty($mobileList) ) {
            return false;
        }

        //增加发送次数
        foreach ( $mobileList as $key => $mobile ) {
            //生成rediskey
            $redisKey = $this->createKey($signId, $channelId, $mobile);
            $this->saveRedis($redisKey,strtotime(date('Y-m-d 00:00:00'))+86399);
        }
        return true;
    }

    /**
     * 生成redis key
     * signId:channelId:mobile
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-04 10:35
     * @param int $signId 签名ID
     * @param int $channelId 通道ID
     * @param int $mobile 手机号码
     * @return string
     */
    private function createKey($signId, $channelId, $mobile)
    {
        return $this->redisKeyPrefix . $signId . ':' . $channelId . ':' . $mobile;
    }

    /**
     * 记录中转失败的次数，单次任务、单一手机号，限制三次，第四次发送失败不拦截
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-08-01
     */
    public function incrTransferSendingNumber($taskId, $mobile)
    {
        if ( empty($mobile) ) {
            return false;
        }
        //增加发送次数，生成rediskey
        $redisKey = $this->redisKeyPrefix . $mobile . ':' . $taskId;
        $this->saveRedis($redisKey,strtotime(date('Y-m-d 00:00:00'))+86399);
        return true;
    }

    /**
     * 查询中转失败的次数，单次任务、单一手机号，限制三次，第四次发送失败不拦截
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-08-01
     */
    public function getTransferSendingNumber($taskId, $mobile)
    {
        if ( empty($mobile) ) {
            return false;
        }
        $redisKey = $this->redisKeyPrefix . $mobile . ':' . $taskId;
        return $this->redisService->hGet($redisKey, 'number');
    }

    /**
     * 记录任务包发送的位置，用于做断点续发
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-09-11
     */
    public function setPackageSendNumber($taskId, $number = 0)
    {
        if ( empty($taskId) ) {
            return false;
        }
        return $this->redisService->hSet($this->redisKey.'number', $taskId, $number);
    }

    /**
     * 获取任务包发送的位置，用于做断点续发
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-09-11
     */
    public function getPackageSendNumber($taskId)
    {
        if ( empty($taskId) ) {
            return false;
        }
        return $this->redisService->hGet($this->redisKey.'number', $taskId);
    }

    /**
     * 删除任务包发送的位置
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-09-11
     */
    public function delPackageSendNumber($taskId)
    {
        if ( empty($taskId) ) {
            return false;
        }
        return $this->redisService->hDel($this->redisKey.'number', $taskId);
    }

    /**
     * 记录中转失败的次数，单次任务、单一手机号，限制三次，第四次发送失败不拦截
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-08-01
     */
    public function incrWarningSendingNumber($channel)
    {
        if ( empty($channel) ) {
            return false;
        }
        //增加发送次数，生成rediskey
        $redisKey = $this->redisKeyPrefix . 'warning:'.$channel;
        $this->saveRedis($redisKey,strtotime(date('Y-m-d H:00:00')) + 3600);
        return true;
    }

    /**
     * 查询中转失败的次数，单次任务、单一手机号，限制三次，第四次发送失败不拦截
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-08-01
     */
    public function getWarningSendingNumber($channel)
    {
        if ( empty($channel) ) {
            return false;
        }
        $redisKey = $this->redisKeyPrefix  . 'warning:'. $channel;
        return $this->redisService->hGet($redisKey, 'number');
    }

    /**
     * 存储redis数据
     */
    public function saveRedis($redisKey,$time = 0)
    {
        if(empty($redisKey)){
            return false;
        }
        //如果redis key不存在，创建redis key添加数据，并且设定失效时间。
        //如果redis key存在需要将发送次数增加1
        if ($this->redisService->exists($redisKey)) { //存在
            $this->redisService->hIncrBy($redisKey, 'number', 1);
            $this->redisService->hSet($redisKey, 'update_time', $_SERVER['REQUEST_TIME']);
        } else { //不存在
            $this->redisService->hMSet($redisKey, ['number' => 1, 'update_time' => $_SERVER['REQUEST_TIME']]);
            $this->redisService->expireAt($redisKey, $time);
        }
        return true;
    }
}
