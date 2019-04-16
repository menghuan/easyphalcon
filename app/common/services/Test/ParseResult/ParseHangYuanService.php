<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析鼎汉返回值 @todo 待测试
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseHangYuanService implements ParseResultInteface
{
    /**
     * 解析发送单条短信结果
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-23 11:00
     * @param string $response 短信通道返回的信息
     *   channel_task_id,status
     * @return array
     * 返回值demo：
     * 15210089171r:000提交成功e7bbcfd1-df83-4478-b8f5-328030ce1e63r:000处理成功
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        $dataTmp = json_decode(json_encode(simplexml_load_string($response)),true);
        $param = json_decode(json_encode(simplexml_load_string($parameters['message'])),true);
        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [];

        //00 表示成功  其他失败
        if ((strpos($response,'error_code') === false) && $dataTmp['subStat'] == 'r:000') {
            ++$result['task']['success_total'];
            $detail['mobile'] = $dataTmp['resDetail']['phoneNumber'];
            $detail['channel_task_id'] =  $dataTmp['smsId'];
            $detail['send_status'] = 1;
        } else {
            $result['task']['send_status'] = 2;
            $detail['mobile'] = $param['phoneNumber'];
            $detail['channel_task_id'] = '';
            $detail['send_status'] = 2;
        }
        $result['detail'][] = $detail;
        //如果成功数量为0，将任务状态修改为全部失败。
        if (0 == $result['task']['success_total']) {
            $result['task']['send_status'] = 2;
        }
        return $result;
    }

    /**
     * 解析发送批量短信结果
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 14:25
     * @param string $response 短信通道返回的信息
     * @return array
     */
    public function parseSendMoreResult($response, $parameters)
    {
        return false;
    }

    /**
     * 解析余额接口返回的信息
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 14:26
     * @param string $response 请求余额接口返回的信息
     * @return array
     */
    public function parseBalance($response)
    {
        if (empty($response)) {
            return array();
        }
        return ['balance'=>$response];
    }

    /**
     * 解析短信通道推送的短信上行消息
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 14:27
     * @param string $response 短信通道推送的消息  原来数据为数组
     * {
     * }
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
        //@todo
        $result = array(
            'code' => 0,
            'success_msg' => 'ok',
            'faile_msg' => 'FAIL',
            'data' => array()
        );
        //判断数据是否为空
        if (empty($response)) {
            return $result;
        }
        //发送成功的计数
        $result['code'] = 1;
        $result['data'][0] = [
            'channel_task_id' => '',
            'mobile' => $response['phoneNumber'],
            'content' => urldecode($response['content'])
        ];
        $msg = ' ' . strtoupper($response['content']);
        if (stripos($msg, 'TD') || stripos($msg, 'T') || stripos($msg, 'N') || stripos($msg, '退订')) {
            $result['data'][0]['replay_type'] = 1;
        } else {
            $result['data'][0]['replay_type'] = 0;
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 14:27
     * @param string $response 短信通道推送的消息
     * array(2) { ["account"]=> string(9) "qiaodaSMS" ["GetReport"]=> string(40) "2114897500138967701,18511892705,DELIVRD|" }
     * @return array
     */
    public function parseReplyStatus($response,$channel = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => 'ok',
            'faile_msg' => 'FAIL',
            'data' => array(),
        );
        //短信商推送来的发送结果
        if (empty($response)) {
            return $result;
        }
        //发送成功的计数
        $result['data'][] = array(
            'channel_task_id' => $response['msgid'],
            'send_status' => ($response['nstat'] == 0) ? 3 : 4,
            'channel_status' => $response['errcode'],
            'mobile' => $response['phone'],
        );
        $result['code'] = 1;
        return $result;
    }

    /**
     * 更新任务状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 11:09
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
