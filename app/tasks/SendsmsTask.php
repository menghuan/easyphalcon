<?php
require dirname(__DIR__).'/common/library/vendor/PhpCurl/vendor/autoload.php';
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line as LineFormatter;

use Common\Services\SmsPlatForm\ChannelService;
use Common\Services\SmsPlatForm\SendService;
use Common\Services\SmsPlatForm\SendingNumberService;
use Common\Services\SmsPlatForm\SendTaskDetailService;
use Common\Services\SmsPlatForm\SendResponseService;

/**
 * 发送短信
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 11:25:11
 * rabbitmq队列中的任务状态
 * {
 *     "type" : "1", //短信内容类型。短信内容类型。0：未知；1：营销类；2：触发类。
 *     "task_id": "5200",
 *     "sign_id": "9",
 *     "channel_id": "41",
 *     "send_contents": [
 *         {
 *             "mobile": "13641154658",
 *             "content": "内容-1【快火箭】"
 *         }
 *     ]
 * }
 */
class SendSmsTask
{
    /**
     * 配置信息
     * @var type
     */
    private $config = null;

    /**
     * 发送任务
     * @var array
     */
    private $sendTask = [];

    /**
     * 发送任务
     * @var array
     */
    private $successNum = 0;

    /**
     * 发送任务
     * @var array
     */
    private $failNum = 0;

    /**
     * 发送结果
     * @var array
     */
    private $sendResult = [];

    private $startTime = 0;
    private $endTime = 0;

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
     * 发送短信
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-02 11:29
     * 1.从rabbitmq中获取任务。
     * 2.
     * @return null;
     */
    public function send($type = 1, $process = '')
    {
        $connectArgs = [
            'host' => $this->config->mq->host,
            'vhost' => '/',
            'port' => $this->config->mq->port,
            'login' => $this->config->mq->user,
            'password' => $this->config->mq->password
        ];

        try {
            $conn = new AMQPConnection($connectArgs);
            if (!$conn->connect()) {
                throw new Exception("连接失败");
            }
            $channel = new AMQPChannel($conn);
            //创建队列
            $queue = new AMQPQueue($channel);
            $channelProcess = (empty($process) || ($process == 'mainsendprocess'))?$this->config->mq->queue[$type]:$process;
            $queue->setName($channelProcess);
            $queue->setFlags(AMQP_DURABLE); //持久化
            $queue->declareQueue();
            $queue->bind($this->config->mq->exchange, $channelProcess);
            //消费回调函数回掉函数
            $processMessage = function ($envelope, $queue) {
                //获取队列数据内容
                $this->sendTask = json_decode($envelope->getBody(), true);
                //发送短信逻辑处理
                $this->executeSend();
                //手动发送ACK应答
                $queue->ack($envelope->getDeliveryTag());
            };

            //获取消息
            $queue->consume($processMessage);
//            $queue->consume($processMessage,AMQP_AUTOACK);//设置为自动应答，消费消息
            $conn->disconnect();
        } catch (Exception $exc) {
            $message = 'error_message: ' . $exc->getMessage() . "\n";
            $message .= "error_trace: " . $exc->getTraceAsString() . "\ndata: " . json_encode($this->runLog);

            //日志目录是否存在
            $logPath = LOG_PATH . 'mq/consume/' . date("Y/m") . '/';
            if (!file_exists($logPath)) {
                mkdir($logPath, 0775, true);
            }

            //写日志
            $logger = new FileAdapter($logPath . 'error_' . date('d') . '.log');
            $formatter = new LineFormatter("[%date%][%type%]\n%message%\n");
            $formatter->setDateFormat('Y-m-d H:i:s');
            $logger->setFormatter($formatter);
            $logger->error($message);
        }
        return;
    }

    /**
     * 执行短信发送
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-08 13:58
     * @return;
     */
    private function executeSend()
    {
        //获取数据库连接信息
        $channel = (new ChannelService())->getByPrimaryKey($this->sendTask['channel_id']);
        if (empty($channel)) {
            $this->runLog['error_code'] = 1;
            $this->runLog['message'] = '没有找到对应的通道';
            $this->runLog['task'] = json_encode($this->sendTask);
            $this->afterSend();
            return;
        }
        echo date('Y-m-d H:i:s') . '_发送前时间';
        //发送
        $log_path = '/var/log/sms_platform_2.0.0/taskLog/sendPidTaskid' . date('Y-m-d') . ".log";
        error_log(date('Y-m-d H:i:s') . '__taskId:' . $this->sendTask['task_id'] . '__Pid' . getmypid() . "\n", 3, $log_path);
        $ext = empty($this->sendTask['ext']) ? 0 : $this->sendTask['ext'];
        $this->sendResult = (new SendService())->send($channel, $this->sendTask['send_contents'], $ext,$this->sendTask['task_id'],$this->sendTask['sign_id']);
        $this->runLog['send_result'] = $this->sendResult;
        if (0 < $this->sendResult['error_code']) {
            $this->runLog['error_code'] = 1;
            $this->runLog['message'] = '提交到短信通道失败';
            $this->runLog['task'] = json_encode($this->sendTask);
            $this->afterSend();
            return;
        }
        echo date('Y-m-d H:i:s') . '_发送完成时间';
        //发送后记录统计数据
        $this->afterSend();
        echo date('Y-m-d H:i:s') . '_更新统计数据完成时间';
        return;
    }

    /**
     * 发送后执行的操作
     * 1.增加当天手机号码发送次数
     * 2.添加统计数据
     *      a.总体统计，每日数量。
     *      b.按签名统计
     *      c.按通道统计
     *      d.发送明细
     * 3.修改任务状态
     * 4.修改任务详情状态
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-08 17:57
     * @return null
     */
    private function afterSend()
    {
        //增加手机号码发送次数
        (new SendingNumberService())->incrSendingNumber($this->sendTask['sign_id'],
            $this->sendTask['channel_id'],
            array_column($this->sendTask['send_contents'], 'mobile'));
        try {
            //修改任务状态，更新任务详情状态。
            $upTask = $this->updateTask();
            if (empty($upTask)) {
                throw new Exception('修改任务以及详情异常');
            }
            echo date('Y-m-d H:i:s') . '_更新任务详情时间';
            //记录日志
            $this->endTime = time();
        } catch (Exception $exc) {
            $this->runLog['error_code'] = 1;
            $this->runLog['message'] = $exc;
            $this->runLog['task'] = json_encode($this->sendTask);
            $this->writeLog();
        }

        //打印日志
        if (DEBUG) {
            $this->printLog();
        }

        return;
    }

    /**
     * 根据发送结果更新任务状态
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-09 11:32
     * @return boolean
     */
    private function updateTask()
    {
        /**
         * 更新任务详情
         * @param int $taskId 任务ID
         * @param int $status 发送状态ID
         * @return boolean
         */
        $updateTaskDetail = function () {
            $detailArr = [$this->sendResult['result']['detail']];
            if(count($detailArr[0])> 100){
                $detailArr = array_chunk($detailArr[0],100);
            }
            foreach($detailArr as $det) {
                $updateResult = (new SendTaskDetailService())->updateTaskAll($this->sendTask['task_id'], $det);
            }
            return $updateResult;
        };

        /**
         * 记录通道返回数据原文
         * @param int $taskId 任务ID
         * @param int $errorCode 错误码 0 成功  其他失败。
         * @param int $response 通道返回原文数据
         * @param int $message 错误提示信息
         * @return int | boolean 受影响行数，出错返回false。
         */
        $addResponse = function ($taskId, $errorCode, $response, $message = '') {
            $data = [
                'task_id' => $taskId,
                'error_code' => $errorCode,
                'response_message' => $response,
                'message' => $message,
                'create_time' => time()];
            return (new SendResponseService())->addOne($data);
        };

        //提交成功
        if (0 == $this->sendResult['error_code']) { //提交成功
            if (1 == $this->sendResult['result']['task']['send_status']) { //全部提交成功
                //更新任务详情
                $updateTaskDetail($this->sendTask['task_id']);
            } else if (2 == $this->sendResult['result']['task']['send_status']) { //全部提交失败
                //更新任务详情
                $updateTaskDetail($this->sendTask['task_id']);
            } else if (3 == $this->sendResult['result']['task']['send_status']) { //部分提交失败
                //更新任务详情
                $updateTaskDetail($this->sendTask['task_id']);
            } else {
                return false;
            }
        } else {
            //提交失败更新任务详情
            $newData = ['send_status' => 2, 'send_time' => time()];
            $mobileList = array_column($this->sendResult['result']['detail'], 'mobile');
            (new SendTaskDetailService())->multiUpdateTask($this->sendTask['task_id'], $mobileList, $newData);
        }

        //添加通道返回原文记录
        $addResponse($this->sendTask['task_id'], $this->sendResult['error_code'], $this->sendResult['response']);
        return true;
    }

    /**
     * 添加发送相应信息
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-04 21:30
     * @param array $sendResult 发送结果数组
     * @return int | bool 添加成功返回主键ID，失败返回false。
     */
    public function addSendResponse($taskId, $sendResult)
    {
        if (empty($sendResult)) {
            return false;
        }

        $data = [
            'task_id' => $taskId,
            'error_code' => $sendResult['error_code'],
            'message' => $sendResult['message'],
            'response_message' => $sendResult['response'],
            'create_time' => time()
        ];

        $sendResponseService = new SendResponseService();
        return $sendResponseService->addOne($data);
    }

    /**
     * 打印日志
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-04 21:09
     * @return null
     */
    public function printLog()
    {
        foreach ($this->runLog as $key => $value) {
            echo $key, " : ", json_encode($value), "\n";
        }
        echo "\n";
        return;
    }

    /**
     * 记录日志
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-04 21:10
     * @return;
     */
    private function writeLog()
    {
        $message = json_encode($this->runLog);

        $logPath = LOG_PATH . 'send/' . date("Y/m") . '/';
        if (!file_exists($logPath)) {
            mkdir($logPath, 0775, true);
        }

        //写日志
        $logger = new FileAdapter($logPath . 'infor_' . date('d') . '.log');
        $formatter = new LineFormatter("[%date%][%type%]\n%message%\n");
        $formatter->setDateFormat('Y-m-d H:i:s');
        $logger->setFormatter($formatter);
        $logger->info($message);
        return true;
    }

    /**
     *手动推送数据
     */
    public function pushMessage()
    {
        $detailService = new SendTaskDetailService();
        $condition = [
            'conditions'=>'task_id>:task_id: and send_status=:status:',
            'bind'=>[
                'task_id'=>2169,
                'status'=>3
            ]
        ];
        $detailInfo = $detailService->getInfoByCondition($condition);
        $curl = new \Curl\Curl();
        $url = 'http://www.kuaihuojian.com/Platform/setStatus';
        foreach($detailInfo as $d){
            $data = [
                'task_id'=>$d->task_id,
                'channel_id'=>$d->channel_id,
                'mobile'=>$d->mobile,
                'send_status'=>$d->send_status,
            ];
            $curl->post($url,$data);
        }
    }

    /**
     * 控制单独匹配的队列的进程数
     * @author 苏云雷
     * @data 2017-09-08
     */
    public function processAction()
    {
        //获取需要单独执行任务的通道
        $info = json_decode(json_encode($this->config->specialChannel),true);
        //循环执行每个队列的任务数量
        if(!empty($info)) {
            foreach ($info as $k => $i) {
                exec('sh '.$this->config->shDir.'rabbitSpecialSend.sh ' . $this->config->mq->queue[1].$k . ' ' . $i);
            }
        }
        exit('执行完毕，信息：'.json_encode($info));
    }
}
