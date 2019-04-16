<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析鼎汉返回值 @todo 待测试
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseDingHanService implements ParseResultInteface
{
    /**
     * 解析发送单条短信结果
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-23 11:00
     * @param string $response 短信通道返回的信息
     *   channel_task_id,status
     * @return array
     * 返回值demo：
     * 1,19033698,20121230100429
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
        //分割返回的数据
        $responseArr = explode(',', $response);
        $detail = [
            'mobile' => $parameters['numbers'],
            'channel_task_id' => $responseArr[1],
        ];
        //00 表示成功  其他失败
        if ((strpos($responseArr[0],'error_code') === false) && intval($responseArr[0]) == 1) {
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
     * 解析短信通道推送的短信上行消息 （通道不支持）
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 14:27
     * @param string $response 短信通道推送的消息  原来数据为数组
     * {
     * }
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
        return false;
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
        $data = array_filter(explode(';', $response['reports']));
        if (empty($data)) {
            return $result;
        }
        //发送成功的计数
        foreach ($data as $k => $v) {
            $info = explode(',', $v);
            $data[$k] = array(
                'channel_task_id' => $info[1],
                'send_status' => ($info[3] == 1) ? 3 : 4,
                'channel_status' => $info[3],
                'mobile' => $info[2],
            );
        }
        $result['data'] = $data;
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
