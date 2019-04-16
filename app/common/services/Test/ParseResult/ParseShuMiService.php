<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析数米返回值
 * @author 李新招 <lixinzhao@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseShuMiService implements ParseResultInteface
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
//        if (empty($response)) {
//            return false;
//        }
        //格式化解析结果
        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'mobile' => $parameters['mobile'],
            'channel_task_id' => 0,
        ];
        if (0 < (int)$response) {
            //记录发送成功数量
            ++$result['task']['success_total'];

            //记录没手机号发送状态
            $detail['send_status'] = 1;
            $detail['channel_task_id'] = $response;
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
     * @date 2017-0309 14:26
     * @param string $response 请求余额接口返回的信息
     * string(31) "20170307092638,0 5541259,470535"
     * @return array
     */
    public function parseBalance($response)
    {
        if (empty($response)) {
            return;
        }
        return ['balance'=>$response];
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
            'success_msg' => '0',
            'faile_msg' => 'FALSE',
            'data' => array()
        );
        //判断数据是否为空
        if (empty($response)) {
            return $result;
        }
        //短信商推送来的发送结果
//        $midStr = $GLOBALS['HTTP_RAW_POST_DATA'];
        $data = explode(';', json_encode($response));
        foreach ($data as $key=>$singleStatus) {
            $singleStatus = explode('|', $singleStatus);
            $msg = ' '.strtoupper(iconv('GBK', 'UTF-8', base64_decode($singleStatus[1])));
            $result['code'] = 1;
            $result['data'][$key] = array(
                'mobile' => $singleStatus[4],
                'channel_task_id' => '',
                'content'=>$msg,
            );
            if (stripos($msg,'TD') || stripos($msg,'T') || stripos($msg,'N') || stripos($msg,'退订')) {
                $result['data'][$key]['replay_type'] = 1;
            }else{
                $result['data'][$key]['replay_type'] = 0;
            }
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
            'code' => 1,
            'success_msg' => '0',
            'faile_msg' => 'FAILE',
            'data' => array()
        );
        //判断数据是否为空
        if (empty($response)) {
            $result['code'] = 0;
            return $result;
        }
        //短信商推送来的发送结果
//        $midStr = $GLOBALS['HTTP_RAW_POST_DATA'];
        $data = array_filter(explode(';', json_encode($response)));
        //发送成功的计数
        foreach ($data as $singleStatus) {
            $singleStatus = explode('|', $singleStatus);
            if ($singleStatus[2] == 'DELIVRD' || $singleStatus[2] == '0') {
                $result['data'][] = array(
                    'channel_task_id' => $singleStatus[3],
                    'mobile' => $singleStatus[1],
                    'send_status' => 3,
                    'channel_status' => $singleStatus[2]
                );
            } else {
                $result['data'][] = array(
                    'channel_task_id' => $singleStatus[3],
                    'mobile' => $singleStatus[1],
                    'send_status' => 4,
                    'channel_status' => $singleStatus[2]
                );
            }
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