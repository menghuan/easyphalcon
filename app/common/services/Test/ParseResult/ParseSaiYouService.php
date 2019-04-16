<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\ChannelApiParamService;
use Common\Services\SmsPlatForm\ChannelApiService;
use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析沃动返回值
 * @author 李新招 <lixinzhoa@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseSaiYouService implements ParseResultInteface
{

    /**
     * 解析发送单条短信结果
     * @author 李新招 <lixinzho@qiaodata.com>
     * @date 2017-03-08 14:25
     * @param string $response 短信通道返回的信息
     *   {"status":"success","send_id":"88766a2bf20f01b93ad36f16a49dcd5e","fee":1,"sms_credits":"109258"}
     * @return array
     * 返回值demo：
     * string(37) "20170306165638,0 1230306165638860000 "
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return;
        }
        //接送转为PHP数组
        $resultTmp = json_decode($response, true);
        if (0 != json_last_error()) {
            return;
        }
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        if(!empty($resultTmp['send_id'])) {
            $detail = [
                'mobile' => $parameters['to'],
                'channel_task_id' => $resultTmp['send_id'],
                'channel_status' => $resultTmp['status']
            ];
            if ('success' == $resultTmp['status']) {
                //记录发送成功数量
                ++$result['task']['success_total'];

                //记录没手机号发送状态
                $detail['send_status'] = 1;
            } else {
                //如果有失败的将任务发送状态修改3（部分失败）；
                $result['task']['send_status'] = 3;

                //记录没手机号发送状态
                $detail['send_status'] = 2;
            }
            $result['detail'][] = $detail;

            //如果成功数量为0，将任务状态修改为全部失败。
            if (0 == $result['task']['success_total']) {
                $result['task']['send_status'] = 2;
            }
        }
        return $result;
    }

    /**
     * 解析发送批量短信结果  暂不接入不同短信的批量发送
     * @author 李新招 <lixinzho@qiaodata.com>
     * @date 2017-03-08 14:25
     * @param string $response 短信通道返回的信息
     * @return array
     */
    public function parseSendMoreResult($response, $parameters)
    {
        return;
    }

    /**
     * 解析余额接口返回的信息 (余额在发送范惠中，没余额查询接口)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-0308 14:26
     * @param string $response 请求余额接口返回的信息
     * string(31) "20170307092638,0 5541259,470535"
     * @return array
     */
    public function parseBalance($response)
    {
        return;
    }

    /**
     * 解析短信通道推送的短信上行消息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 14:27
     * @param string $response 短信通道推送的消息    数组格式   没有task_id
     * {
     * "events": "mo",
     * "address": "18513733044",
     * "app": "13386",
     * "content": "你好",
     * "timestamp": "1489797258",
     * "token": "d476dd1932d6bf43eaf8b5e8af2f9977",
     * "signature": "90e029434f6fcce02b2e7742e586dcc7"
     * }
     * @return array
     */
    public function parseReplyMessage($response,$channelInfo = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => '',
            'faile_msg' => '',
            'data' => array()
        );
        //判断数据
        if (empty($response)) {
            return $result;
        }
        //加密判断
        $ChannelApiService = new ChannelApiService();//实例化api
        $ChannelApiParamService = new ChannelApiParamService();//实例化apiparam
        $channelApi = $ChannelApiService->getChannelApiByType($channelInfo['channel_id'], $channelInfo['type']);
        if (empty($channelApi->id)) {
            return $result;
        }
        $parameters = $ChannelApiParamService->getByApiIds($channelApi->id);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return $result;
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        //$value = md5($response['token'].$param['key_message']);
        if ($param['appid'] != $response['app']) {
            return $result;
        }
        //手机号
        $phone = $response['address'];
        //用户回复的内容
        $msg = ' '.strtoupper($response['content']);
        if (!$phone OR !$msg) {
            return $result;
        }
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $phone,
            'channel_task_id' => '',
            'content'=>$response['content'],
        );
        if (stripos($msg,'TD') || stripos($msg,'T') || stripos($msg,'N') || stripos($msg,'退订')) {
            $result['data'][0]['replay_type'] = 1;
        }else{
            $result['data'][0]['replay_type'] = 0;
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信状态
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 14:27
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyStatus($response,$channelInfo = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => '',
            'faile_msg' => '',
            'data' => array(),
        );
        //短信商推送来的发送结果
        if (empty($response)) {
            return $result;
        }
        if (empty($response['send_id'])) {
            return $result;
        }
        //加密判断
        $ChannelApiService = new ChannelApiService();//实例化api
        $ChannelApiParamService = new ChannelApiParamService();//实例化apiparam
        $channelApi = $ChannelApiService->getChannelApiByType($channelInfo['channel_id'], $channelInfo['type']);
        if (empty($channelApi->id)) {
            return $result;
        }
        $parameters = $ChannelApiParamService->getByApiIds($channelApi->id);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return $result;
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
//        $value = md5($response['token'].$param['key_status']);
        if ($param['appid'] != $response['app']) {
            return $result;
        }
        $result['code'] = 1;
        $data = array(
            'channel_task_id' => $response['send_id'],
            'send_status' => 3,
            'mobile' => $response['address'],
            'channel_status' => $response['events']
        );
        if ($response['events'] != 'delivered') {
            $data['send_status'] = 4;
        }
        $result['data'][] = $data;
        return $result;
    }

    /**
     * 更新任务状态
     * @author 李新招 <lixinzhao@qiaodata.com>
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
     * @return int 受影响行数。
     */
    public function updateTaskDetailStatus($taskId, $sendResult)
    {
        $taskId = intval($taskId);
        if (0 >= $taskId || empty($sendResult) || !is_array($sendResult)) {
            return false;
        }

        $rows = 0;
        $sendTaskDetailService = new SendTaskDetailService();
        foreach ($sendResult as $result) {
            $data = [
                'channel_task_id' => $result['channel_task_id'],
                'send_status' => $result['send_status'],
                'update_time' => $_SERVER['REQUEST_TIME'],
            ];
            $result['update_time'] = $_SERVER['REQUEST_TIME'];
            $rows += $sendTaskDetailService->updateTask($taskId, $result['mobile'], $data);
        }
        return $rows;
    }

}
