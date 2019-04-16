<?php
include APP_PATH . '/common/library/vendor/PHPExcel/Classes/PHPExcel/IOFactory.php';

use Phalcon\Cli\Task;
use Common\Library\Utils\SnowFlake;
use Common\Services\RedisService;
use Common\Services\SmsPlatForm\ProjectService;
use Common\Services\SmsPlatForm\ShortUrlService;
use Common\Services\SmsPlatForm\ShortUrlTaskService;
use Common\Services\SmsPlatForm\ShortHistoryService;
use Common\Services\SmsPlatForm\ShorturlListService;
use Common\Services\SmsPlatForm\ShortUrlClickLogService;

/**
 * 短地址发号器
 * 批量生成 定量提前存储及提取 
 * @author 王江华 <wangjianghua@qiaodata.com>
 * @date 2018-04-03 
 */
class ShorturlticketTaskBak  extends Task
{
    /**
     * 批次任务是否完成
     * @var bool
     */
    private $done = false; 
    
    /**
     * 当前进程号
     * @var int
     */
    private $pid = 0; 
    
    /**
     * 总进程数
     * @var int
     */
    private $totalProcess = 0; 
    
    /**
     * 当前workid
     * @var int
     */
    private $workId = 0; 
    
    /**
     * 当前数据中心id
     * @var int
     */
    private $datacenterId = 0; 
    
    /**
     * redis 
     * @var object
     */
    private $redisService = null;
    
    /**
     * 批次任务是否完成
     * @var object
     */
    protected $snowflake = null;
    
    /**
     * 参与短链接计算的数组
     * @var array
     */
    protected $base32 = []; 
    
    /**
     * 分表表名
     * @var string
     */
    protected $tableName = ""; 
    
    /**
     * 批次任务服务
     * @var object
     */
    protected $shortUrlTaskService = null; 
    
    /**
     * 短地址历史排重服务
     * @var object
     */
    protected $shortHistoryService = null; 
    
    /**
     * 产品服务
     * @var object
     */
    protected $projectService = null; 
    
    /**
     * 短域名服务
     * @var object
     */
    protected $shorturlListService = null; 
    
    /*
     * 短地址点击日志服务
     * &var object
     */
    protected $shortUrlClickLogService = null;


    /**
     * 队列剩余数
     * @var int
     */
    const lastQueueNums = 100000; 
    
    /**
     * 多进程短链接发号器 每个进程要跑的数量
     * @var int
     */
    const perForkNums = 1000000; 
    
    
    /**
     * 是否运行完成
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 10:50
     * @return bool
     */
    public function isDone()
    {
        return $this->done;
    }
    
    
    /**
     * 初始化成员属性
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @return null
     */
    public function initializeAttribute($totalProcess = 1, $pid = 1)
    {
        $this->workId = 1;
        $this->datacenterId = 1;
        $this->totalProcess = $totalProcess >= 1 ? $totalProcess : 1;
        $this->pid = $pid;
        //实例化redis
        $this->redisService = new RedisService();
        //实例化雪花算法
        $this->snowflake = new SnowFlake($this->workId, $this->datacenterId); 
        //参与短地址计算的数组
        $this->base32 = [  
           "a" , "b" , "c" , "d" , "e" , "f" , "g" , "h" ,  
           "i" , "j" , "k" , "l" , "m" , "n" , "o" , "p" ,
           "q" , "r" , "s" , "t" , "u" , "v" , "w" , "x" ,
           "y" , "z" , "1" , "2" , "3" , "4" , "5" , "6" , 
           "7" , "8" , "9" , "A" , "B" , "C" , "D" , "E" , 
           "F" , "G" , "H" , "I" , "J" , "K" , "L" , "M" ,
           "N" , "O" , "P" , "Q" , "R" , "S" , "T" , "U" ,
           "V" , "W" , "X" , "Y" , "Z" ];
        $this->shortUrlTaskService = new ShortUrlTaskService();
        $this->shortHistoryService = new ShortHistoryService();
        $this->projectService = new ProjectService();
        $this->shortUrlClickLogService = new ShortUrlClickLogService(); //总表
        return;
    }
    
    /*
     * 多进程 短地址发号器
     * 每次5个进程 每个进程10万总共50万 pipeline压入队列在分钟级别 经测试10分钟内
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     */
    public function ticketAction(){
        //初始化成员属性
        $this->initializeAttribute();
        //检测队列剩余量 大于10万的话不生成
        $queueNum = $this->redisService->llen($this->config->redisCommonShortUrlQueue);
        if($queueNum > self::lastQueueNums){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "the data ".$queueNum." is full".PHP_EOL;
            exit();
        }
        for ($i = 0; $i < 10; $i ++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                echo 'could not fork';
                continue;
            } else if ($pid) {
                pcntl_wait($status);
            } else {
                //通过pipeline批量生成
                $pipe = $this->redisService->multi(Redis::PIPELINE);  
                for($j = 0; $j < self::perForkNums;$j ++) {  
                    $newid = $this->patchTicketAction();
                    echo $newid.PHP_EOL;
                    //压入队列
                    $pipe->lpush($this->config->redisCommonShortUrlQueue,$newid);
                } 
                $result = $pipe->exec();
                print_r($result);
                unset($result);
                echo "Date: " . date("Y-m-d H:i:s", time()) . "the ".$i." child process is end".PHP_EOL;
                exit();
            }
        }
    }
    
    
    /*
     * 检测队列剩余情况
     * * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     */
    public function checkQueueNumsAction(){
        //初始化成员属性
        $this->initializeAttribute();
        $queueNum = $this->redisService->llen($this->config->redisCommonShortUrlQueue);
        if($queueNum <= self::lastQueueNums){
            $this->ticketAction(); // 重新补充数据
        }
        echo "Date: " . date("Y-m-d H:i:s", time()) . "the check data ".$queueNum." is full sucess".PHP_EOL;
        exit();
    }
    
    
    
    /*
     * 发号器生成派发
     * 压入队列 进入去重库
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     * @return string
     */
    public function patchTicketAction(){
        //初始化成员属性
        $this->initializeAttribute();
        //先通过雪花算法 计算出一个key
        $id = $this->snowflake->nextId(); 
        //echo $id.PHP_EOL;
        $secretKey = "a①d shor€t?";
        $hex = md5($id.$secretKey);  
        $hexLen = strlen($hex); 
        $subHexLen = $hexLen / 8;   
        $output = array();   
        for ($i = 0; $i < $subHexLen; $i++) {   
          $subHex = substr ($hex, $i * 8, 8);   
          $int = 0x3FFFFFFF & (1 * ('0x'.$subHex));   
          $out = '';   
          for ($j = 0; $j < 5; $j++) {   
            $val = 0x0000001F & $int;  
            $out .= $this->base32[$val];   
            $int = $int >> 5;  
          }   
          $output[] = $out;   
        } 
        $newid = $output[mt_rand(0, 3)];
        unset($output,$out);
        return $newid;
    }  
    
    
    /*
     * 监控业务批次任务 进行短地址的批量获取
     * 目前实现方法是 从公共短地址队列中批量pop出结果后  
     * 然后把所有取出的而结果放入历史库中set+数据库
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     * @return string
     */
    public function checkShortUrlTaskListAction($totalProcess = 0, $pid = 0){
        try {
            //初始化成员属性
            $this->initializeAttribute($totalProcess, $pid);
            //获取未开始执行的批次任务
            $shortList = $this->shortUrlTaskService->getByStatus(0);
            $shortList = $shortList->toArray();    
            foreach($shortList as $sk=>$sv){
                if(!empty($sv['short_url_nums'])){
                    if(1 == $sv['long_url_nums']){ //多个需要解析csv文件
                        $this->multiLongUrl2multiShortUrl($sv);
                    }else if(2 == $sv['long_url_nums']){ //单个
                        $this->singleLongUrl2multiShortUrl($sv);
                    } 
                }
            }
            unset($shortList);
            $this->done = true;
        } catch (\Exception $exc) {
            echo $exc->getMessage()
                    . ' file:' . $exc->getFile()
                    . ' line:' . $exc->getLine()
                    . ' trace:' . $exc->getTraceAsString();
        }
        return;
    }
    
    
    /*
     * 单个长连接对应多个短链接的情况处理 
     * 当短链接为1个时是单个长地址转单个短信地址
     * 当短链接为多个时是单个长地址转多个短地址
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     * @param singleLongUrl array
     * @return string
     */
    public function singleLongUrl2multiShortUrl($singleLongUrl = []){
        $return = $shortUrlArr = [];
        if(empty($singleLongUrl)){
            return $return;
        }
        //检查任务是否已经被执行过
        $shortUrlTaskInfo = $this->shortUrlTaskService->getByPrimaryKey($singleLongUrl['id'],["id","status"]);
        if(!empty($shortUrlTaskInfo) && $shortUrlTaskInfo['status'] == 1){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." is dealed ".PHP_EOL;
            return;
        }
        $expire_time = $singleLongUrl['short_url_expiretime'] ? strtotime($singleLongUrl['create_time']) + $singleLongUrl['short_url_expiretime']*86400 : 0;
        //循环这么多个短链接 对应到同一个长连接中
        for($j = 1; $j <= $singleLongUrl['short_url_nums'];$j ++){
            $shortUrl = $this->redisService->rpop($this->config->redisCommonShortUrlQueue);
            if(empty($shortUrl)){
                echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." get shorturl fail".PHP_EOL;
                continue;
            }
            //写入到set历史排重库中
            $reset = $this->redisService->sadd($this->config->redisCommonShortUrlHistorySet,$shortUrl);
            if(0 > $this->redisService->ttl($this->config->redisCommonShortUrlHistorySet)){
                $this->redisService->expire($this->config->redisCommonShortUrlHistorySet,time()+86400*30*12);
            }
            //写入排重库表中
            $reset2 = $this->shortHistoryService->insertOneIgnoreDuplicate(['short'=>$shortUrl,'short_task_project'=>$singleLongUrl['short_task_project']]);
            //写入到hash有效库中 并设置过期时间
            $reset3 = $this->redisService->hset($this->config->redisShortUrlHistoryHash.":".$shortUrl,json_encode(['short'=>$shortUrl,'longurl'=>$singleLongUrl['long_url'],'short_task_project'=>$singleLongUrl['short_task_project']]));
            if($expire_time){
                if(0 > $this->redisService->ttl($this->config->redisShortUrlHistoryHash.":".$shortUrl)){
                    $this->redisService->expire($this->config->redisShortUrlHistoryHash.":".$shortUrl,$expire_time+mt_rand(3600,86400));
                }
            }
            if(false == $reset || false == $reset2 || false == $reset3){
                echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl set history exists".PHP_EOL;
            }
            $shortUrlArr[] = $shortUrl; 
        }
        if(empty($shortUrlArr)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." get shorturllist fail".PHP_EOL;
            return;
        }
        //获取短域名前缀跟队列中的标示进行对接组成一个完整的短地址
        $shorturlprefix = $this->getShortUrl($singleLongUrl['short_task_project']);
        foreach($shortUrlArr as $sv){
            $return[] = [
                'id' => $this->snowflake->nextId(),
                'short_task_project' => $singleLongUrl['short_task_project'],
                'short_task_id' => $singleLongUrl['short_task_id'],
                'short_url_task_id' => $singleLongUrl['id'],
                'long_url' => $singleLongUrl['long_url'],
                'short_url' => $shorturlprefix."/".$sv,
                'short_url_expiretime' => $singleLongUrl['short_url_expiretime'],
                'create_time' => $singleLongUrl['create_time'], //生成时间按批次任务提交时间来计算
                'end_time' => $expire_time ? date("Y-m-d",$expire_time)." 23:59:59" : "2028-12-31 23:59:59"
            ];
        }
        //根据产品id获取短链接分表表名
        $this->getShardTable($singleLongUrl['short_task_project'],"sms_short_url");        
        $shortUrlService = new ShortUrlService($this->tableName);
        try {
            //写入短地址分表中
            $shortUrlAddResult = $shortUrlService->insertManyIgnoreDuplicate($return);
            //更新任务状态
            $this->shortUrlTaskService->updateByPrimaryKey($singleLongUrl['id'], ['status'=>1]);
        } catch (\Exception $exc) {
            echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl  taskid ".$singleLongUrl['id']." wrong info ".$exc->getMessage()
                . " file:" . $exc->getFile()
                . " line:" . $exc->getLine()
                . " trace:" . $exc->getTraceAsString();
        }
        echo "Date: " . date("Y-m-d H:i:s", time()) . "the  taskid ".$singleLongUrl['id']." child process is end".PHP_EOL;
        unset($return,$shortUrlArr,$shortUrlTaskInfo,$singleLongUrl);
        return;
    }
    
    
    /*
     * 多个长连接对应多个短链接的情况处理
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     * @param mutliLongUrl array
     * @return string
     */
    public function multiLongUrl2multiShortUrl($mutliLongUrl = []){
        $return = $shortUrlArr = $longUrlArr = [];
        if(empty($mutliLongUrl)){
            return $return;
        }
        $longUrlCnt = 0;
        //检查任务是否已经被执行过
        $shortUrlTaskInfo = $this->shortUrlTaskService->getByPrimaryKey($mutliLongUrl['id'],["id","status"]);
        $shortUrlTaskInfo = $shortUrlTaskInfo->toArray();
        if(!empty($shortUrlTaskInfo) && $shortUrlTaskInfo['status'] == 1){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." is dealed ".PHP_EOL;
            return;
        }
        //需要解析csv或者excel格式文件 解析出文件中的长连接 
        $reader = PHPExcel_IOFactory::load(APP_PATH."/".$mutliLongUrl['long_url']);
        $longUrlListTemp = $reader->getActiveSheet()->toArray(null,true,true,true);
        foreach($longUrlListTemp as $k=>$v){
            if(empty($v['A'])){
                continue;
            }
            $longUrlCnt ++;
        }
        if(empty($longUrlListTemp)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']."  excel ".$mutliLongUrl['long_url']." is empty".PHP_EOL;
            return;
        }
        if($longUrlCnt !== $mutliLongUrl['short_url_nums']){
            $mutliLongUrl['short_url_nums'] = $longUrlCnt; //当不一样的时候进行修改
        }
        $expire_time = $mutliLongUrl['short_url_expiretime'] ? strtotime($mutliLongUrl['create_time']) + $mutliLongUrl['short_url_expiretime']*86400 : 0;    
        //循环这么多个短链接 对应到多个长连接中
        for($j = 1; $j <= $mutliLongUrl['short_url_nums'];$j ++){
            if(!empty($longUrlListTemp[$j]['A'])){
                $shortUrl = $this->redisService->rpop($this->config->redisCommonShortUrlQueue);
                if(empty($shortUrl)){
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." get shorturl fail".PHP_EOL;
                    continue;
                }
                //写入到set历史排重库中
                $reset = $this->redisService->sadd($this->config->redisCommonShortUrlHistorySet,$shortUrl);
                if(0 > $this->redisService->ttl($this->config->redisCommonShortUrlHistorySet)){
                    $this->redisService->expire($this->config->redisCommonShortUrlHistorySet,time()+86400*30*12);
                }
                //写入排重库表中
                $reset2 = $this->shortHistoryService->insertOneIgnoreDuplicate(['short'=>$shortUrl,'short_task_project'=>$mutliLongUrl['short_task_project']]);
                //写入到hash有效库中并设置有效时间
                $reset3 = $this->redisService->hset($this->config->redisShortUrlHistoryHash.":".$shortUrl,json_encode(['short'=>$shortUrl,'longurl'=>$longUrlListTemp[$j]['A'],'short_task_project'=>$mutliLongUrl['short_task_project']]));
                if($expire_time){
                    if(0 > $this->redisService->ttl($this->config->redisShortUrlHistoryHash.":".$shortUrl)){
                        $this->redisService->expire($this->config->redisShortUrlHistoryHash.":".$shortUrl,$expire_time+mt_rand(3600,86400));
                    }
                }
                if(false == $reset || false == $reset2  || false == $reset3){
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl set history exists".PHP_EOL;
                }
                $shortUrlArr[] = $shortUrl; 
                $longUrlArr[] = trim($longUrlListTemp[$j]['A']); 
            }
        }
        if(empty($shortUrlArr)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." get shorturllist fail".PHP_EOL;
            return;
        }
        if(empty($longUrlArr)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." get longurllist fail".PHP_EOL;
            return;
        }
        //获取短域名前缀跟队列中的标示进行对接组成一个完整的短地址
        $shorturlprefix = $this->getShortUrl($singleLongUrl['short_task_project']);
        foreach($shortUrlArr as $sk=>$sv){
            $return[] = [
                'id' => $this->snowflake->nextId(),
                'short_task_project' => $mutliLongUrl['short_task_project'],
                'short_task_id' => $mutliLongUrl['short_task_id'],
                'short_url_task_id' => $mutliLongUrl['id'],
                'long_url' => $longUrlArr[$sk],
                'short_url' => $shorturlprefix."/".$sv,
                'short_url_expiretime' => $mutliLongUrl['short_url_expiretime'],
                'create_time' => $mutliLongUrl['create_time'], //生成时间按批次任务提交时间来计算
                'end_time' => $expire_time ? date("Y-m-d",$expire_time)." 23:59:59" : "2028-12-31 23:59:59"
            ];
        }
        //根据产品id获取短链接分表表名
        $this->getShardTable($mutliLongUrl['short_task_project'],"sms_short_url");        
        $shortUrlService = new ShortUrlService($this->tableName);
        try {
            //写入短地址分表中
            $shortUrlAddResult = $shortUrlService->insertManyIgnoreDuplicate($return);
            //更新任务状态
            $this->shortUrlTaskService->updateByPrimaryKey($mutliLongUrl['id'], ['status'=>1]);
        } catch (\Exception $exc) {
            echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." wrong info ".$exc->getMessage()
                . " file:" . $exc->getFile()
                . " line:" . $exc->getLine()
                . " trace:" . $exc->getTraceAsString();
        }
        echo "Date: " . date("Y-m-d H:i:s", time()) . "the multi taskid ".$mutliLongUrl['id']." child process is end".PHP_EOL;
        unset($return,$longUrlArr,$shortUrlArr,$shortUrlTaskInfo,$mutliLongUrl,$longUrlListTemp);
        return;
    }
    
    
    /*
     * 获取短域名
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2017-04-25 13:00:00
     */
    public function getShortUrl($projectId = 0){
        $short = '';
        if(empty($projectId)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "project id is null".PHP_EOL;
            return $short;
        }
        $data = $this->projectService->getByPrimaryKey($projectId);
        if(empty($data)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "project info is null".PHP_EOL;
            return $short;
        }
        if(empty($data['short_url_id'])){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "project info short_url_id is null".PHP_EOL;
            return $short;
        }
        $shortinfo = $this->shorturlListService->getByPrimaryKey($data['short_url_id']);
        if(empty($shortinfo)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl info is null".PHP_EOL;
            return $short;
        }
        if(empty($shortinfo['short_url'])){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl info short_url is null".PHP_EOL;
            return $short;
        }
        unset($data,$projectId,$short);
        return $shortinfo['short_url'];
    }
    
    
    /*
     * 消费短地址点击日志 并写入到分表中
     */
    public function consumerClickLogAction($totalProcess = 0, $pid = 0){
        //初始化成员属性
        $this->initializeAttribute($totalProcess, $pid);
        //消费点击日志队列
        while(true){
            $clicklog = $this->redisService->rpop($this->config->redisShortUrlClickLogQueue);
            if(empty($clicklog)){
                echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl clicklog queue is null".PHP_EOL;
                break;
            }
            $clicklog = json_decode($clicklog,true);
            if(empty($clicklog)){
                echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl clicklog json is null or is wrong".PHP_EOL;
                continue;
            }
            try {
                //写入点击日志总表和分表
                $this->shortUrlClickLogService->insertOneIgnoreDuplicate($clicklog);
                $this->getShardTable($clicklog['short_task_project'],"sms_short_url_clicklog");
                $shortUrlClickLogService = new ShortUrlClickLogService($this->tableName);
                $shortUrlClickLogService->insertOneIgnoreDuplicate($clicklog);
                //写入redis相关统计任务
                $this->setStatisticsRedis($clicklog);
            } catch (\Exception $exc) {
                echo "Date: " . date("Y-m-d H:i:s", time()) . " shorturl clicklog  wrong info ".$exc->getMessage()
                    . " file:" . $exc->getFile()
                    . " line:" . $exc->getLine()
                    . " trace:" . $exc->getTraceAsString();
                continue;
            }
        }
        $this->done = true;
        return;
    }
    
    
    /*
     * 写入点击日志信息到统计redis中 方便进行后续的统计任务
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2017-04-25 13:00:00
     */
    public function setStatisticsRedis($clicklog = []){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog is null".PHP_EOL;
            return false;
        }
        
        /*
         * 写入各种统计redis计数器 方便后续进行统计  默认过期时间都为1天 只统计当天的数据
         * 1、总体统计:当天总体统计 、web端总体统计、接口总体统计
         * 2、任务统计: 产品、任务、来源、日期、批次、设备
         */
        $today = date("Y-m-d");
        $time = time() + 86400 + mt_rand(3600, 7200);
        //总体统计计数器
        $this->setStatisticsRedisTotalCounterByHyperLogLog($clicklog,$today);
        //按任务统计计数器
        $this->setStatisticsRedisTaskCounterByHyperLogLog($clicklog,$today);
        //分时段按任务统计计数器
        $this->setStatisticsRedisHourTaskCounterByHyperLogLog($clicklog,$today);
        //计算最终结果
        $this->setStatisticsRedisTotalByHyperLogLog($clicklog,$today);
        //设置缓存+计数器过期时间
        $this->setStatisticsRedisExpireTimeByHyperLogLog($clicklog,$time);
        return;
    }
    
    /*
     * 总体统计计数器
     */
    public function setStatisticsRedisTotalCounter($clicklog = [], $today = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog total counter is null".PHP_EOL;
            return false;
        }
        /*---------------------------------------长连接start------------------------------------------------------------------*/
        //总体长连接
        $lr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."long",$clicklog['long_url']);
        if(false !== $lr){
            //当长连接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":long_url");
        }
        //web or 接口长连接
        $wlr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."long",$clicklog['long_url']);
        if(false !== $wlr){
            //当长连接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_long_url");
        }
        /*---------------------------------------长连接end------------------------------------------------------------------*/
        
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        //总体短链接
        $sr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."short",$clicklog['short_url']);
        if(false !== $sr){
            //当短链接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":short_url");
        }
        //web or 接口短链接
        $wsr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."short",$clicklog['short_url']);
        if(false !== $wsr){
            //当短链接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_short_url");
        }
        /*---------------------------------------短链接end------------------------------------------------------------------*/
        
        /*---------------------------------------总点击数start------------------------------------------------------------------*/
        //总体总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":clicknum");
        //web or接口总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_clicknum");
        /*---------------------------------------总点击数end------------------------------------------------------------------*/
        
        /*---------------------------------------独立uv 点击数start-----------------------------------------------------------*/
        //独立uv 点击数
        $ucr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."uniqclicknum",$clicklog['short_url']);
        if(false !== $ucr){
            //当独立uv 点击数不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":uniq_clicknum");
        }
        
        //web or 接口独立uv 点击数
        $wucr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."uniqclicknum",$clicklog['short_url']);
        if(false !== $wucr){
            //当独立uv 点击数不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_clicknum");
        }
        /*---------------------------------------独立uv 点击数end-----------------------------------------------------------*/
        
        /*---------------------------------------唯一IPstart-----------------------------------------------------------*/
        //唯一IP
        $uip = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."uniqip",$clicklog['click_ip']);
        if(false !== $uip){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip");
        }
        
        //web or 接口唯一IP
        $wuip = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."uniqip",$clicklog['click_ip']);
        if(false !== $wuip){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip");
        }
        /*---------------------------------------唯一IPend-----------------------------------------------------------*/
        
        /*---------------------------------------PC端|移动端|未知start-----------------------------------------------------------*/
        //总体
        $device = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['click_device']."device",$clicklog['short_url']);
        if(false !== $device){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device");
        }
        
        //pc端
        $udevice = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from'].$clicklog['click_device']."device",$clicklog['short_url']);
        if(false !== $udevice){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device"); 
        }
        /*---------------------------------------PC端|移动端|未知end-----------------------------------------------------------*/
        return;
    }
    
    /*
     * 按任务统计计数器
     */
    public function setStatisticsRedisTaskCounter($clicklog = [], $today = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog task counter is null".PHP_EOL;
            return false;
        }
        /*---------------------------------------长连接start------------------------------------------------------------------*/
        //总体长连接
        $lr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":task:".$today.$clicklog['short_task_id'].$clicklog['short_from'].$clicklog['short_url_task_id'].$clicklog['click_device']."long",$clicklog['long_url']);
        if(false !== $lr){
            //当长连接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":task:".$today.":long_url");
        }
        //web or 接口长连接
        $wlr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":task:".$today.$clicklog['short_from']."long",$clicklog['long_url']);
        if(false !== $wlr){
            //当长连接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":task:".$today.":".$clicklog['short_from']."_long_url");
        }
        /*---------------------------------------长连接end------------------------------------------------------------------*/
        
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        //总体短链接
        $sr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."short",$clicklog['short_url']);
        if(false !== $sr){
            //当短链接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":short_url");
        }
        //web or 接口短链接
        $wsr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."short",$clicklog['short_url']);
        if(false !== $wsr){
            //当短链接不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_short_url");
        }
        /*---------------------------------------短链接end------------------------------------------------------------------*/
        
        /*---------------------------------------总点击数start------------------------------------------------------------------*/
        //总体总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":clicknum");
        //web or接口总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_clicknum");
        /*---------------------------------------总点击数end------------------------------------------------------------------*/
        
        /*---------------------------------------独立uv 点击数start-----------------------------------------------------------*/
        //独立uv 点击数
        $ucr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."uniqclicknum",$clicklog['short_url']);
        if(false !== $ucr){
            //当独立uv 点击数不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":uniq_clicknum");
        }
        
        //web or 接口独立uv 点击数
        $wucr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."uniqclicknum",$clicklog['short_url']);
        if(false !== $wucr){
            //当独立uv 点击数不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_clicknum");
        }
        /*---------------------------------------独立uv 点击数end-----------------------------------------------------------*/
        
        /*---------------------------------------唯一IPstart-----------------------------------------------------------*/
        //唯一IP
        $uip = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today."uniqip",$clicklog['click_ip']);
        if(false !== $uip){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip");
        }
        
        //web or 接口唯一IP
        $wuip = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."uniqip",$clicklog['click_ip']);
        if(false !== $wuip){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip");
        }
        /*---------------------------------------唯一IPend-----------------------------------------------------------*/
        
        /*---------------------------------------PC端|移动端|未知start-----------------------------------------------------------*/
        //总体
        $device = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['click_device']."device",$clicklog['short_url']);
        if(false !== $device){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device");
        }
        
        //pc端
        $udevice = $this->redisService->sadd($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from'].$clicklog['click_device']."device",$clicklog['short_url']);
        if(false !== $udevice){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device"); 
        }
        /*---------------------------------------PC端|移动端|未知end-----------------------------------------------------------*/
        return;
    }
    
    
    /*
     * 分时段按任务统计计数器
     */
    public function setStatisticsRedisTaskHourCounter($clicklog = [], $today = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog task hour counter is null".PHP_EOL;
            return false;
        }
        /*---------------------------------------总点击数start------------------------------------------------------------------*/
        //总体总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":taskhour:".$today.date("H").$clicklog['short_task_id'].$clicklog['short_from'].$clicklog['short_url_task_id'].$clicklog['click_device'].":clicknum");
        /*---------------------------------------总点击数end------------------------------------------------------------------*/
        /*---------------------------------------独立uv 点击数start-----------------------------------------------------------*/
        //独立uv 点击数
        $ucr = $this->redisService->sadd($this->config->redisShortUrlStatistics.":taskhour:".$today.date("H").$clicklog['short_task_id'].$clicklog['short_from'].$clicklog['short_url_task_id'].$clicklog['click_device']."uniqclicknum",$clicklog['short_url']);
        if(false !== $ucr){
            //当独立uv 点击数不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id'].":".$clicklog['short_from'].":".$clicklog['short_url_task_id'].":".$clicklog['click_device'].":uniq_clicknum");
        }
        /*---------------------------------------独立uv 点击数end-----------------------------------------------------------*/
        /*---------------------------------------唯一IPstart-----------------------------------------------------------*/
        //唯一IP
        $uip = $this->redisService->sadd($this->config->redisShortUrlStatistics.":taskhour:".$today.date("H").$clicklog['short_task_id'].$clicklog['short_from'].$clicklog['short_url_task_id'].$clicklog['click_device']."uniqip",$clicklog['click_ip']);
        if(false !== $uip){
            //当唯一IP不存在set集合中时 计数加1
            $this->redisService->incr($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id'].":".$clicklog['short_from'].":".$clicklog['short_url_task_id'].":".$clicklog['click_device'].":uniq_ip");
        }
        /*---------------------------------------唯一IPend-----------------------------------------------------------*/
        return;
    }
    
    
    /*
     * 计算最终结果集
     */
    public function setStatisticsRedisTotal($clicklog = [],$today = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog expire time is null".PHP_EOL;
            return false;
        }
        //总体
        $total_today_json = json_encode([
            'total' => [
                'long_url_nums'=> $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":long_url"),
                'short_url_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":short_url"),
                'short_url_total_click_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":clicknum"),
                'short_url_total_click_uv'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":uniq_clicknum"),
                'short_url_unique_ip_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip"),
                'from_pc'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_device") : 0,
                'from_mobile'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_device") : 0,
                'from_unknow'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":2_device") : 0,
                'time'=>$today,
            ],
            'webtotal' => [
                'long_url_nums'=> $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_long_url") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_long_url") : 0,
                'short_url_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_short_url") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_short_url") : 0,
                'short_url_total_click_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_clicknum") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_clicknum") : 0,
                'short_url_total_click_uv'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_clicknum") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_clicknum") : 0,
                'short_url_unique_ip_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_ip") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_ip") : 0,
                'from_pc'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_0_device") : 0,
                'from_mobile'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_1_device") : 0,
                'from_unknow'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_2_device") : 0,
                'time'=>$today,
            ],
            'intertotal' => [
                'long_url_nums'=> $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_long_url") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_long_url") : 0,
                'short_url_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_short_url") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_short_url") : 0,
                'short_url_total_click_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_clicknum") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_clicknum") : 0,
                'short_url_total_click_uv'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_clicknum") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_clicknum") : 0,
                'short_url_unique_ip_nums'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_ip") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_ip") : 0,
                'from_pc'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_0_device") : 0,
                'from_mobile'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_1_device") : 0,
                'from_unknow'=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_2_device") : 0,
                'time'=>$today,
            ]
        ],JSON_UNESCAPED_UNICODE);
        
        try {
            $this->redisService->set($this->config->redisShortUrlStatistics.":".$today."_all_total",$total_today_json);
        } catch (\Exception $exc) {
            echo "Date: " . date("Y-m-d H:i:s", time()) . " shorturl clicklog statistics set error info ".$exc->getMessage()
                . " file:" . $exc->getFile()
                . " line:" . $exc->getLine()
                . " trace:" . $exc->getTraceAsString();
        }
        return;
    }
    
    
    /*
     * 设置统计任务过期时间
     */
    public function setStatisticsRedisExpireTime($clicklog = [], $time = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog expire time is null".PHP_EOL;
            return false;
        }
        //长地址过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today."long")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today."long",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":long_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":long_url");
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."long")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."long",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_long_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_long_url",$time);
        }
        
        //短地址过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today."short")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today."short",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":short_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":short_url");
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."short")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."short",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_short_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_short_url",$time);
        }
        
        //总点击数过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":clicknum")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":clicknum");
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_clicknum")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_clicknum");
        }
        
        //独立UV过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today."uniqclicknum")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today."uniqclicknum",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":uniq_clicknum")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":uniq_clicknum");
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."uniqclicknum")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."uniqclicknum",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_clicknum")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_clicknum",$time);
        }
        
        //独立IP过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today."uniqip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today."uniqip",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip");
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."wuniqcip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from']."wuniqcip",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip",$time);
        }
        
        //PC|移动|未知过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['click_device']."device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['click_device']."device",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device");
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from'].$clicklog['click_device']."device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.$clicklog['short_from'].$clicklog['click_device']."device",$time);
        }
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device");
        }
        
        //总体统计过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all_total_today")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all_total_today",$time);
        }
        return;
    }

    /*
     * 分库分表逻辑
     * 短地址 以及 短地址点击日志
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2017-04-25 13:00:00
     */
    public function getShardTable($id = 0 ,$table = ""){ 
        //初始化成员属性
        $this->initializeAttribute(); 
        if (isset($this->config->shardTable)) {    
            $dbconfig = $this->config->shardTable;   
            $id = is_numeric($id) ? (int)$id : crc32($id);    
            $database_id = ($id / $dbconfig['database_split'][0]) % $dbconfig['database_split'][1];    
            $table_id = ($id / $dbconfig['table_split'][0]) % $dbconfig['table_split'][1]; 
            if($table_id == 0){
                $table_id = $dbconfig['table_split'][0];
            }else if($table_id > $dbconfig['table_split'][1]){
                $table_id = $dbconfig['table_split'][1];
            }
            $this->tableName = $table . '_' . $table_id;    
        } else {       
            $this->tableName = $table;       
        }      
    }
    
    
    public function testAction(){
        //初始化成员属性
        $this->initializeAttribute();
        $this->redisService->setBit($this->config->redisCommonShortUrlBitMaps,1,1);
        $ret = $this->redisService->getBit($this->config->redisCommonShortUrlBitMaps,1);
        var_dump($ret);
    }
    
    
    /*
     * 查找不是数字的进行ascii计算
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     * @param str string
     * @return int
     */  
    public function findStringNums($str=''){
        //初始化成员属性
        $this->initializeAttribute();
        $str = trim($str);
        if(empty($str)){
            return '';
        }
        $result = '';
        $base = array_flip($this->base32);
        for($i = 0;$i < strlen($str);$i ++){
            if(is_numeric($str) && !empty($str)){
                $result .= (int)$str;
            }else{
                $result .= $base[$str]+1; //数组下标从0开始 要加1
            }
        }
        //对所有值拼接-10000最多5位
        return $result;
    }
}
