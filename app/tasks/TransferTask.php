<?php
require dirname(__DIR__).'/common/library/vendor/PhpCurl/vendor/autoload.php';
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line as LineFormatter;

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
class TransferTask
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
    public function sendTransferAction()
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
            $channelProcess = $this->config->transfer;
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
            $conn->disconnect();
        } catch (Exception $exc) {
            $message = 'error_message: ' . $exc->getMessage() . "\n";
            $message .= "error_trace: " . $exc->getTraceAsString() . "\ndata: " . json_encode($this->runLog);

            //日志目录是否存在
            $logPath = LOG_PATH . 'mq/transfer/' . date("Y/m") . '/';
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
     * 给产品先发送数据请求
     * @author 苏云雷
     * @data 2017-09-12
     */
    public function executeSend()
    {
        $curl = new \Curl\Curl();
        foreach($this->sendTask as $task) {
            if(!empty($task['data'])) {
                if ($task['is_json']) {
                    $task['data'] = json_decode($task['data'], true);
                }
                $log_path = '/var/log/sms_platform_2.0.0/taskLog/sendTransfer' . date('Y-m-d') . ".log";
                error_log(date('Y-m-d H:i:s') . '__url' . $task['send_url'] . '__data' . json_encode($task['data']) . "\n", 3, $log_path);
                $pushStr = $curl->post($task['send_url'], $task['data']);
                error_log(date('Y-m-d H:i:s') . '_id:' . $task['id'] . '_pushStr:' . $pushStr . "\n", 3, $log_path);
            }
        }
    }

}
