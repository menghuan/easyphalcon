<?php
namespace Common\Services\SmsPlatForm;

use Common\Services\RedisService;
use Phalcon\Config\Adapter\Php as ConfigPhp;
use Common\Services\SmsPlatForm\SendTaskService;
use Common\Services\SmsPlatForm\SendTaskDetailService;
use Common\Services\SmsPlatForm\ChannelApiService;
use Common\Services\SmsPlatForm\ParamFactoryService;
use Common\Services\SmsPlatForm\ParseResultFactoryService;
use Common\Services\SmsPlatForm\SendingNumberService;

require dirname(__FILE__) . '/../../library/vendor/PhpCurl/vendor/autoload.php';

/**
 * 发送短信
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 15:34:08
 */
class SendService
{
    /**
     * 短信通道为空
     */
    const CHANNEL_EMPTY = 1;

    /**
     * 没找到发送API
     */
    const CHANNEL_API_EMPTY = 2;

    /**
     * API参数为空
     */
    const CHANNEL_API_PARAMETERS_EMPTY = 3;

    /**
     * 请求方法不存在
     */
    const METHOD_ERROR = 4;

    /**
     * 解析结果失败
     */
    const PARSE_RESULT_FAIL = 5;

    /**
     * 发送成功
     */
    const SEND_SUCCESS = 0;

    /**
     * 错误消息
     * @var string
     */
    private $message = "";

    /**
     * 任务Service
     * @var object
     */
    private $sendTaskService = null;

    /**
     * 任务详情Service
     * @var object
     */
    private $sendTaskDetailService = null;

    /**
     * 短信通道API Service
     * @var Common\Services\SmsPlatForm\ChannelApiService
     */
    private $channelApiService = null;

    /**
     * 解析返回结果的类
     * @var
     */
    private $parseResultService = null;

    private $taskId = 0;
    private $mobile = 0;

    public function __construct()
    {
        $this->config = new ConfigPhp('/opt/www/sms_platform_2.0.0/app/config/config.php');
        $this->sendTaskService = new SendTaskService();
        $this->sendTaskDetailService = new SendTaskDetailService();
        $this->channelApiService = new ChannelApiService();
        $this->sendingNumberService = new SendingNumberService();
    }

    /**
     * 发送短信
     * @param \Common\Model\SmsPlatFomr\Channel $channel sms_channel表对象
     * @param array $smsList 接收短信的手机列表
     * [
     *      [
     *          'mobile' => 13641145677,
     *          'content' => '短信内容',
     *      ],
     * ]
     * @param string $ext 扩展码
     * @return array
     * [
     *       'error_code' => 3, //返回的状态码  0成功  其他失败
     *       'message' => 'API参数为空', //提示信息
     *       'result' => [
     *           'task' => [
     *               'success_total' => 1,//发送成功数量
     *               'send_status' => 1,//总体发送状态 1全部成功  2全部失败 3部分成功部分失败
     *           ],
     *           //发送详情信息
     *           'detail' => [
     *               0 => [
     *                       'mobile' => '13641154657',//手机号
     *                       'channel_task_id' => 14000189922,//通道返回的taskid
     *                       'send_status' => 1,//发送状态
     *                   ],
     *               ],
     *       ],
     *       //通道返回数据原文，如果单条发送，则把每一次发送的原文连接在一起
     *       'response' => '{"total_count":2,"total_fee":"0.1000","unit":"RMB","data":[{"code":0,"count":2,"fee":0.1,"mobile":"13641154657","msg":"发送成功","sid":14000189922,"unit":"RMB"}]}',
     *   ]
     */
    public function send($channel, $smsList, $ext = '', $taskId, $signId)
    {
        if (empty($channel)) {
            return ['error_code' => 1, 'message' => '短信通道为空', 'result' => ['task' => ['success_total' => 0, 'send_status' => 2]], 'response' => ''];
        }

        $this->taskId = $taskId;
        $this->mobile = $smsList[0]['mobile'];
        //获取短信通道下的发送短信的API
        $dispatchType = 2;
        $channelApi = $this->channelApiService->getChannelApiByType($channel->id, 2);
        if (empty($channelApi)) {
            $channelApi = $this->channelApiService->getChannelApiByType($channel->id, 1);
            $dispatchType = 1;
        }
        echo date('Y-m-d H:i:s') . '_拼装参数前记录手机号个数：' . count($smsList) . "\n" . '_本次发送手机号内容：' . json_encode(array_column($smsList, 'mobile')) . "\n";
        if (empty($channelApi)) {
            return ['error_code' => 2, 'message' => '没找到发送API', 'result' => ['task' => ['success_total' => 0, 'send_status' => 2]], 'response' => ''];
        }

        //查找上次发送的记录
        $sendNumber = $this->sendingNumberService->getPackageSendNumber($taskId);
        array_splice($smsList, 0, $sendNumber);
        //组装API参数
        $parameterService = ParamFactoryService::createParamServiceInstance($channel);
        if ($dispatchType == 1) {
            //单条发送参数
            $result['result'] = [
                'task' => [
                    'success_total' => 0,
                    'send_status' => 0
                ]
            ];
            $result['response'] = '';
            $sendStatus = [];
            foreach ($smsList as $key => $sms) {
                $parameters = $parameterService->createSendOneParam($channelApi->id, $sms, $ext);
                if (empty($parameters)) {
                    $result[] = ['error_code' => 3, 'message' => 'API参数为空', 'result' => ['task' => ['success_total' => 0, 'send_status' => 2]], 'response' => ''];
                } else {
                    $response = $this->doSend($channel, $channelApi, $parameters, 'parseSendOneResult',$key);
                    $result['result']['task']['success_total'] += $response['result']['task']['success_total'];
                    //记录增加发送记数
                    $this->sendingNumberService->setPackageSendNumber($taskId, $sendNumber + $key + 1);
                    if ($result['result']['task']['send_status'] != 3) {
                        $sendStatus[] = $response['result']['task']['send_status'];
                        $sendStatus = array_values(array_unique($sendStatus));
                        if (count($sendStatus) == 1) {
                            $result['result']['task']['send_status'] = $sendStatus[0];
                        } else {
                            $result['result']['task']['send_status'] = 3;
                        }
                    }
                    $result['result']['detail'][] = $response['result']['detail'][0];
                    $result['response'] .= $response['response'].'_';
                }
            }
        } elseif ($dispatchType == 2) {
            if (!empty($channel->max_num) && $channel->max_num < count($smsList)) {
                $sendArr = array_chunk($smsList, $channel->max_num);
            } else {
                $sendArr = [$smsList];
            }
            //批量发送参数
            $result = [
                'response' => '',
                'result' => [
                    'task' => [
                        'success_total' =>10,
                        'send_status' => 1
                    ]
                ]
            ];
            foreach ($sendArr as $k=>$s) {
                $parameters = $parameterService->createSendMoreParam($channelApi->id, $s, $ext, $signId);
                if (!empty($parameters)) {
                    $response = $this->doSend($channel, $channelApi, $parameters, 'parseSendMoreResult',$k);
                    $sendNumber += count($s);
                    $this->sendingNumberService->setPackageSendNumber($taskId, $sendNumber);
                    if (empty($result['result']['detail'])) {
                        $result['result']['detail'] = $response['result']['detail'];
                    } else {
                        $result['result']['detail'] = array_merge($response['result']['detail'], $result['result']['detail']);
                    }
                    $result['response'] .= $response['response'];
                }
            }
        }
        if (empty($result)) {
            return ['error_code' => 5, 'message' => '解析结果失败', 'result' => ['task' => ['success_total' => 0, 'send_status' => 2]], 'response' => ''];
        }
        $this->sendingNumberService->delPackageSendNumber($taskId);
        return ['error_code' => 0, 'message' => '', 'result' => $result['result'], 'response' => $result['response']];
    }

    /**
     * 更新任务状态
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-04 11:09
     * @param int $taskId 任务ID
     * @param array $sendResult 任务状态数组
     * [
     *   0 => [
     *     'mobile' => '13641154657',
     *     'channel_task_id' => 14000189922,
     *     'send_status' => 1,
     *   ],
     * ]
     * @return array
     *
     */
    public function updateTaskDetailStatus($channel, $taskId, $sendResult)
    {
        if (empty($this->parseResultService)) {
            $this->parseResultService = ParseResultFactoryService::createParseServiceInstance($channel);
        }

        return $this->parseResultService->updateTaskDetailStatus($taskId, $sendResult);
    }

    /**
     * @param \Common\Model\SmsPlatFomr\Channel $channel sms_channel表对象
     * @param \Common\Model\SmsPlatFomr\ChannelApi $channelApi sms_channel_api表对象
     * @param $parameters 格式化的发送数据，按照每个通道的参数格式化类格式化后的数据
     * @param $parseResultMethod 解析返回数据的方法
     * @return array
     * 单条：
     * [
     *       'result' => [
     *           'task' => [
     *               'success_total' => 1, //发送成功数量
     *               'send_status' => 1, //总体发送状态  1 全部成功  2全部失败 3部分成功部分失败
     *           ],
     *           //发送详情
     *           'detail' => [
     *                'mobile' => '13641154657',//手机号
     *                'channel_task_id' => 14000189922,//通道返回的taskid
     *                'send_status' => 1,//发送状态 1成功 2失败
     *            ],
     *       ],
     *       //通道返回的数据原文
     *       'response' => '{"total_count":2,"total_fee":"0.1000","unit":"RMB","data":[{"code":0,"count":2,"fee":0.1,"mobile":"13641154657","msg":"发送成功","sid":14000189922,"unit":"RMB"}]}',
     *   ]
     * 批量：
     * [
     *       'result' => [
     *           'task' => [
     *               'success_total' => 1,
     *               'send_status' => 1,
     *           ],
     *           'detail' => [
     *               0 => [
     *                       'mobile' => '13641154657',
     *                       'channel_task_id' => 14000189922,
     *                       'send_status' => 1,
     *                   ],
     *               ],
     *       ],
     *       'response' => '{"total_count":2,"total_fee":"0.1000","unit":"RMB","data":[{"code":0,"count":2,"fee":0.1,"mobile":"13641154657","msg":"发送成功","sid":14000189922,"unit":"RMB"}]}',
     *   ]
     */
    public function doSend($channel, $channelApi, $parameters, $parseResultMethod,$key = 0)
    {
        if (empty($channel) || empty($channelApi) || empty($parameters) || empty($parseResultMethod)) {
            return ['error_code' => 6, 'message' => '请求参数有误', 'result' => ['task' => ['success_total' => 0, 'send_status' => 2]], 'response' => ''];
        }
        //给通道发送请求
        $curl = new \Curl\Curl();
        $curl->setJsonDecoder(function ($response) {
            return $response;
        });
        echo date('Y-m-d H:i:s') . '_要发送的参数为' . is_array($parameters) ? json_encode($parameters) : $parameters;
        if ($channel->type == 1) {
            $sendUrl = $this->config->sendUrl;
            $sendData = [
                'url' => $channelApi->url,
                'data' => $parameters,
                'taskId' => $this->taskId.'_'.$key
            ];
        } else {
            $sendUrl = $channelApi->url;
            $sendData = $parameters;
        }
        //记录本地数据对照通道接收数据
        $log_path = '/var/log/sms_platform_2.0.0/taskLog/sendInfo_'.$channel->id.'_' . date('Y-m-d') . ".log";
        while(1) {
            if (1 == $channelApi->method) { //GET
                $response = $curl->get($sendUrl, $sendData);
            } else if (2 == $channelApi->method) { //POST
                $response = $curl->post($sendUrl, $sendData);
            } else {
                return ['error_code' => 4, 'message' => '请求方法不存在', 'result' => ['task' => ['success_total' => 0, 'send_status' => 2]], 'response' => ''];
            }
            //解析发送结果
            if (!empty($response)) {
                $checkResponse = json_decode($response, true);
                if (!empty($checkResponse) && isset($checkResponse['error_code']) && $checkResponse['error_code'] == 1 && $checkResponse['message'] == '请求失败') {
                    $sendingNumberService = new SendingNumberService();
                    $transferSendNumber = $sendingNumberService->getTransferSendingNumber($this->taskId, $this->mobile);
                    if ($transferSendNumber < 3) {
                        $sendingNumberService->incrTransferSendingNumber($this->taskId, $this->mobile);
                        echo date('Y-m-d H:i:s') . '中转服务器返回异常:' . $response . '，当前失败次数：' . $transferSendNumber . PHP_EOL;
                        usleep(1000);
                    }else{
                        break;
                    }
                }else{
                    break;
                }
            }
            error_log(date('Y-m-d H:i:s'). "parameters:".json_encode($parameters)."\n___url:".$sendUrl."\n__response:" . json_encode($response) . "_\n_url:" . $sendUrl . "\n", 3, $log_path);
        }
        $this->parseResultService = ParseResultFactoryService::createParseServiceInstance($channel);
        $result = $this->parseResultService->$parseResultMethod($response, $parameters);
        if (is_object($response) || is_array($response)) {
            $response = json_encode($response);
        }
        return array('result' => $result, 'response' => $response);
    }
}
