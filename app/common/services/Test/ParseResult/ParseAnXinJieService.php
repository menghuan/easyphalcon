<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析安信捷返回值
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2018-3-3 14:38:14
 */
class ParseAnXinJieService implements ParseResultInteface
{

    /**
     * 解析发送单条短信结果
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-06-15 11:00
     * @param string $response 短信通道返回的信息
     *   channel_task_id,status
     * @return array
     * 返回值demo：
     * string(37) "X114850151028190428,00"
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'mobile' => $parameters['mobiles'],
            'channel_task_id' => '',
        ];
        //分割返回的数据
        $responseArr = explode(',', $response);
        $detail['channel_task_id'] = $responseArr[0];
        //00 表示成功  其他失败
        if ((strpos($responseArr[0],'error_code') === false) && intval($responseArr[1]) == '0') {
            ++$result['task']['success_total'];
            $detail['send_status'] = 1;
        } else {
            $result['task']['send_status'] = 2;
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
     * @date 2018-06-15 14:25
     * @param string $response 短信通道返回的信息
     * @return array
     */
    public function parseSendMoreResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        //分割返回的数据
        $responseArr = explode(',', $response);
        $detail['channel_task_id'] = $responseArr[0];
        //00 表示成功  其他失败
        if ((strpos($responseArr[0],'error_code') === false) && intval($responseArr[1]) == '0') {
            $result['task']['success_total'] = 1;
        } else {
            $result['task']['success_total'] = 1;
            $result['task']['send_status'] = 2;
        }
        $mobilesArr = explode(',', trim($parameters['mobiles'], ','));
        foreach($mobilesArr as $m) {
            $detail['mobile'] = $m;
            $detail['send_status'] = $result['task']['send_status'];
            $detail['channel_task_id'] = $responseArr[0];
            $result['detail'][] = $detail;
        }
        return $result;
    }

    /**
     * 解析余额接口返回的信息
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-06-15 14:26
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
     * @date 2018-06-15 14:27
     * @param string $response 短信通道推送的消息  原来数据为数组
     * {
     * }
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
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
        //短信商推送来的发送结果
        $data = array_filter(explode(';', $response['report']));
        if (empty($data)) {
            return $result;
        }
        //发送成功的计数
        foreach ($data as $k => $v) {
            $info = explode(',', $v);
            if ($info[0] == 0) {
                $result['code'] = 1;
                $result['data'][$k] = [
                    'channel_task_id' => '',
                    'mobile' => $info[1],
                    'content' => $info[3]
                ];
                $msg = ' ' . strtoupper($info[3]);
                if (stripos($msg, 'TD') || stripos($msg, 'T') || stripos($msg, 'N') || stripos($msg, '退订')) {
                    $result['data'][$k]['replay_type'] = 1;
                } else {
                    $result['data'][$k]['replay_type'] = 0;
                }
            }
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-06-15 14:27
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
        $data = array_filter(explode(';', $response['report']));
        if (empty($data)) {
            return $result;
        }
        //发送成功的计数
        foreach ($data as $k => $v) {
            $info = explode(',', $v);
            if ($info[0] == 2) {
                $data[$k] = array(
                    'channel_task_id' => $info[2],
                    'send_status' => ($info[5] == '0') ? 3 : 4,
                    'channel_status' => $info[5],
                    'mobile' => $info[1],
                );
            }
        }
        $result['data'] = $data;
        $result['code'] = 1;
        return $result;
    }

    /**
     * 更新任务状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-06-15 11:09
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
