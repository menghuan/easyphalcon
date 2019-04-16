<?php
namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析沃动返回值
 * @author 李新招 <lixinzhoa@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseWoDongService implements ParseResultInteface
{

    /**
     * 解析发送单条短信结果
     * @author 李新招 <lixinzho@qiaodata.com>
     * @date 2017-03-08 14:25
     * @param string $response 短信通道返回的信息
     *   resptime,respstatus,msgid
     * @return array
     * 返回值demo：
     * string(37) "20170306165638,0 1230306165638860000 "
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        //格式化解析结果
        $relResb = explode(',', $response)[1];
        $status = substr($relResb, -21, 1);
        $taskId = substr($relResb, -20, 19);
        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'mobile' => $parameters['mobile'],
            'channel_task_id' => $taskId,
        ];
        if (0 == (int)$status) {
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
        return $result;
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
        return;
    }

    /**
     * 解析余额接口返回的信息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-0308 14:26
     * @param string $response 请求余额接口返回的信息
     * string(31) "20170307092638,0 5541259,470535"
     * @return array
     */
    public function parseBalance($response)
    {
        $relResb = explode(',', $response);
//        $status = substr($rel_resb[1], -9,1);//状态
        $balance = $relResb[2];
        if (empty($balance)) {
            return array();
        }
        return $balance;
    }

    /**
     * 解析短信通道推送的短信上行消息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 14:27
     * @param string $response 短信通道推送的消息   原数据为数组
     * {
     * "_url": "/send/setReplayMessage/42",
     * "receiver": "",
     * "pswd": "",
     * "moTime": "1703171809",
     * "destcode": "1069084313066601690",
     * "mobile": "15906694850",
     * "spCode": "1069084313066601690",
     * "msg": "收到"
     * }
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
//        $datas = $_REQUEST;
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
        //手机号
        $phone = $response['mobile'];
        //用户回复的内容
        $msg = ' '.strtoupper($response['msg']);
        //task_id
//        $msgid = $response['msgid'];
        if (!$phone OR !$msg) {
            return $result;
        }
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $phone,
            'channel_task_id' => '',
            'content'=>$response['msg']
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
     * @param string $response 短信通道推送的消息  数组形式，下面是加密过的
     * {
     * "_url": "\/send\/setStatus\/42",
     * "receiver": "",
     * "pswd": "",
     * "msgid": "1230317094716110900",
     * "reportTime": "1703170947",
     * "mobile": "15210089171",
     * "status": "DELIVRD"
     * }
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
        //短信商推送来的发送结果
//        $response = $_REQUEST;
        if (empty($response)) {
            return $result;
        }
        if (empty($response['msgid'])) {
            return $result;
        }
        $data = array(
            'channel_task_id' => $response['msgid'],
            'send_status' => 4,
            'mobile' => $response['mobile'],
            'channel_status' => $response['status'],
        );
        $result['code'] = 1;
        if ($response['status'] == 'DELIVRD') {
            $data['send_status'] = 3;
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
