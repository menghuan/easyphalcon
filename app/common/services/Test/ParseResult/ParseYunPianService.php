<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析云片返回值
 * @author wangjianghua
 * @date 2018-3-3 14:38:14
 */
class ParseYunpianService implements ParseResultInteface
{

    /**
     * 解析发送单条短信结果
     * @author wangjianghua
     * @date 2018-03-03 14:25
     * @param string $response 短信通道返回的信息
     * {
     *  "code": 0,
     *  "msg": "发送成功",
     *  "count": 1, //成功发送的短信计费条数
     *  "fee": 0.05,    //扣费条数，70个字一条，超出70个字时按每67字一条计
     *  "unit": "RMB",  // 计费单位
     *  "mobile": "13200000000", // 发送手机号
     *  "sid": 3310228982   // 短信ID
     * }
     * @return array
     * 返回值demo：
     *   [
     *       'task' => [
     *           'success_total' => 1,
     *           'send_status' => 1,
     *       ],
     *       'detail' => [
     *           0 => [
     *               'mobile' => '13641154657',
     *               'channel_task_id' => 14000189922,
     *               'send_status' => 1,
     *           ],
     *       ],
     *   ]
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }

        //接送转为PHP数组
        $resultTmp = json_decode($response, true);
        if (0 != json_last_error()) {
            return false;
        }

        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'channel_task_id' => $resultTmp['sid'],
            'mobile' => $resultTmp['mobile']
        ];
        //任务状态
        if ((0 == $resultTmp['code'])) {
            //记录发送成功数量
            $result['task']['success_total'] = 1;
            //记录没手机号发送状态
            $detail['send_status'] = 1;
        } else {
            //如果有失败的将任务发送状态修改3（部分失败）；
            $result['task']['send_status'] = 2;
            //记录没手机号发送状态
            $detail['send_status'] = 2;
        }

        $result['detail'][] = $detail;
        return $result;
    }

    /**
     * 解析发送多条短信结果
     * @author wangjianghua
     * @date 2018-03-03 14:25
     * @param string $response 短信通道返回的信息
     * @return array
     * 返回值demo：
     */
    public function parseSendMoreResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }

        //接送转为PHP数组
        $resultTmp = json_decode($response, true);

        if (0 != json_last_error()) {
            return false;
        }

        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        if(!empty($resultTmp['http_status_code'])){
            $result['task']['send_status'] = 2;
            return $result;
        }
        $statusArr = array_column($resultTmp['data'], 'code');
        $count = count(array_filter($statusArr));
        if (!in_array(0, $statusArr)) {
            $result['task']['send_status'] = 2;
        } elseif ($count == 0) {
            $result['task']['send_status'] = 1;
        } else {
            $result['task']['send_status'] = 3;
        }
        //任务状态
        foreach ($resultTmp['data'] as $key => $res) {
            $detail[$key]['channel_task_id'] = $res['sid'];
            if (0 == $res['code']) {
                //记录发送成功数量
                ++$result['task']['success_total'];
                $detail[$key]['mobile'] = $res['mobile'];
                //记录没手机号发送状态
                $detail[$key]['send_status'] = 1;
            } else {
                $detail[$key]['mobile'] = $res['mobile'];
                //记录没手机号发送状态
                $detail[$key]['send_status'] = 2;
            }
        }
        $result['detail'] = $detail;
        return $result;
    }

    /**
     * 解析余额接口返回的信息
     * @author wangjianghua
     * @date 2018-0303 14:26
     * {
     * "nick": "于瀚斌",
     * "gmt_created": "2016-12-15 15:25:25",
     * "mobile": "18910273819,13811112230,13901252557",
     * "email": "kuaihuojian@qiaodazhao.com",
     * "ip_whitelist": null,
     * "api_version": "v2",
     * "alarm_balance": 50,    //剩余条数或剩余金额低于该值时提醒
     * "emergency_contact": "",  //紧急联系人
     * "emergency_mobile": "",
     * "balance": 101.95     //账户剩余条数或者剩余金额（根据账户类型）
     * }
     * @param string $response 请求余额接口返回的信息
     * @return array
     */
    public function parseBalance($response)
    {
        if (empty($response)) {
            return false;
        }
        $data['balance'] = $response->alarm_balance;
        return $data;
    }

    /**
     * 解析短信通道推送的短信上行消息
     * @author wangjianghua
     * @date 2018-03-03 14:27
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => 'SUCCESS',
            'faile_msg' => 'FAILE',
            'data' => array()
        );
        //返回的数据
        $data = json_decode(urldecode($response), true);
        if (empty($data)) {
            return $result;
        }
        //用户回复t  td 小写字母，转换为大写
        $text = ' '.strtoupper($data['text']);
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $data['mobile'],
            'channel_task_id' => '',
            'content' => $text,
        );
        if (stripos($text,'TD') || stripos($text,'T') || stripos($text,'N') || stripos($text,'退订')) {
            //放入缓存
            $result['data'][0]['replay_type'] = 1;
        } else {
            $result['data'][0]['replay_type'] = 0;
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信状态
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-03 14:27
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyStatus($response,$channel = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => 'SUCCESS',
            'faile_msg' => 'FAILE',
            'data' => array()
        );
        $data = json_decode(urldecode($response['sms_status']), true);
        if (!$data) {
            return $result;
        }
        $result['code'] = 1;
        foreach ($data as $singleStatus) {
            if (empty($singleStatus['sid'])) {
                continue;
            }
            $result['data'][] = array(
                'channel_task_id' => $singleStatus['sid'],
                'mobile' => $singleStatus['mobile'],
                'send_status' => ($singleStatus['report_status'] == 'SUCCESS') ? 3 : 4,
                'channel_status' =>$singleStatus['error_msg']
            );
        }
        return $result;
    }

    /**
     * 更新任务状态
     * @author wangjianghua
     * @date 2018-03-04 11:09
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
