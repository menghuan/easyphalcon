<?php
use Phalcon\Cli\Task;

/**
 * 后台任务
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 10:55:59
 */
class MainTask extends Task
{
    /**
     * default
     */
    public function mainAction()
    {
        echo "default method\n";
    }
    
    /**
     * 定时跑web端的短地址批次任务
     * 运行方式:
     *      cd /opt/www/frog/public
     *      php cli.php Main dealShortUrlTaskList 1
     * 参数：
     *      任务进程数量
     * @author 王江华 <dongguangming@qiaodata.com>
     * @date 2017-07-20 15:38
     * @return null
     */
    public function dealShortUrlTaskListAction($parameters)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        
        //总进程数量
        $totalProcess = $parameters ? intval($parameters[0]) : 1;
        if ($totalProcess <= 0) {
            $totalProcess = 1;
        }
        
        //推荐
        $done = []; //记录每个进程完成情况
        $shorturlticketTask = new ShorturlticketTask();
        for ($shorturlticketPid = 0; $shorturlticketPid < $totalProcess; $shorturlticketPid ++) {
            //启动子进程
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo 'could not fork';
                continue;
            } else if ($pid) {
                pcntl_wait($status);
                continue;
            } else {
                //子进程中执行推荐操作
                echo "total:".$totalProcess."shorturlticketPid:".$shorturlticketPid.PHP_EOL;
                $done[$pid] = false; //默认没有完成
                $shorturlticketTask->checkShortUrlTaskListAction($totalProcess, $shorturlticketPid);
                if ($shorturlticketTask->isDone()) {
                    $done[$shorturlticketPid] = true;
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " all task is end".PHP_EOL;
                }
                exit(0); 
            }
        }
        return;
    }
    
    
    /**
     * 定时消费点击日志并写入数据库
     * 运行方式:
     *      cd /opt/www/frog/public
     *      php cli.php Main dealShortUrlClickLog 1
     * 参数：
     *      任务进程数量
     * @author 王江华 <dongguangming@qiaodata.com>
     * @date 2017-07-20 15:38
     * @return null
     */
    public function dealShortUrlClickLogAction($parameters)
    {
        //总进程数量
        $totalProcess = $parameters ? intval($parameters[0]) : 1;
        if ($totalProcess <= 0) {
            $totalProcess = 1;
        }
        
        //推荐
        $done = []; //记录每个进程完成情况
        $shorturlticketTask = new ShorturlticketTask();
        
        for ($shorturlticketPid = 0; $shorturlticketPid < $totalProcess; $shorturlticketPid ++) {
            //启动子进程
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo 'could not fork';
                continue;
            } else if ($pid) {
                pcntl_wait($status);
                continue;
            } else {
                //子进程中执行推荐操作
                echo "total:".$totalProcess."shorturlticketPid:".$shorturlticketPid.PHP_EOL;
                $done[$pid] = false; //默认没有完成
                $shorturlticketTask->consumerClickLogAction($totalProcess, $shorturlticketPid);
                if ($shorturlticketTask->isDone()) {
                    $done[$shorturlticketPid] = true;
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " all click log is end".PHP_EOL;
                }
                exit(0); 
            }
        }
        return;
    }
    
    
    /**
     * 从发送任务中取出任务，调用对应通道的发送接口发送短信
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-08 11:28
     * @return
     */
    public function sendSmsAction($process = [])
    {
        $sendSmsTask = new \SendsmsTask();
        $process = empty($process)?'mainsendprocess':$process[0];
        $sendSmsTask->send(1,$process);
        return;
    }

    /**
     * 从发送任务中取出任务，调用对应通道的发送接口发送短信
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-19 11:40
     * @return
     */
    public function sendTriggerSmsAction()
    {
        $sendSmsTask = new \SendsmsTask();
        $sendSmsTask->send(2);
        return;
    }

    /**
     * 发送短信
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-02 11:17
     * @return null
     */
    public function smsPlatformSendAction()
    {
        if (!$this->send) {
            die;
        }
        /**
         * 回掉函数
         */
        $callback = function ($msg) {
            $sendsmsTask = new SendsmsTask();
            $sendsmsTask->send($msg->body);
            echo '任务发送完毕' . PHP_EOL;
        };
        /**
         * 当程序执行完成后执行的函数
         */
        $shutdownFunction = function ($channel, $connection) {
            $channel->close();
            $connection->close();
            echo '关闭链接' . PHP_EOL;
        };

        //连接MQ
        $connection = new AMQPStreamConnection($this->mq->host,
            $this->mq->port,
            $this->mq->user,
            $this->mq->password);
        $channel = $connection->channel();
        $channel->basic_qos(null, 1, null);
        //获取一条数据，验证队列是否有数据
        //$msg = $channel->basic_get($this->mq->queue);
        $channel->basic_consume($this->mq->queue, '', false, true, false, false, $callback);
        //当程序执行完成后执行的函数，其功能为可实现程序执行完成的后续操作。
        register_shutdown_function($shutdownFunction, $channel, $connection);

        //监控队列
        while (count($channel->callbacks)) {
            $channel->wait();
			usleep(1000);
        }
    }


    /**
     * [statisticsIpAction 短地址点击统计 按ip统计表任务]
     * @author liuguangyuan 2018-04-17
     * @return [type] [description]
     */
    public function statisticsIpAction(){
        $statisticshorturl = new StatisticshorturlTask();
        $statisticshorturl->statisticsIp();
        return;
    }

    /**
     * [statisticsShortUrlClickAction ]
     * 短链接点击日志统计任务 针对每个产品 每个任务下面的
     * @author liuguangyuan 2018-04-17
     * @return [type] [description]
     */
    public function statisticsShortUrlClickAction(){
        $statisticshorturl = new StatisticshorturlTask();
        $statisticshorturl->StatisticsShortUrlClick();
        return;
    }


    /**
     * [statisticsShortUrlTotalAction ]
     * 短链接点击日志 总体统计
     * @author liuguangyuan 2018-04-19
     * @return [type] [description]
     */
    public function statisticsShortUrlTotalAction(){
        $statisticshorturl = new StatisticshorturlTask();
        $statisticshorturl->statisticsTotal();
        return;
    }

    /**
     * [StatisticshorturlTaskCtime]
     *  短链接点击日志 按任务下批次生成时间统计
     * @author liuguangyuan 2018-04-20
     */
    public function statisticshorturlTaskCtimeAction(){
        $statisticshorturl = new StatisticshorturlTask();
        $statisticshorturl->statisticsTaskByCtime();
        return;
    }

    /**
     * [StatisticshorturlTaskClickTime]
     * 短链接点击日志  按任务下批次点击时间统计
     * @author liuguangyuan 2018-04-20
     */
    public function statisticshorturlTaskClickTimeAction(){
        $statisticshorturl = new StatisticshorturlTask();
        $statisticshorturl->statisticshorturlTaskClickTime();
        return;
    }

    /**
     * [StatisticshorturlHourTask]
     * 短链接点击日志 分时段按任务统计
     * @author liuguangyuan 2018-04-20
     */
    public function statisticshorturlHourTaskAction(){
        $statisticshorturl = new StatisticshorturlTask();
        $statisticshorturl->statisticshorturlHourTask();
        return;
    }
}
