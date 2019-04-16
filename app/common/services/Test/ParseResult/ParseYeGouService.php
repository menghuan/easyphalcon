<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析数米返回值
 * @author 李新招 <lixinzhao@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseYeGouService implements ParseResultInteface
{

    /**
     * 解析发送单条短信结果
     * @author 李新招 <lixinzho@qiaodata.com>
     * @date 2017-03-9 14:25
     * @param string $response 短信通道返回的信息
     *   resptime,respstatus,msgid
     * @return array
     * 返回值demo：
     * string(37) "20170306165638,0 1230306165638860000 "
     */
    public function parseSendOneResult($response, $parameters)
    {
        return;
    }

    /**
     * 解析发送批量短信结果  暂不支持不同短信的批量发送
     * @author 李新招 <lixinzho@qiaodata.com>
     * @date 2017-03-08 14:25
     * @param string $response 短信通道返回的信息
     * @return array
     */
    public function parseSendMoreResult($response, $parameters)
    {
        $data = json_decode($response, true);
        if (empty($data)) {
            return false;
        }
        //格式化解析结果
        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'mobile' => '',
            'channel_task_id' => '',
        ];
        $content = json_decode($parameters['content'],true);
        foreach ($content as $key => $va) {
            if ($data['status'] == 'ok') {
                ++$result['task']['success_total'];
                //记录没手机号发送状态
                $detail[$key]['send_status'] = 1;
                $detail[$key]['channel_task_id'] = $data['data']['rrid'];
                $detail[$key]['mobile'] = $va['phone'];
            } else {
                $result['task']['send_status'] = 2;
                $detail[$key]['send_status'] = 2;
                $detail[$key]['channel_task_id'] = $data['data']['rrid'];
                $detail[$key]['mobile'] = $va['phone'];
            }
        }
        $result['detail'][] = $detail;
        return $result;
    }

    /**
     * 解析余额接口返回的信息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-0309 14:26
     * @param string $response 请求余额接口返回的信息
     * string(31) "20170307092638,0 5541259,470535"
     * @return array
     */
    public function parseBalance($response)
    {
        $data = json_decode($response, true);
        if (empty($data)) {
            return;
        }
        if ($data['status'] == 'ok') {
            return $data['data']['balance'];
        } else {
            return;
        }
    }

    /**
     * 解析短信通道推送的短信上行消息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 14:27
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => '',
            'faile_msg' => '',
            'data' => array(),
        );
        $response = json_decode($response, true);
        //短信商推送来的发送结果
        if (empty($response)) {
            return $result;
        }
        if (empty($response['data']['rrid'])) {
            return $result;
        }
        //上行类型
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $response['data']['mobile'],
            'channel_task_id' => $response['data']['rrid'],
            'content' => $response['data']['content']
        );
        $msg = strtoupper($response['data']['content']);
        if (stripos($msg, 'TD') || stripos($msg, 'T') || stripos($msg, 'N') || stripos($msg, '退订')) {
            $result['data'][0]['replay_type'] = 1;
        } else {
            $result['data'][0]['replay_type'] = 0;
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信上行状态
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 14:27
     * @param string $response 短信通道推送的消息
     * 400319|15369297656|DELIVRD|3201137401041||1703201137|2017-03-20 11:37:43|2017-03-20 11:37:52
     * @return array
     */
    public function parseReplyStatus($response,$channel = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => '',
            'faile_msg' => '',
            'data' => array(),
        );
        $response = json_decode($response, true);
        //短信商推送来的发送结果
        if (empty($response)) {
            return $result;
        }
        if (empty($response['data']['rrid'])) {
            return $result;
        }
        //状态类型
        $data = array(
            'channel_task_id' => $response['data']['rrid'],
            'send_status' => 3,
            'mobile' => $response['data']['mobile'],
            'channel_status' => $response['data']['status'],
        );
        $result['data'][0] = $data;
        $result['code'] = 1;
        if ($response['data']['status'] != 'DELIVRD') {
            $result[0]['send_status'] = 4;
        }
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
