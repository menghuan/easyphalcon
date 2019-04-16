<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析数米返回值
 * @author 李新招 <lixinzhao@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseGuangXinTongService implements ParseResultInteface
{
    /**
     * 解析发送同一内容到一个或多个手机号
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param xml object $response 短信通道返回的信息
     * @return array
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        //把xml object转化为json串，在转成数组
        $xmljson = json_encode($response);
        $resultTmp =json_decode($xmljson,true);
        if (empty($resultTmp)) {
            return false;
        }

        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'channel_task_id' => $resultTmp['taskID'],
            'mobile' => $parameters['mobile']
        ];
        //任务状态
        if (('Success' == $resultTmp['returnstatus'])) {
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
     * 解析批量发送结果
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-21
     * @param string $response 短信通道返回的信息
     * 请求成功
     */
    public function parseSendMoreResult($response, $parameters)
    {
        return;
    }

    /**
     * 解析余额接口返回的信息
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
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
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyMessage($response,$channel = [])
    {
        $result = array(
            'code' => 0,
            'success_msg' => '',
            'faile_msg' => '',
            'data' => array()
        );
        $response = json_decode($response,true);
        //判断数据
        if (empty($response)) {
            return $result;
        }
        //手机号
        $phone = $response['phone'];
        //用户回复的内容
        $msg = ' ' . strtoupper($response['replyContent']);
        if (!$phone || !$msg) {
            return $result;
        }
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $phone,
            'channel_task_id' =>'',
            'content' => $response['replyContent']
        );
        if (stripos($msg, 'TD') || stripos($msg, 'T') || stripos($msg, 'N') || stripos($msg, '退订')) {
            $result['data'][0]['replay_type'] = 1;
        } else {
            $result['data'][0]['replay_type'] = 0;
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信上行状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param string $response 短信通道推送的消息
     * signature: 9eb24a034b655257a63c209b35b64b2a4ec5e894ed513c0410a3a6dc570aaaaa
     * event: request
     * userId: 19999
     * timestamp: 1434684323193
     * eventType: 1
     * templateId: 29999
     * message: request
     * smsUser: smsuser
     * phones: ["13888888888"]
     * token: nyFltYEluRVvYezFHJW1st2ewb71RVcVDiNN6GqvRnWtgDDDDD
     * smsIds: ["1434684322919_95_1_1_9m9684$13888888888"]
     * labelId: 0
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
        if (empty($response['response'])) {
            return $result;
        }
        //把xml object转化为json串，在转成数组
        $xmljson = json_encode($response['response']);
        $resultTmp =json_decode($xmljson,true);
        if (empty($resultTmp)) {
            return false;
        }
        $result['code'] = 1;
        $data = [];
        if(isset($resultTmp['statusbox'][0])){
            foreach($resultTmp['statusbox'] as $res) {
                $data[] = [
                    'channel_task_id' => $res['taskid'],
                    'send_status' => ($res['status'] == 10) ? 3 : 4,
                    'mobile' => $res['mobile'],
                    'channel_status' => $res['errorcode'],
                ];
            }
        }else{
            $data[] = [
                'channel_task_id' => $resultTmp['statusbox']['taskid'],
                'send_status' => ($resultTmp['statusbox']['status'] == 10) ? 3 : 4,
                'mobile' => $resultTmp['statusbox']['mobile'],
                'channel_status' => $resultTmp['statusbox']['errorcode'],
            ];
        }

        $result['data'] = $data;
        return $result;
    }

    /**
     * 更新任务状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
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
