<?php
use Phalcon\Cli\Task;

/**
 * 后台任务
 * @author wnagjianghua
 * @date 2018-3-2 10:55:59
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
     *      多任务进程数量
     * @author wangjianghau
     * @date 2018-07-20 15:38
     * @return null
     */
    public function dealTaskListAction($parameters)
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
        $task = new Task();
        for ($sid = 0; $sid < $totalProcess; $sid ++) {
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
                echo "total:".$totalProcess."sid:".$sid.PHP_EOL;
                $done[$pid] = false; //默认没有完成
                $task->checkTaskListAction($totalProcess, $sid);
                if ($task->isDone()) {
                    $done[$sid] = true;
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " all task is end".PHP_EOL;
                }
                exit(0); 
            }
        }
        return;
    }

    /**
     * 发送短信
     * @author wnagjianghua
     * @date 2018-03-02 11:17
     * @return null
     */
    public function SendAction()
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
}
