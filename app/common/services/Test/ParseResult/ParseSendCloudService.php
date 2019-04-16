<?php

namespace Common\Services\SmsPlatForm\ParseResult;

use Common\Services\SmsPlatForm\SendTaskDetailService;

/**
 * 解析数米返回值
 * @author 李新招 <lixinzhao@qiaodata.com>
 * @date 2017-3-3 14:38:14
 */
class ParseSendCloudService implements ParseResultInteface
{

    /**
     * 解析发送同一内容到一个或多个手机号
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param string $response 短信通道返回的信息
     *   resptime,respstatus,msgid
     * @return array
     * 返回值demo：
     */
    public function parseSendOneResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        //把response转成数组
        $resultTmp =json_decode($response, true);
        if (empty($resultTmp)) {
            return false;
        }

        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        $detail = [
            'channel_task_id' => $resultTmp['info']['smsIds'][0],
            'mobile' => $parameters['phone']
        ];
        //任务状态
        if ((200 == $resultTmp['statusCode'])) {
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
     * @date 2017-05-09
     * @param string $response 短信通道返回的信息
     * @return array
     */
    public function parseSendMoreResult($response, $parameters)
    {
        if (empty($response)) {
            return false;
        }
        //接送转为数组
        $resultTmp = json_decode($response, true);
        if (empty($resultTmp)) {
            return false;
        }

        //格式化解析结果
        $result = [
            'task' => ['success_total' => 0, 'send_status' => 1],
            'detail' => []
        ];
        if(!empty($resultTmp['info']['items'])){
            foreach($resultTmp['info']['items'] as $kFail => $item){
                $detail[] = [
                    'channel_task_id' => '',
                    'mobile'=> $item['phone']
                ];
            }
        }
        if(!empty($resultTmp['info']['smsIds'])){
            foreach($resultTmp['info']['smsIds'] as $kSucc => $info){
                $infoArr = explode('$', $info);
                $detail[] = [
                    'channel_task_id' => $info,
                    'mobile'=> $infoArr[1]
                ];
            }
        }

        if ($resultTmp['statusCode'] == 200) {
            $result['task']['send_status'] = 1;
        } elseif ($resultTmp['statusCode'] == 311) {
            $result['task']['send_status'] = 3;
        } else {
            $result['task']['send_status'] = 2;
            $tos = json_decode($parameters['tos'],true);
            foreach ($tos as $p => $v) {
                $detail[] = [
                    'channel_task_id'=>'',
                    'mobile'=>$v['phone']
                ];
            }
        }
        $result['detail'] = $detail;
        return $result;
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
            'data' => array(),
        );
        //短信商推送来的发送结果
        if (empty($response)) {
            return $result;
        }
        //加密判断
//        $ChannelApiService = new ChannelApiService();//实例化api
//        $ChannelApiParamService = new ChannelApiParamService();//实例化apiparam
//        $channelApi = $ChannelApiService->getChannelApiByType(45, 1);
//        if (empty($channelApi->id)) {
//            return $result;
//        }
//        $parameters = $ChannelApiParamService->getByApiIds($channelApi->id);
//        if (empty($parameters) || 0 >= $parameters->count()) {
//            return $result;
//        }
//        $param = [];
//        foreach ($parameters as $key => $paramTmp) {
//            $param[$paramTmp->param_key] = $paramTmp->param_value;
//        }
////        $value = md5($response['token'].$param['key_status']);
//        if ($param['appid'] != $response['app']) {
//            return $result;
//        }
        //手机号
        if (empty($response['phone'])) {
            return $result;
        }
        $msg = ''.$response['replyContent'];
        $result['code'] = 1;
        $result['data'][0] = array(
            'mobile' => $response['phone'],
            'channel_task_id' => '',
            'content'=>$msg
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
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param string $response 短信通道推送的消息
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
        if (empty($response)) {
            return $result;
        }
        if (empty($response['smsId'])) {
            return $result;
        }
        //加密判断
//        $ChannelApiService = new ChannelApiService();//实例化api
//        $ChannelApiParamService = new ChannelApiParamService();//实例化apiparam
//        $channelApi = $ChannelApiService->getChannelApiByType(45, 1);
//        if (empty($channelApi->id)) {
//            return $result;
//        }
//        $parameters = $ChannelApiParamService->getByApiIds($channelApi->id);
//        if (empty($parameters) || 0 >= $parameters->count()) {
//            return $result;
//        }
//        $param = [];
//        foreach ($parameters as $key => $paramTmp) {
//            $param[$paramTmp->param_key] = $paramTmp->param_value;
//        }
////        $value = md5($response['token'].$param['key_status']);
//        if ($param['appid'] != $response['app']) {
//            return $result;
//        }
        $result['code'] = 1;
        $data = array(
            'channel_task_id' => $response['smsId'],
            'send_status' => 3,
            'mobile' => $response['phone'],
            'channel_status' => $response['event']
        );
        if ($response['event'] != 'deliver') {
            $data['send_status'] = 4;
        }
        $result['data'][] = $data;
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
