<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析数米返回值
 * @author 李新招 <lixinzhao@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseMengWangService implements ParseResultInteface
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
        if(empty($response)){
            return [];
        }
        $response = json_decode($response, true);
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $mobileArr = explode(',',$parameters['multixmt']);
        foreach($mobileArr as $key=>$m) {
            $mobile = explode('|',$m);
            $detail = [
                'mobile' => $mobile[2],
                'channel_task_id' => intval($response[0])+intval($key),
                'send_status' => 1
            ];
            if(strlen($response[0])<15){
                $detail['send_status'] = 2;
                $detail['channel_task_id'] = intval($response[0]);
            }
            $result['detail'][] = $detail;
        }
        return $result;
    }
    /**
     *
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
        return $response;
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
            'code' => 1,
            'success_msg' => 'command=MO_RESPONSE&spid='.$response['seqid'].'&momsgid='.$response['momsgid'].'&mostat=ACCEPT&moerrcode=000',
            'faile_msg' => 'command=MO_RESPONSE&spid='.$response['seqid'].'&momsgid='.$response['momsgid'].'&mostat=NO&moerrcode=000',
            'data' => array()
        );
        //短信商推送来的发送结果
        $msg = iconv('GBK','UTF-8',hex2bin($response['sm']));
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $response['sa'],
            'channel_task_id' => '',
            'content'=>$msg,
        );
        if (stripos($msg,'TD') || stripos($msg,'T') || stripos($msg,'N') || stripos($msg,'退订')) {
            $result['data'][0]['replay_type'] = 1;
        }else{
            $result['data'][0]['replay_type'] = 0;
        }
        return $result;
    }

    /**
     * 解析短信通道推送的短信上行状态
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 14:27
     * @return array
     */
    public function parseReplyStatus($response,$channel = [])
    {
        $result = array(
            'code' => 1,
            'success_msg' => 'command=RT_RESPONSE&spid='.$response['seqid'].'&mtmsgid='.$response['mtmsgid'].'&rtstat=ACCEPT&moerrcode=000',
            'faile_msg' => 'command=RT_RESPONSE&spid='.$response['seqid'].'&mtmsgid='.$response['mtmsgid'].'&rtstat=NO&moerrcode=000',
            'data' => array()
        );
        //发送成功的计数
        $result['data'][] = array(
            'channel_task_id' => $response['mtmsgid'],
            'mobile' => $response['sa'],
            'channel_status' => $response['mtstat']
        );
        if ($response['mtstat'] == 'DELIVRD') {
            $result['data'][0]['send_status'] = 3;
        } else {
            $result['data'][0]['send_status'] = 4;
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
