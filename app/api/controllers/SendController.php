<?php
use Phalcon\Logger\Adapter\File as FileAdapter;
use Phalcon\Logger\Formatter\Line as LineFormatter;
use Phalcon\Config\Adapter\Php as ConfigPhp;

use Common\Services\SmsPlatForm\ChannelService;
use Common\Services\SmsPlatForm\ChannelSignService;
use Common\Services\SmsPlatForm\SignService;
use Common\Services\SmsPlatForm\ProjectService;
use Common\Services\SmsPlatForm\BlacklistService;
use Common\Services\SmsPlatForm\WhitelistService;
use Common\Services\SmsPlatForm\SendingNumberService;
use Common\Services\SmsPlatForm\SendTaskService;
use Common\Services\SmsPlatForm\SendTaskDetailService;
use Common\Util\IteratorUtil;
use Common\Services\SmsPlatForm\WarningService;

/**
 * 添加发送任务
 * @author wangjianghua
 * @date 2018-3-4 23:15:49
 */
class SendController extends Phalcon\Mvc\Controller
{
    /**
     * 能支持的最大数据量
     * @var intcount
     */
    const MOBILE_COUNT = 500;

    /**
     * 用户提交的参数
     * @var array
     */
    private $parameters = [];

    /**
     * 选中的短信通道ID
     * @var obj
     */
    private $selectedChannelId = 0;

    /**
     * 短信平台内部的任务ID
     * @var int
     */
    private $taskId = 0;

    /**
     * 验证过的短信内容
     * [
     *      'successed' => [], //验证通过的
     *      'content_failed' => [], //内容为空的
     *      'sign_failed' => [], //签名验证失败的
     *      'td_failed' => [], //退订字样验证失败的
     *      'blacklist' => [], //黑名单
     *      'excess' => [], //超过发送限制，超过指定条数。超量。
     * ]
     * @var array
     */
    private $validatedSms = [
        'task_id' => '',
        'successed' => [], //验证通过的
        'content_failed' => [], //内容为空的
        'sign_failed' => [], //签名验证失败的
        'td_failed' => [], //退订字样验证失败的
        'blacklist' => [], //黑名单
        'excess' => [], //超过发送限制，超过指定条数。超量。
        'number' => [], //手机号格式错误。
    ];

    /**
     * 错误日志信息
     */
    private $failMessage = '';
    /**
     * 手机白名单
     * @var array
     */
    private $mobileWhitelist = [];

    /**
     * 接收产品提交的发送任务
     * 请求方式：POST
     * 参数列表：
     * +------------------+--------+---------------------------------------------------------+
     * | 参数             | 数据类型| 描述                                                    |
     * +------------------+--------+---------------------------------------------------------+
     * | task_name        | string | 任务名称，简述。                                         |
     * +------------------+--------+---------------------------------------------------------+
     * | type             | int    | 短信内容类型。短信内容类型。0：未知；1：营销类；2：触发类。 |
     * +------------------+--------+---------------------------------------------------------+
     * | project_id       | int    | 产品ID，任务从哪个产品发送过来的。                        |
     * +------------------+--------+---------------------------------------------------------+
     * | channel_id       | int    | 通道ID，在短信平台的自增ID，sms_channel表的主键ID。       |
     * +------------------+--------+---------------------------------------------------------+
     * | sign_id          | int    | 签名ID，在短信平台的自增ID，sms_sign表的主键ID。          |
     * +------------------+--------+---------------------------------------------------------+
     * | send_type        | int    | 1 单发  2多发 判断条件 单 多筛选 (暂时不需要)             |
     * +------------------+--------+---------------------------------------------------------+
     * | td               | string | 退订字样 用于验证短信完整性 暂时不需要传此参数             |
     * +------------------+--------+---------------------------------------------------------+
     * | token            | string | 身份验证标识                                             |
     * +------------------+--------+---------------------------------------------------------+
     * |                  |        | [                                                       |
     * | send_contents    | array  |    ['mobile'=>13641154545, 'content'=>'短信内容1']       |
     * |                  |        |    ['mobile'=>13641154545, 'content'=>'短信内容2']       |
     * |                  |        | ]                                                       |
     * +------------------+--------+---------------------------------------------------------+
     * | supper_whitelist | array  | 超级白名单 此名单中的手机号无论如何都会发送短信            |
     * +------------------+--------+---------------------------------------------------------+
     * | is_repetition    | int    | 是否是补发的短信                                         |
     * +------------------+--------+---------------------------------------------------------+
     * @author wangjianghua
     * @date 2018-03-04 23:20
     * @return null
     * 响应信息：
     * {
     *   "error_code": 12, //错误码
     *   "message": "添加发送任务失败",
     *   "validated_sms": {
     *     "successed": [ //验证通过的
     *       {
     *         "mobile": "13641154658",
     *         "content": "内容-1【快火箭】"
     *       }
     *     ],
     *     "content_failed": [], //内容为空的
     *     "sign_failed": [ //签名验证失败的
     *       {
     *         "mobile": "13641154655",
     *         "content": "内容-2"
     *       }
     *     ],
     *     "td_failed": [], //退订字样验证失败的
     *     "blacklist": [], //在黑名单中的
     *     "excess": [] //发送超过限制的
     *   }
     * }
     *
     * 错误码说明：
     * +----+---------------------------------------+
     * | 0  | 没有错误                               |
     * +----+---------------------------------------+
     * | 1  | 签名和产品未绑定                       |
     * +----+---------------------------------------+
     * | 2  | 产品密钥错误                           |
     * +----+---------------------------------------+
     * | 3  | 不在发送时间段内                       |
     * +----+---------------------------------------+
     * | 4  | 签名已失效                             |
     * +----+---------------------------------------+
     * | 5  | 签名和通道没有绑定                     |
     * +----+---------------------------------------+
     * | 6  | 签名在通道中没有报备成功                |
     * +----+---------------------------------------+
     * | 7  | 通道无效                               |
     * +----+---------------------------------------+
     * | 8  | 签名下没有有效的通道                    |
     * +----+---------------------------------------+
     * | 9  | 短信内容验证不通过                      |
     * +----+---------------------------------------+
     * | 10 | 全在黑名单中                           |
     * +----+---------------------------------------+
     * | 11 | 全部超过发送次数，请更换通道或更换签名。 |
     * +----+---------------------------------------+
     * | 12 | 存储任务失败                           |
     * +----+---------------------------------------+
     * | 13 | 添加发送任务失败                       |
     * +----+---------------------------------------+
     */
    public function sendAction()
    {
        //请求错误
        if (!$this->request->isPost()) {
            $result = ['error_code' => 1, 'message' => "请求错误"];
            echo json_encode($result);
            return;
        }
        //接收参数
        $this->parameters = [
            'task_name' => $this->request->getPost('task_name', 'trim', ''), //任务名称，简述。
            'type' => $this->request->getPost('type', 'int', 0), //短信内容类型。短信内容类型。0：未知；1：营销类；2：触发类。
            'project_id' => $this->request->getPost('project_id', 'trim'), //产品ID，任务从哪个产品发送过来的。
            'channel_id' => $this->request->getPost('channel_id', 'int', 0), //通道ID，在短信平台的自增ID，sms_channel表的主键ID。
            'sign_id' => $this->request->getPost('sign_id', 'int', 0), //签名ID，在短信平台的自增ID，sms_sign表的主键ID。
            'send_type' => $this->request->getPost('send_type', 'int', 2), //1 单发  2多发 判断条件 单 多筛选
//            'td' => $this->request->getPost('td', 'trim', ''), //退订字样，用于验证短信完整性
            'token' => $this->request->getPost('token', 'trim', ''), //身份验证标识
            'ext' => $this->request->getPost('ext', 'trim', ''), //扩展码，目前只有数米通道使用
            'send_contents' => $this->request->getPost('send_contents'), //接收短信的手机号码和短信内容列表
            'supper_whitelist' => $this->request->getPost('supper_whitelist'), //超级白名单列表
            'is_repetition' => $this->request->getPost('is_repetition', 'int', 0), //是否为补发，0：否；1：是；
        ];
        $this->writeLog(); //记录请求日志
        //兼容json格式发送数据，代码优化
        $jsonInfo = json_decode($this->parameters['send_contents'],true);
        if(!is_null($jsonInfo)){
            $this->parameters['send_contents'] = $jsonInfo;
        }

        //发送前验证
        $validation = $this->beforeAdd($this->parameters);
        if (0 < $validation['error_code']) {
            $validation['validated_sms'] = $this->validatedSms;
            echo json_encode($validation);
            return;
        }

        //添加任务到数据表
        $this->validatedSms['task_id'] = $this->taskId = $this->addTask();
        if (!$this->taskId) { //添加任务失败
            $this->writeAddErrorLog(); //写日志,记录错误
            echo json_encode(['error_code' => 12, 'message' => '存储任务失败', 'validated_sms' => $this->validatedSms]);
            return;
        }
        //添加任务到队列中
        $publishResult = $this->publishTask();
        if (!$publishResult) {
            echo json_encode(['error_code' => 13, 'message' => '添加发送任务失败', 'validated_sms' => $this->validatedSms]);
            return;
        }
        //输出相应信息
        echo json_encode(['error_code' => 0, 'message' => '添加成功', 'validated_sms' => $this->validatedSms]);
    }

    /**
     * 记录超时的数据
     * @author wangjianghua
     * @date 2018-05-06 14:22
     * @return int 任务ID
     */
    public function addOutTimerangeData()
    {
        $sendStatus = 10;

        //任务
        $task = [
            'task_name' => isset($this->parameters['task_name']) ? $this->parameters['task_name'] : '',
            'type' => isset($this->parameters['type']) ? $this->parameters['type'] : 0,
            'project_id' => isset($this->parameters['project_id']) ? $this->parameters['project_id'] : 0,
            'sms_total' => count($this->parameters['send_contents']),
            'success_total' => 0,
            'send_status' => $sendStatus,
            'channel_id' => $this->selectedChannelId,
            'sign_id' => isset($this->parameters['sign_id']) ? $this->parameters['sign_id'] : 0,
            'dispatch_type' => isset($this->parameters['send_type']) ? $this->parameters['send_type'] : 0,
            'create_time' => $_SERVER['REQUEST_TIME'],
            'update_time' => $_SERVER['REQUEST_TIME'],
        ];
        $sendTaskService = new SendTaskService();
        $taskId = $sendTaskService->addOne($task);
        if (!$taskId) {
            return 0;
        }

        //任务详情
        $taskDetail = [];
        foreach ($this->parameters['send_contents'] as $sms) {
            $taskDetail[] = [
                'task_id' => $taskId,
                'project_id' => $this->parameters['project_id'],
                'channel_id' => $this->selectedChannelId,
                'sign_id' => $this->parameters['sign_id'],
                'mobile' => $sms['mobile'],
                'channel_task_id' => '',
                'content' => $sms['content'],
                'num' => 0,
                'send_time' => 0,
                'back_time' => 0,
                'send_status' => $sendStatus,
                'create_time' => $_SERVER['REQUEST_TIME'],
                'update_time' => $_SERVER['REQUEST_TIME'],
            ];
        }

        //任务详情记录到数据库
        $addResult = (new SendTaskDetailService())->addMany($taskDetail);
        if (!$addResult) { //添加
            $sendTaskService->deleteByPrimaryKey($taskId);
            return 0;
        }
        return $taskId;
    }
    
    
    /**
     * 记录任务整体失败的日志内容
     * @author wangjianghua
     * @date 2018-06-26
     * @return null;
     */
     private function writeLog()
    {
        $message = 'taskInfo: '.json_encode($this->parameters);
        $logPath = LOG_PATH . 'task_log/';
        if (!file_exists($logPath)) {
            mkdir($logPath, 0775, true);
        }

        //写日志
        $logger = new FileAdapter($logPath .'task_log_'.date("Y-m-d").'.log');
        $formatter = new LineFormatter("[%date%][%type%]\n%message%\n");
        $formatter->setDateFormat('Y-m-d H:i:s');
        $logger->setFormatter($formatter);
        $logger->info($message);
        return;
    }

    /**
     * 记录任务整体失败的日志内容
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-06-26
     * @return null;
     */
    private function writeFailLog()
    {
        $this->parameters['error_message'] = $this->failMessage;
        $message = 'taskInfo: '.json_encode($this->parameters);
        $logPath = LOG_PATH . 'add_task/fail/' . date("Y/m") . '/';
        if (!file_exists($logPath)) {
            mkdir($logPath, 0775, true);
        }

        //写日志
        $logger = new FileAdapter($logPath . date('d') . '.log');
        $formatter = new LineFormatter("[%date%][%type%]\n%message%\n");
        $formatter->setDateFormat('Y-m-d H:i:s');
        $logger->setFormatter($formatter);
        $logger->error($message);
        return;
    }


    /**
     * 往mysql添加任务失败时添加错误日志
     * @author wangjianghua
     * @date 2018-05-05 16:43
     * @return null;
     */
    private function writeAddErrorLog()
    {
        $data = [
            'task_id' => $this->taskId,
            'sign_id' => $this->parameters['sign_id'],
            'channel_id' => $this->selectedChannelId,
            'validated_sms' => $this->validatedSms
        ];
        $message = 'last_sql: ' . $this->di->get('profiler')->getLastProfile()->getSQLStatement() . "\n";
        $message .= 'data: ' . json_encode($data);
        $logPath = LOG_PATH . 'mysql/add_task/' . date("Y/m") . '/';
        if (!file_exists($logPath)) {
            mkdir($logPath, 0775, true);
        }

        //写日志
        $logger = new FileAdapter($logPath . 'error_' . date('d') . '.log');
        $formatter = new LineFormatter("[%date%][%type%]\n%message%\n");
        $formatter->setDateFormat('Y-m-d H:i:s');
        $logger->setFormatter($formatter);
        $logger->error($message);
        return;
    }

    /**
     * 发送前验证
     * （1）产品与签名关系验证
     * （2）产品密钥验证
     * （3）有效发送时间验证
     * （4）签名状态验证（是否开启）
     * （5）签名与通道关系验证（是否绑定）
     *      a. 指定通道：验证签名与通道关系验证（是否绑定）
     *      b. 未指定通道：选择签名的主用通道，如未指定主用通道则随机选择一条绑定的通道
     * （6）通道状态验证（是否开启）
     * （7）短信完整性验证
     *       a. 营销：短信签名、退订字样，短信内容不为空
     *       b. 触发：短信签名、短信内容不为空
     * （8）黑名单排查
     * （9）白名单排查
     * （10）发送限制
     *       a. 同一手机号码、同一通道一天内最多发送10条短信 【通道约定】（可以不验证，通道会返回这种限制状态。）
     *       b. 当天发送超过7条，后续短信不下发，返回业务线超过发送限制提示（跟进补发不受此限）
     *       c. 跟进补发：添加一个参数表示是否是补发即可。
     * （11）批量发送携带“超级白名单”
     *       a. 超级白名单：批量发送通道，以批次为单位，每个批次随机都带一个超级白名单号码发送，同一超级白名单号码在同一通道每天发送不超过10条
     * @author wangjianghua
     * @date 2018-04-28 13:53
     * @param array $parameters 发送参数
     * @return array
     * [
     *      'error_code'=>1,  //错误码，0：没有错误。
     *      'message'=>'签名和产品未绑定' //提示信息
     * ]
     *
     * error_code说明：
     * 0：没有错误
     * 1：签名和产品未绑定
     * 2：产品密钥错误
     * 3：不在发送时间段内
     * 4：签名已失效
     * 5：签名和通道没有绑定
     * 6：签名在通道中没有报备成功
     * 7：通道无效
     * 8：签名下没有有效的通道
     * 9：短信内容验证不通过
     * 10：全在黑名单中
     * 11：全部超过发送次数，请更换通道或更换签名。
     */
    private function beforeAdd($parameters)
    {
        //（1）产品与签名关系验证
        $sign = (new SignService())->getByPrimaryKey($parameters['sign_id']);
        if (empty($sign->project_id) || $parameters['project_id'] != $sign->project_id) {
            $this->failMessage = '签名和产品未绑定';
            $this->writeFailLog();
            return ['error_code' => 1, 'message' => $this->failMessage];
        }

        //（2）产品密钥验证
        $project = (new ProjectService())->getByPrimaryKey($parameters['project_id'], ['id', 'encrypt']);
        if (strcmp($project->encrypt, $parameters['token']) !== 0) {
            $this->failMessage = '产品密钥错误';
            $this->writeFailLog();
            return ['error_code' => 2, 'message' => $this->failMessage];
        }

        //（3）有效发送时间验证
        $start = strtotime(date('Y-m-d ' . $this->config->working_time->start));
        $end = strtotime(date('Y-m-d ' . $this->config->working_time->end));
        if ($parameters['type'] == 1&($_SERVER['REQUEST_TIME'] < $start || $_SERVER['REQUEST_TIME'] > $end)) {
            $this->failMessage = '不在发送时间段内，工作时间：' . $this->config->working_time->start . '-' . $this->config->working_time->end;
            $this->writeFailLog();
            return ['error_code' => 3, 'message' => $this->failMessage];
        }
        //（4）签名状态验证（是否开启）
        if (!$sign->status) {
            $this->failMessage = '签名已失效';
            $this->writeFailLog();
            return ['error_code' => 4, 'message' => $this->failMessage];
        }

        //（5）签名与通道关系验证（是否绑定），如果已绑定需要验证签名在通道中报备是否通过。
        //（6）通道状态验证（是否开启）
        $channel = null;
        if ($parameters['channel_id']) { //a. 指定通道：验证签名与通道关系验证（1).是否绑定;2)签名在绑定的通道中是否报备通过;3)通道是否有效）
            $channelSign = (new ChannelSignService())->getByChannelSign($parameters['channel_id'], $parameters['sign_id']);
            if (empty($channelSign)) { //1).是否绑定
                $this->failMessage = '签名和通道没有绑定';
                $this->writeFailLog();
                return ['error_code' => 5, 'message' => $this->failMessage];
            } else if (1 != $channelSign->check_status) { //2)签名在绑定的通道中是否报备通过
                $this->failMessage = '签名在通道中没有报备成功';
                $this->writeFailLog();
                return ['error_code' => 6, 'message' => $this->failMessage];
            } else { //3)通道是否有效
                $channel = (new ChannelService())->getByPrimaryKey($parameters['channel_id'], ['id', 'status', 'check_sign', 'check_replay', 'check_code']);
                if (1 == $channel->status) {
                    $this->selectedChannelId = $parameters['channel_id'];
                } else {
                    $this->failMessage = '通道无效';
                    $this->writeFailLog();
                    return ['error_code' => 7, 'message' => $this->failMessage];
                }
            }
        } else { //b. 未指定通道：选择签名的主用通道，如未指定主用通道则随机选择一条绑定的通道
            $channelId = (new ChannelService())->getChannelIdBySignId($parameters['sign_id'], $parameters['type']);
            if (!$channelId) {
                $this->failMessage = '签名下没有有效的通道';
                $this->writeFailLog();
                return ['error_code' => 8, 'message' => $this->failMessage];
            }
            $channel = (new ChannelService())->getByPrimaryKey($channelId, ['id', 'status', 'check_sign', 'check_replay']);
            $this->selectedChannelId = $channel->id;
        }

        //（7）短信完整性验证
        if(empty($parameters['send_contents'])){
            $this->failMessage = '短信内容验证为空或格式不正确';
            $this->writeFailLog();
            return ['error_code' => 12, 'message' => $this->failMessage];
        }
        //一次发送超过500条
        if(count($parameters['send_contents']) > self::MOBILE_COUNT){
            $this->failMessage = '短信内容超限，一次发送最多'.self::MOBILE_COUNT.'条';
            $this->writeFailLog();
            return ['error_code' => 13, 'message' => $this->failMessage];
        }
        $this->validateSign($channel, $parameters['send_contents'], $sign->name);
        if (empty($this->validatedSms['successed'])) {
            $this->failMessage = '全部短信内容验证不通过';
            $this->writeFailLog();
            return ['error_code' => 9, 'message' => $this->failMessage];
        }

        //（8）黑名单排查 （9）白名单排查 （11）批量发送携带“超级白名单”
        if($this->parameters['type'] != 2) {
            if (isset($parameters['supper_whitelist'])) {
                $this->filterBlacklist($parameters['sign_id'], $parameters['supper_whitelist']);
            } else {
                $this->filterBlacklist($parameters['sign_id']);
            }
            if (empty($this->validatedSms['successed'])) {
                $this->failMessage = '全在黑名单中';
                $this->writeFailLog();
                return ['error_code' => 10, 'message' => $this->failMessage];
            }
        }

        //（10）b. 当天发送超过7条，后续短信不下发，返回业务线超过发送限制提示 incr expireAt指定失效时间
        if ($this->parameters['type']==2 || !$this->parameters['is_repetition']) {
            $this->filterSendingNumber($parameters['sign_id'], $channel->id);
            if (empty($this->validatedSms['successed'])) {
                $this->failMessage = '全部超过发送次数，请更换通道或更换签名。';
                $this->writeFailLog();
                return ['error_code' => 11, 'message' => $this->failMessage];
            }
        }
        return ['error_code' => 0, 'message' => '验证完成'];
    }

    /**
     * 过滤超过发送限制的手机号码
     * 同一手机号码同一签名同一通道当天发送超过指定（目前是7）条，后续短信不下发，返回业务线超过发送限制提示.
     * @param int $signId 签名ID
     * @param int $channelId 通道ID
     * @return null
     */
    private function filterSendingNumber($signId, $channelId)
    {
        //获取要发送的，也就是过滤后剩余的要发送的短信列表的发送次数
        $mobileList = IteratorUtil::arrayColumn($this->validatedSms['successed'], 'mobile');
        $sendingNumber = (new SendingNumberService())->getSendingNumber($signId, $channelId, $mobileList);
        if (empty($sendingNumber)) {
            return;
        }

        if($signId == 34){
            $sendNum = 100;
        }else{
            $sendNum = $this->config->sending_number[$this->parameters['type']];
        }
        //将发送数量达到上限的从发送任务重去除掉，在白名单和超级白名单的照常发送。
        foreach ($this->validatedSms['successed'] as $key => $sms) {
            if (!in_array($sms['mobile'], $this->mobileWhitelist) &&
                isset($sendingNumber[$sms['mobile']]) &&
                 $sendNum <= $sendingNumber[$sms['mobile']]
            ) {
                $this->validatedSms['excess'][] = $sms;
                unset($this->validatedSms['successed'][$key]);
            }
        }
        return;
    }

    /**
     * 过滤当前签名的黑名单，和超级黑名单。
     * （8）黑名单排查
     * （9）白名单排查
     * （11）批量发送携带“超级白名单”
     * @author wangjianghua
     * @date 2018-05-03 17:03
     * @param int $sign 签名ID
     * @param array $supperWhitelist 超级白名单
     * @return null
     */
    private function filterBlacklist($sign, $supperWhitelist = [])
    {
        $blackList = (new BlacklistService())->getBySignId($sign);
        $whitelist = (new WhitelistService())->getByStatus();
        if ($whitelist->count()) {
            $this->mobileWhitelist = IteratorUtil::arrayColumn($whitelist, 'mobile');
        }
        if (!empty($supperWhitelist)) {
            $this->mobileWhitelist = array_merge($this->mobileWhitelist, $supperWhitelist);
        }

        //如果手机号码在白名单中，需要从黑名单中去除。求差集。
        $blackList = array_diff($blackList, $this->mobileWhitelist);

        //过滤掉发送任务重的黑名单数据
        foreach ($this->validatedSms['successed'] as $key => $successed) {
            if (in_array($successed['mobile'], $blackList)) {
                $this->validatedSms['blacklist'][] = $successed;
                unset($this->validatedSms['successed'][$key]);
            }
        }
        return;
    }

    /**
     * 验证短信完整度
     *  1.短信内容不能为空
     *  2.短信签名
     *  3.短信退订字样
     * @author wangjianghua
     * @date 2018-05-02 13:49
     * @param obj $channel object
     * @param array $sendContents array
     * @return array
     * [
     *      'successed' => [], //验证通过的
     *      'content_failed' => [], //内容为空的
     *      'sign_failed' => [], //签名验证失败的
     *      'td_failed' => [], //退订字样验证失败的
     *      'blacklist' => [], //在黑名单中的
     *      'excess' => [], //超过发送限制的
     * ]
     */
    private function validateSign($channel, $sendContents, $sign)
    {
        $td = "退订";
        $code = "验证码";
        foreach ($sendContents as $content) {
            if (empty($content['content'])) {
                $this->validatedSms['content_failed'][] = $content;
            } else if ($channel->check_sign && strpos($content['content'], $sign) === false) {
                //验证签名
                $this->validatedSms['sign_failed'][] = $content;
            } else if ($channel->check_replay && strpos($content['content'], $td) === false) {
                //验证退订文案
                $this->validatedSms['td_failed'][] = $content;
            } else if ($channel->check_code && strpos($content['content'], $code) === false) {
                //验证验证码字样
                $this->validatedSms['code_failed'][] = $content;
            } else if (!preg_match('/^((1[3,5,8][0-9])|(14[5,7])|(17[0,1,3,4,5,6,7,8])|(19[7,8,9]))\d{8}$/',$content['mobile'])){
                //手机号格式验证失败
                $this->validatedSms['number'][] = $content;
            }else {
                $this->validatedSms['successed'][] = $content;
            }
        }
        return $this->validatedSms;
    }

    /**
     * 将产品提交的发送任务存储到数据库
     * @author wangjianghua
     * @date 2018-05-04 16:20
     * @return int task id
     */
    private function addTask()
    {
        //添加任务，生成短信平台内部任务ID。
        $task = [
            'task_name' => isset($this->parameters['task_name']) ? $this->parameters['task_name'] : '',
            'type' => isset($this->parameters['type']) ? $this->parameters['type'] : 0,
            'project_id' => isset($this->parameters['project_id']) ? $this->parameters['project_id'] : 0,
            'sms_total' => count($this->parameters['send_contents']),
            'success_total' => 0,
            'send_status' => 0,
            'channel_id' => $this->selectedChannelId,
            'sign_id' => isset($this->parameters['sign_id']) ? $this->parameters['sign_id'] : 0,
            'dispatch_type' => isset($this->parameters['send_type']) ? $this->parameters['send_type'] : 0,
            'create_time' => $_SERVER['REQUEST_TIME'],
            'update_time' => $_SERVER['REQUEST_TIME'],
        ];
        $sendTaskService = new SendTaskService();
        $taskId = $sendTaskService->addOne($task);
        if (!$taskId) {
            return 0;
        }

        //任务详情记录到数据库
        $taskDetail = $this->mergeSmsList($taskId);
        $addResult = (new SendTaskDetailService())->addMany($taskDetail);
        if (!$addResult) { //添加
            $sendTaskService->deleteByPrimaryKey($taskId);
            return 0;
        }

        return $taskId;
    }

    /**
     * 将验证通过需要发送的、内容为空的、签名验证失败的、
     * 退订字样验证失败的、在黑名单中的、超过发送限制的短信内容合并，
     * 合并时带上不同分组的验证状态
     * 5：在黑名单中
     * 6：内容为空的
     * 7：签名验证失败的
     * 8：退订字样验证失败的
     * 9：超过发送限制的
     * 10：不再发送时间段内的
     * @author wangjianghua
     * @date 2018-05-04 17:26
     * @return array
     */
    private function mergeSmsList($taskId)
    {
        $taskDetail = [];

        //创建短信发送任务详情数据
        $createData = function ($smsContents, $sendStatus) use ($taskId) {
            foreach ($smsContents as $sms) {
                $taskDetail[] = [
                    'task_id' => $taskId,
                    'project_id' => $this->parameters['project_id'],
                    'type' => $this->parameters['type'],
                    'channel_id' => $this->selectedChannelId,
                    'sign_id' => $this->parameters['sign_id'],
                    'mobile' => $sms['mobile'],
                    'channel_task_id' => '',
                    'content' => is_array($sms['content'])?json_encode($sms['content']):$sms['content'],
                    'num' => 0,
                    'send_time' => 0,
                    'back_time' => 0,
                    'send_status' => $sendStatus,
                    'create_time' => $_SERVER['REQUEST_TIME'],
                    'update_time' => $_SERVER['REQUEST_TIME'],
                ];
            }
            return $taskDetail;
        };

        //正常需要发送的
        if ($this->validatedSms['successed']) {
            $taskDetail = array_merge($taskDetail, $createData($this->validatedSms['successed'], 0));
        }

        //在黑名单中的
        if ($this->validatedSms['blacklist']) {
            $taskDetail = array_merge($taskDetail, $createData($this->validatedSms['blacklist'], 5));
        }

        //内容为空的
        if ($this->validatedSms['content_failed']) {
            $taskDetail = array_merge($taskDetail, $createData($this->validatedSms['content_failed'], 6));
        }

        //签名验证失败的
        if ($this->validatedSms['sign_failed']) {
            $taskDetail = array_merge($taskDetail, $createData($this->validatedSms['sign_failed'], 7));
        }

        //签名验证失败的
        if ($this->validatedSms['td_failed']) {
            $taskDetail = array_merge($taskDetail, $createData($this->validatedSms['td_failed'], 8));
        }

        //超过发送限制的
        if ($this->validatedSms['excess']) {
            $taskDetail = array_merge($taskDetail, $createData($this->validatedSms['excess'], 9));
        }
        return $taskDetail;
    }

    /**
     * 发布任务
     * @author wangjianghua
     * @date 2018-05-05 11:27
     * @return null
     */
    private function publishTask()
    {
        $taskMessage = [
            'type' => $this->parameters['type'],
            'task_id' => $this->taskId,
            'sign_id' => $this->parameters['sign_id'],
            'channel_id' => $this->selectedChannelId,
            'send_contents' => $this->validatedSms['successed'],
            'ext' => $this->parameters['ext']
        ];
        $connectArgs = [
            'host' => $this->config->mq->host,
            'vhost' => '/',
            'port' => $this->config->mq->port,
            'login' => $this->config->mq->user,
            'password' => $this->config->mq->password
        ];
        try {
            //创建连接和channel
            $conn = new AMQPConnection($connectArgs);
            if (!$conn->connect()) {
                throw new Exception("连接失败");
            }
            $channel = new AMQPChannel($conn);

            //交换机名 路由key
            $keyRoute = $this->config->mq->queue[$this->parameters['type']];
            $specialChannel = json_decode(json_encode($this->config->specialChannel),true);
            $specialKey = '_P'.$this->parameters['project_id'].'_C'.$this->parameters['channel_id'].'_S'.$this->parameters['sign_id'];
            if(!empty($specialChannel[$specialKey])){
                $keyRoute .= $specialKey;
            }
            $exchangeName = $this->config->mq->exchange; //默认的交换机

            //创建交换机
            $exchange = new AMQPExchange($channel);
            $exchange->setName($exchangeName);
//            $exchange->setType(AMQP_EX_TYPE_DIRECT); //direct类型
//            $exchange->setFlags(AMQP_DURABLE); //持久化
//            if ( !$exchange->declare() ) {
//                $conn->disconnect();
//                throw new Exception("创建交换机失败");
//            }

            //创建队列
            $queue = new AMQPQueue($channel);
            $queue->setName($keyRoute);
            $queue->setFlags(AMQP_DURABLE); //持久化
            $queue->declareQueue();

            //绑定交换机与队列，并指定路由键
            if (!$queue->bind($exchangeName, $keyRoute)) {
                $conn->disconnect();
                throw new Exception("绑定交换机和队列并且指定路由，全部失败。");
            }

            //发送消息
            $channel->startTransaction(); //开始事务
            if (!$exchange->publish(json_encode($taskMessage), $keyRoute)) { //将你的消息通过制定routingKey发送
                $channel->commitTransaction(); //提交事务
                $conn->disconnect();
                throw new Exception("发送消息失败");
            }
            $channel->commitTransaction(); //提交事务

            $conn->disconnect();
            return true;
        } catch (Exception $exc) {
            //异常信息
            $message = 'error_message: ' . $exc->getMessage() . "\n";
            $message .= "error_trace: " . $exc->getTraceAsString() . "\ndata: " . json_encode($taskMessage);
            //发送异常预警短信
            (new WarningService())->sendWarningSms($message);
            //日志目录是否存在
            $logPath = LOG_PATH . 'mq/publish/' . date("Y/m") . '/';
            if (!file_exists($logPath)) {
                mkdir($logPath, 0775, true);
            }

            //写日志
            $logger = new FileAdapter($logPath . 'error_' . date('d') . '.log');
            $formatter = new LineFormatter("[%date%][%type%]\n%message%\n");
            $formatter->setDateFormat('Y-m-d H:i:s');
            $logger->setFormatter($formatter);
            $logger->error($message);
            return false;
        }
    }
}
