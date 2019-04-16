<?php
namespace Common\Services\SmsPlatForm\ParseResult;

/**
 * 解析接口返回值
 * 更新任务状态
 * @author wangjianghua
 * @date 2018-3-3 13:29:16
 */
interface ParseResultInteface
{
    /**
     * 解析发送单条短信结果
     * @author wangjianghua
     * @date 2018-03-03 14:25
     * @param string $response 短信通道返回的信息
     * {
     * "code": 0,
     * "msg": "发送成功",
     * "count": 1, //成功发送的短信计费条数
     * "fee": 0.05,    //扣费条数，70个字一条，超出70个字时按每67字一条计
     * "unit": "RMB",  // 计费单位
     * "mobile": "13200000000", // 发送手机号
     * "sid": 3310228982   // 短信ID
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
    public function parseSendOneResult($response, $parameters);


    /**
     * 解析发送多条短信结果
     * @author wangjianghua
     * @date 2018-03-03 14:25
     * @param string $response 短信通道返回的信息
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
    public function parseSendMoreResult($response, $parameters);


    /**
     * 解析余额接口返回的信息
     * @author wangjianghua
     * @date 2018-0303 14:26
     * @param string $response 请求余额接口返回的信息
     * @return array
     */
    public function parseBalance($response);

    /**
     * 解析短信通道推送的短信上行消息
     * @author wangjianghua
     * @date 2018-03-03 14:27
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyMessage($response,$channel);

    /**
     * 解析短信通道推送的短信状态
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-03 14:27
     * @param string $response 短信通道推送的消息
     * @return array
     */
    public function parseReplyStatus($response,$channel);

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
    public function updateTaskDetailStatus($taskId, $sendResult);
}
