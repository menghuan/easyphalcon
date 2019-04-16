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
class ShorturlticketTask  extends Task
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
    
    /**
     * 所生成的最大超限短连接数
     * @var int
     */
    const maxShortNums = 1000;

    /**
     * 队列剩余数
     * @var int
     */
    const lastQueueNums = 500000; 
    
    /**
     * 多进程短链接发号器 每个进程要跑的数量
     * @var int
     */
    const perForkNums = 100000; 
    
    
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
        $this->shorturlListService = new ShorturlListService(); //短域名表
        return;
    }
    
    
    /*
     * 多进程 短地址发号器
     * pipeline压入队列
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     */
    public function ticketAction(){
        //初始化成员属性
        $this->initializeAttribute();
        ini_set('memory_limit', '1024M');
        set_time_limit(0);
        //检测队列剩余量 大于10万的话不生成
        $queueNum = $this->redisService->llen($this->config->redisCommonShortUrlQueue);
        if($queueNum > self::lastQueueNums){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "the data ".$queueNum." is full".PHP_EOL;
            exit();
        }
        //通过pipeline批量生成
        $pipe = $this->redisService->multi(Redis::PIPELINE);  
        for($i = 0; $i< 100 ;$i++){
            for($j = 0; $j < self::perForkNums;$j ++) {  
                $newid = $this->patchTicketAction();
                //写入到set历史排重库中
//                $reset = $this->redisService->pfadd($this->config->redisCommonShortUrlHistorySet,[$newid]);
//                if(0 > $this->redisService->ttl($this->config->redisCommonShortUrlHistorySet)){
//                    $this->redisService->expire($this->config->redisCommonShortUrlHistorySet,time()+86400*30*12*10);
//                }
//                if(0 == $reset){ //表示已经重复直接跳过
//                    //echo "Date: " . date("Y-m-d H:i:s", time()) . "the ".$newid." is exists".PHP_EOL;
//                    continue;
//                }
                echo $newid.PHP_EOL;
                //压入队列
                $pipe->lpush($this->config->redisCommonShortUrlQueue,$newid);
            } 
            $result = $pipe->exec();
            print_r($result);
            unset($result);
            echo "Date: " . date("Y-m-d H:i:s", time()) . "the ".$i." child process is end".PHP_EOL;
        }
        echo 'success';
        exit();
    }
    
    
    /*
     * 检测队列剩余情况
     * * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     */
    public function checkQueueNumsAction(){
        //初始化成员属性
        $this->initializeAttribute();
        $nx = $this->redisService->get($this->config->redisSetNx."_check");
        if(1 == $nx){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "the check data is running".PHP_EOL;
            exit();
        }
        $this->redisService->setex($this->config->redisSetNx."_check",1,86400+mt_rand(3600,86400));
        $queueNum = $this->redisService->llen($this->config->redisCommonShortUrlQueue);
        if($queueNum <= self::lastQueueNums){
            $this->ticketAction(); // 重新补充数据
        }
        //释放锁
        $this->redisService->setex($this->config->redisSetNx."_check",0,86400+mt_rand(3600,86400));
        echo "Date: " . date("Y-m-d H:i:s", time()) . "the check data ".$queueNum." is full sucess".PHP_EOL;
        exit();
    }
    
    public function dealTestAction($totalProcess = 0, $pid = 0){
        //初始化成员属性
        $this->initializeAttribute($totalProcess, $pid);
        for($i= 0;$i<=10000000;$i++){
            $newid = $this->patchTicketAction();
            echo $newid.PHP_EOL;
        }
        echo "success";
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
        $subHexLen = $hexLen / 8;   //将长网址md5生成32位签名串，分为4段，每段8个字节；
        $output = array();   
        for ($i = 0; $i < $subHexLen; $i++) {   
          $subHex = substr ($hex, $i * 8, 8);   //对这四段循环处理，取8个字节
          $int = 0x3FFFFFFF & (1 * ('0x'.$subHex));   //将他看成16进制串与0x3fffffff(30位1)与操作，即超过30位的忽略处理
          $out = ''; 
		  //将这30位分成5段
          for ($j = 0; $j < 5; $j++) {   
            $val = 0x0000001F & $int;  //每5位的数字作为字母表的索引取得特定字符，依次进行获得5位字符串；
            $out .= $this->base32[$val];   
            $int = $int >> 5;  
          }   
          $output[] = $out;   
        } 
		//总的md5串可以获得4个5位串；取里面的任意一个就可作为这个长url的短url地址；
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
        //初始化成员属性
        $this->initializeAttribute($totalProcess, $pid);
        while(true){
            //获取未开始执行的批次任务
            try {
                $shortList = $this->redisService->brpop($this->config->redisCommonShortTaskQueue,1);
            } catch (\Exception $exc) {
                echo "shortUrlTask Redis rpop ".$exc->getMessage()
                        . ' file:' . $exc->getFile()
                        . ' line:' . $exc->getLine()
                        . ' trace:' . $exc->getTraceAsString();
            }
            if(empty($shortList)){
                echo "Date: " . date("Y-m-d H:i:s", time()) . " no task is deal".PHP_EOL;
                $this->done = true;
                break;
            }
            try {
                $shorts = $shortList[1];
                $shortList = json_decode($shortList[1],true);
            } catch (\Exception $exc) {
                $this->redisService->lpush($this->config->redisCommonShortTaskQueue,$shorts);
                unset($shorts);
                echo "shortUrlTask Redis decode fail lpush to redis ".$exc->getMessage()
                        . ' file:' . $exc->getFile()
                        . ' line:' . $exc->getLine()
                        . ' trace:' . $exc->getTraceAsString();
            }

            if(!empty($shortList['short_url_nums'])){
                if(1 == $shortList['long_url_nums']){ //多个需要解析csv文件
                    $this->multiLongUrl2multiShortUrl($shortList,$pid);
                }else if(2 == $shortList['long_url_nums']){ //单个
                    $this->singleLongUrl2multiShortUrl($shortList,$pid);
                } 
            }
            unset($shortList);
            $this->done = true;
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
    public function singleLongUrl2multiShortUrl($singleLongUrl = [],$pid = 0){
        $return = $shortUrlArr = [];
        if(empty($singleLongUrl)){
            return $return;
        }
        //检查任务是否已经被执行过
        if($singleLongUrl['status'] == 1){
            echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." is dealed ".PHP_EOL;
            return;
        }
        //设置锁
        $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",1,86400+mt_rand(3600,86400));
        $cur = $this->redisService->get($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['id']."_uniq_short_url");
        $expire_time = $singleLongUrl['short_url_expiretime'] ? strtotime($singleLongUrl['create_time']) + $singleLongUrl['short_url_expiretime']*86400 : 0;
        //循环这么多个短链接 对应到同一个长连接中
        //按分页逻辑去处理短地址量大的情况
        if($cur > 0 && $cur < $singleLongUrl['short_url_nums']){ //当大于0表示已经处理过一次了 可能是失败 
           $totalpage = ceil(($singleLongUrl['short_url_nums'] - $cur) / self::maxShortNums); 
        }else{
           $totalpage = ceil($singleLongUrl['short_url_nums'] / self::maxShortNums);
        }
        echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl cur is ".$cur." totalpage is ".$totalpage.PHP_EOL;
        for($j = 0; $j < $totalpage; $j++){  //分批次处理
            $offset = $j * (self::maxShortNums >= $singleLongUrl['short_url_nums'] ? $singleLongUrl['short_url_nums'] : self::maxShortNums);
            if($j == $totalpage -1){
                $limit = $offset + ($singleLongUrl['short_url_nums'] % self::maxShortNums == 0 ? self::maxShortNums : $singleLongUrl['short_url_nums'] % self::maxShortNums);
            }else{
                $limit = $offset + (self::maxShortNums >= $singleLongUrl['short_url_nums'] ? $singleLongUrl['short_url_nums'] : self::maxShortNums);
            }
            echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl offset is ".$offset." limit is ".$limit.PHP_EOL;
            $cur_t = $this->redisService->get($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['id']."_uniq_short_url");
            if($cur_t >= $singleLongUrl['short_url_nums']){
                echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." total is ".$cur_t." is out of range outer".PHP_EOL;
                unset($cur_t);
                break;
            }
            for($h = $offset; $h < $limit;$h ++){
                $shortUrl = "";
                $cur_tt = $this->redisService->get($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['id']."_uniq_short_url");
                if($cur_tt >= $singleLongUrl['short_url_nums']){
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." total is ".$cur_tt." is out of range outer".PHP_EOL;
                    unset($cur_tt);
                    break;
                }
                try {
                    $shortUrl = $this->redisService->brpop($this->config->redisCommonShortUrlQueue,1);
                } catch (\Exception $exc) {
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "single redisCommonShortUrlQueue is null ".$exc->getMessage()
                        . ' file:' . $exc->getFile()
                        . ' line:' . $exc->getLine()
                        . ' trace:' . $exc->getTraceAsString();
                }
                $shortUrl = $shortUrl[1];
                if(empty($shortUrl)){
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl taskid ".$singleLongUrl['id']." get shorturl fail".PHP_EOL;
                    continue;
                }
                try {
                    //写入排重库表中
                    echo $shortUrl."== pid:".$pid."== thispid:".$this->pid.PHP_EOL;
                    $reset = $this->shortHistoryService->insertOneIgnoreDuplicate(['short'=>$shortUrl,'short_task_project'=>$singleLongUrl['short_task_project']]);
                    //写入到hash有效库中 并设置过期时间
                    $reset2 = $this->redisService->hmset(
                            $this->config->redisShortUrlHistoryHash.":".$singleLongUrl['short_task_project'].":".$shortUrl,
                            [
                                'short'=>$shortUrl,
                                'longurl'=>$singleLongUrl['long_url'],
                                'short_task_project'=>$singleLongUrl['short_task_project'],
                                'short_task_id' => $singleLongUrl['short_task_id'],
                                'short_url_expiretime' => $singleLongUrl['short_url_expiretime'],
                                'short_url_task_id' => $singleLongUrl['id'],
                                'short_from' => $singleLongUrl['short_from'],
                                'task_create_time' => $singleLongUrl['create_time']
                            ]);
                    if($expire_time){
                        if(0 > $this->redisService->ttl($this->config->redisShortUrlHistoryHash.":".$singleLongUrl['short_task_project'].":".$shortUrl)){
                            $this->redisService->expire($this->config->redisShortUrlHistoryHash.":".$singleLongUrl['short_task_project'].":".$shortUrl,$expire_time+mt_rand(3600,86400));
                        }
                    }
                } catch (\Exception $exc) {
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl  taskid ".$singleLongUrl['id']." wrong info ".$exc->getMessage()
                        . " file:" . $exc->getFile()
                        . " line:" . $exc->getLine()
                        . " trace:" . $exc->getTraceAsString();
                }
                if(false == $reset || false == $reset2){ 
                    //如果有失败
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl set history exists".PHP_EOL;
                }
                //统计任务每个批次下今日已生成的短链接数
                $this->redisService->incr($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['id']."_uniq_short_url");
                if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['id']."_uniq_short_url")){
                    $this->redisService->expire($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['id']."_uniq_short_url",86400+mt_rand(3600,86400));
                }
                //统计任务下今日已生成的短链接数
                $this->redisService->incr($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['short_task_id']."_task_uniq_short_url");
                if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['short_task_id']."_task_uniq_short_url")){
                    $this->redisService->expire($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$singleLongUrl['short_task_id']."_task_uniq_short_url",86400+mt_rand(3600,86400));
                }
                //统计短链接的总数
                $this->redisService->incr($this->config->redisShortUrlStatistics.":tasktotal:".$singleLongUrl['short_task_id']."_total_short_url");
                if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":tasktotal:".$singleLongUrl['short_task_id']."_total_short_url")){
                    $this->redisService->expire($this->config->redisShortUrlStatistics.":tasktotal:".$singleLongUrl['short_task_id']."_total_short_url",86400+mt_rand(3600,86400));
                }
                unset($reset,$reset2);
                $shortUrlArr[] = $shortUrl; 
            }
            if(empty($shortUrlArr)){
                //释放锁
                $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
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
                //释放锁
                $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl  taskid ".$singleLongUrl['id']." wrong info ".$exc->getMessage()
                    . " file:" . $exc->getFile()
                    . " line:" . $exc->getLine()
                    . " trace:" . $exc->getTraceAsString();
            }
        }  
        //释放锁
        $this->redisService->setex($this->config->redisSetNx.":".$singleLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
        echo "Date: " . date("Y-m-d H:i:s", time()) . "the  taskid ".$singleLongUrl['id']." child process is end".PHP_EOL;
        unset($return,$shortUrlArr,$singleLongUrl);
        return;
    }
    
    
    
    /*
     * 多个长连接对应多个短链接的情况处理
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-11 14:09
     * @param mutliLongUrl array
     * @return string
     */
    public function multiLongUrl2multiShortUrl($mutliLongUrl = [],$pid = 0){
        $return = $shortUrlArr = $longUrlArr = [];
        if(empty($mutliLongUrl)){
            return $return;
        }
        $longUrlCnt = 0;
        //检查任务是否已经被执行过
        if($mutliLongUrl['status'] == 1){
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
        //设置锁
        $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",1,86400+mt_rand(3600,86400));
        //先获取是否有失败中断的情况
        $cur = $this->redisService->get($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['id']."_uniq_short_url");
        $expire_time = $mutliLongUrl['short_url_expiretime'] ? strtotime($mutliLongUrl['create_time']) + $mutliLongUrl['short_url_expiretime']*86400 : 0;    
        
        //循环这么多个短链接 对应到多个长连接中
        //按分页逻辑去处理短地址量大的情况
        if($cur > 0 && $cur < $longUrlCnt){ //当大于0表示已经处理过一次了 可能是失败 
           $totalpage = ceil(($longUrlCnt - $cur) / self::maxShortNums); 
        }else{
           $totalpage = ceil($longUrlCnt / self::maxShortNums);
        }
        echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl cur is ".$cur." totalpage is ".$totalpage.PHP_EOL;
        for($j = 0; $j < $totalpage; $j++){  //分批次处理
            $offset = $j * (self::maxShortNums >= $longUrlCnt ? $longUrlCnt : self::maxShortNums);
            if($j == $totalpage -1){
                $limit = $offset + ($longUrlCnt % self::maxShortNums == 0 ? self::maxShortNums : $longUrlCnt % self::maxShortNums);
            }else{
                $limit = $offset + (self::maxShortNums >= $longUrlCnt ? $longUrlCnt : self::maxShortNums);
            }
            echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl offset is ".$offset." limit is ".$limit.PHP_EOL;
            //超限后退出
            $cur_t = $this->redisService->get($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['id']."_uniq_short_url");
            if($cur_t >= $mutliLongUrl['short_url_nums']){
                //释放锁
                $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                echo "Date: " . date("Y-m-d H:i:s", time()) . " mutliLongUrl taskid ".$mutliLongUrl['id']." total is ".$cur_t." is out of range outer".PHP_EOL;
                unset($cur_t);
                break;
            }
            for($h = $offset; $h < $limit;$h ++){
                //超限后退出
                $cur_tt = $this->redisService->get($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['id']."_uniq_short_url");
                if($cur_tt >= $mutliLongUrl['short_url_nums']){
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " mutliLongUrl taskid ".$mutliLongUrl['id']." total is ".$cur_tt." is out of range inner".PHP_EOL;
                    unset($cur_tt);
                    break;
                }
                echo 'h:'.$h.PHP_EOL;
                if(!empty($longUrlListTemp[$h]['A'])){
                    $shortUrl = "";
                    try {
                        $shortUrl = $this->redisService->brpop($this->config->redisCommonShortUrlQueue,1);
                    } catch (\Exception $exc) {
                        //释放锁
                        $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                        echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl  taskid ".$mutliLongUrl['id']." redisCommonShortUrlQueue is empty ".$exc->getMessage()
                                . " file:" . $exc->getFile()
                                . " line:" . $exc->getLine()
                                . " trace:" . $exc->getTraceAsString();
                    }
                    $shortUrl = $shortUrl[1];
                    if(empty($shortUrl)){
                        //释放锁
                        $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                        echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." get shorturl fail".PHP_EOL;
                        continue;
                    }
                    try {
                        //写入排重库表中
                        $reset = $this->shortHistoryService->insertOneIgnoreDuplicate(['short'=>$shortUrl,'short_task_project'=>$mutliLongUrl['short_task_project']]);
                        //写入到hash有效库中并设置有效时间
                        $reset2 = $this->redisService->hmset(
                                $this->config->redisShortUrlHistoryHash.":".$mutliLongUrl['short_task_project'].":".$shortUrl,
                                [
                                    'short'=>$shortUrl,
                                    'longurl'=>$longUrlListTemp[$h]['A'],
                                    'short_task_id' => $mutliLongUrl['short_task_id'],
                                    'short_url_expiretime' => $mutliLongUrl['short_url_expiretime'],
                                    'short_url_task_id' => $mutliLongUrl['id'],
                                    'short_from' => $mutliLongUrl['short_from'],
                                    'task_create_time' => $mutliLongUrl['create_time'],
                                    'short_task_project'=>$mutliLongUrl['short_task_project'],
                                ]);
                        if($expire_time){
                            if(0 > $this->redisService->ttl($this->config->redisShortUrlHistoryHash.":".$mutliLongUrl['short_task_project'].":".$shortUrl)){
                                $this->redisService->expire($this->config->redisShortUrlHistoryHash.":".$mutliLongUrl['short_task_project'].":".$shortUrl,$expire_time+mt_rand(3600,86400));
                            }
                        }
                    } catch (\Exception $exc) {
                        //释放锁
                        $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                        echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl  taskid ".$singleLongUrl['id']." wrong info ".$exc->getMessage()
                            . " file:" . $exc->getFile()
                            . " line:" . $exc->getLine()
                            . " trace:" . $exc->getTraceAsString();
                    }
                    if(false == $reset || false == $reset2){
                        echo "Date: " . date("Y-m-d H:i:s", time()) . " singleLongUrl set history exists".PHP_EOL;
                    }
                    //统计任务每个批次下今日已生成的短链接数
                    $this->redisService->incr($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['id']."_uniq_short_url");
                    if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['id']."_uniq_short_url")){
                        $this->redisService->expire($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['id']."_uniq_short_url",86400+mt_rand(3600,86400));
                    }
                    //统计任务下今日已生成的短链接数
                    $this->redisService->incr($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['short_task_id']."_task_uniq_short_url");
                    if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['short_task_id']."_task_uniq_short_url")){
                        $this->redisService->expire($this->config->redisShortUrlStatistics.":tasktotal:".date("Y-m-d").":".$mutliLongUrl['short_task_id']."_task_uniq_short_url",86400+mt_rand(3600,86400));
                    }
                    //统计短链接的总数
                    $this->redisService->incr($this->config->redisShortUrlStatistics.":tasktotal:".$mutliLongUrl['short_task_id']."_total_short_url");
                    if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":tasktotal:".$mutliLongUrl['short_task_id']."_total_short_url")){
                        $this->redisService->expire($this->config->redisShortUrlStatistics.":tasktotal:".$mutliLongUrl['short_task_id']."_total_short_url",86400+mt_rand(3600,86400));
                    }
                    $shortUrlArr[] = $shortUrl; 
                    $longUrlArr[] = trim($longUrlListTemp[$h]['A']); 
                }
                if(empty($shortUrlArr)){
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." get shorturllist fail".PHP_EOL;
                    return;
                }
                if(empty($longUrlArr)){
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." get longurllist fail".PHP_EOL;
                    return;
                }
                //获取短域名前缀跟队列中的标示进行对接组成一个完整的短地址
                $shorturlprefix = $this->getShortUrl($mutliLongUrl['short_task_project']);
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
                    //释放锁
                    $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
                    echo "Date: " . date("Y-m-d H:i:s", time()) . " multiLongUrl taskid ".$mutliLongUrl['id']." wrong info ".$exc->getMessage()
                        . " file:" . $exc->getFile()
                        . " line:" . $exc->getLine()
                        . " trace:" . $exc->getTraceAsString();
                }
            }
        } 
        //释放锁
        $this->redisService->setex($this->config->redisSetNx.":".$mutliLongUrl['id']."_short_url",0,86400+mt_rand(3600,86400));
        echo "Date: " . date("Y-m-d H:i:s", time()) . "the multi taskid ".$mutliLongUrl['id']." child process is end".PHP_EOL;
        unset($return,$longUrlArr,$shortUrlArr,$mutliLongUrl,$longUrlListTemp);
        return;
    }
    
    
    /*
     * 获取短域名
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2017-04-25 13:00:00
     */
    public function getShortUrl($projectId = 0){
        //初始化成员属性
        $this->initializeAttribute();
        $short = '';
        if(empty($projectId)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "project id is null".PHP_EOL;
            return $short;
        }
        echo $projectId;
        $data = $this->projectService->getByPrimaryKey($projectId);
        $data = $data->toArray();
        if(empty($data)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "project info is null".PHP_EOL;
            return $short;
        }
        if(empty($data['short_url_id'])){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "project info short_url_id is null".PHP_EOL;
            return $short;
        }
        $shortinfo = $this->shorturlListService->getByPrimaryKey($data['short_url_id']);
        $shortinfo = $shortinfo->toArray();
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
     * 批次任务失败后重试队列
     */
    public function taskFailToRedisQueueAction(){
        //初始化成员属性
        $this->initializeAttribute();
        $list = $this->shortUrlTaskService->getByStatus(0);
        if(!empty($list)){
            //压入redis缓存中
            $list = $list->toArray();
            foreach($list as $lv){
                $data = [
                    "short_task_id" => $lv["short_task_id"],
                    "short_task_project" => $lv["short_task_project"],
                    "long_url" => $lv["long_url"],
                    "long_url_nums" => $lv["long_url_nums"],
                    "short_url_nums" => $lv["short_url_nums"],
                    "short_url_expiretime" => $lv["short_url_expiretime"],
                    "create_time" => $lv["create_time"],
                    "id" => $lv["id"],
                    "short_from" => $lv["short_from"],
                    "status" => $lv["status"]
                ];
                $nx = $this->redisService->get($this->config->redisSetNx.":".$lv['id']."_short_url");
                if(false == $nx || 0 == $nx){
                    $this->redisService->lpush($this->config->redisCommonShortTaskQueue,json_encode($data,JSON_UNESCAPED_UNICODE));
                }else {
                    echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl task is running".PHP_EOL;
                    continue;
                }
            }
        }
        echo "success";
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
            echo $clicklog.PHP_EOL;
            $clicklog = json_decode($clicklog,true);
            if(empty($clicklog)){
                echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl clicklog json is null or is wrong".PHP_EOL;
                continue;
            }
            
            if(empty($clicklog['short_task_project']) || empty($clicklog['short_task_id']) || empty($clicklog['short_url_task_id']) || empty($clicklog['long_url'])){
                echo "Date: " . date("Y-m-d H:i:s", time()) . "shorturl clicklog base data is null or is wrong".PHP_EOL;
                continue;
            }
            
            //当获取归属地失败后 重新获取一次
            if(empty($clicklog["click_ip_location"]) || empty($clicklog["click_ip_area"])){
                $ipdata = $this->getLocationByIp($clicklog["click_ip"]);
                $click_ip_location = $ipdata["isp"] ? $ipdata["isp"] : "";
                $click_ip_area = $ipdata["city"] ? $ipdata["city"] : "";
                $clicklog["click_ip_location"] = $click_ip_location;
                $clicklog["click_ip_area"] = $click_ip_area;
                unset($ipdata,$click_ip_area,$click_ip_location);
            }
            
            try {
                $task_create_time = $clicklog['task_create_time']; //批次任务生成时间
                unset($clicklog['task_create_time']);
                $clicklog['id'] = $this->snowflake->nextId();
                //写入点击日志总表和分表
                $shortUrlClickLogServiceT= new ShortUrlClickLogService("sms_short_url_clicklog_total");
                $shortUrlClickLogServiceT->insertOneIgnoreDuplicate($clicklog);
                $this->getShardTable($clicklog['short_task_project'],"sms_short_url_clicklog");
                $shortUrlClickLogService = new ShortUrlClickLogService($this->tableName);
                $shortUrlClickLogService->insertOneIgnoreDuplicate($clicklog);
                //写入redis相关统计任务
                $this->setStatisticsRedis($clicklog,$task_create_time);
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
    public function setStatisticsRedis($clicklog = [],$task_create_time = 0){
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
        $time = 86400 + mt_rand(3600, 7200);
        //总体统计计数器
        $this->setStatisticsRedisTotalCounterByHyperLogLog($clicklog,$today);
        //按任务统计计数器
        $this->setStatisticsRedisTaskCounterByHyperLogLog($clicklog,$today,$task_create_time);
        //分时段按任务统计计数器
        $this->setStatisticsRedisHourTaskCounterByHyperLogLog($clicklog,$today);
        //计算最终结果
        $this->setStatisticsRedisTotalByHyperLogLog($clicklog,$today,$task_create_time);
        //设置缓存+计数器过期时间
        $this->setStatisticsRedisExpireTimeByHyperLogLog($clicklog,$today,$time);
        return;
    }
    
    /*
     * 总体统计计数器
     * 利用redis的HyperLogLog 惊人高效的统计功能
     */
    public function setStatisticsRedisTotalCounterByHyperLogLog($clicklog = [], $today = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog total counter is null".PHP_EOL;
            return false;
        }
        /*---------------------------------------长连接start------------------------------------------------------------------*/
        //总体长连接
        $pfaddo1 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_long_url",[$clicklog['long_url']]);
        if(0 == $pfaddo1){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":uniq_long_url".$clicklog['long_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_long_url",[$clicklog['long_url']]);
        }
        //web or 接口长连接
        $pfaddo2 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_long_url",[$clicklog['long_url']]);
        if(0 == $pfaddo2){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":".$clicklog['short_from']."_uniq_long_url".$clicklog['long_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_long_url",[$clicklog['long_url']]);
        }
        /*---------------------------------------长连接end------------------------------------------------------------------*/
        
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        //总体短链接
        $pfaddo3 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_short_url",[$clicklog['short_url']]);
        if(0 == $pfaddo3){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":uniq_short_url".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_short_url",[$clicklog['short_url']]);
        }
        //web or 接口短链接
        $pfaddo4 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_short_url",[$clicklog['short_url']]);
        if(0 == $pfaddo4){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":".$clicklog['short_from']."_uniq_short_url".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_short_url",[$clicklog['short_url']]);
        }
        /*---------------------------------------短链接end------------------------------------------------------------------*/
        
        /*---------------------------------------总点击数start------------------------------------------------------------------*/
        //总体总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":click_num");
        //web or接口总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_click_num");
        /*---------------------------------------总点击数end------------------------------------------------------------------*/
        
        /*---------------------------------------独立uv 点击数start-----------------------------------------------------------*/
        //独立uv 点击数
        $pfaddo5 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_click_num",[$clicklog['short_url']]);
        if(0 == $pfaddo5){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":uniq_click_num".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_click_num",[$clicklog['short_url']]);
        }
        //web or 接口独立uv 点击数
        $pfaddo6 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_click_num",[$clicklog['short_url']]);
        if(0 == $pfaddo6){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":".$clicklog['short_from']."_uniq_click_num".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_click_num",[$clicklog['short_url']]);
        }
        /*---------------------------------------独立uv 点击数end-----------------------------------------------------------*/
        
        /*---------------------------------------唯一IPstart-----------------------------------------------------------*/
        //唯一IP
        $pfaddo7 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip",[$clicklog['click_ip']]);
        if(0 == $pfaddo7){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":uniq_ip".$clicklog['click_ip']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip",[$clicklog['click_ip']]);
        }
        //web or 接口唯一IP
        $pfaddo8 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip",[$clicklog['click_ip']]);
        if(0 == $pfaddo8){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":all:".$today.":".$clicklog['short_from']."_uniq_ip".$clicklog['click_ip']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip",[$clicklog['click_ip']]);
        }
        /*---------------------------------------唯一IPend-----------------------------------------------------------*/
        
        /*---------------------------------------PC端|移动端|未知start-----------------------------------------------------------*/
        //总体
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device"); 
        //pc端
        $this->redisService->incr($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device");
        /*---------------------------------------PC端|移动端|未知end-----------------------------------------------------------*/
        return;
    }
    
    /*
     * 按任务统计计数器
     * 利用redis的HyperLogLog 惊人高效的统计功能
     */
    public function setStatisticsRedisTaskCounterByHyperLogLog($clicklog = [], $today = 0, $task_create_time = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog task counter is null".PHP_EOL;
            return false;
        }
        
        if(empty($task_create_time)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog short_url_task_id create_time is null".PHP_EOL;
            return false;
        }
        
        /**---------------------------------------按批次任务生成时间进行统计start----------------------------------------------**/
        /*---------------------------------------长连接start------------------------------------------------------------------*/
        $pfaddt1 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_long_url",[$clicklog['long_url']]);
        if(0 == $pfaddt1){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_long_url".$clicklog['long_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_long_url",[$clicklog['long_url']]);
        }
        /*---------------------------------------长连接end--------------------------------------------------------------------*/
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        $pfaddt2 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_short_url",[$clicklog['short_url']]);
        if(0 == $pfaddt2){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_short_url".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_short_url",[$clicklog['short_url']]);
        }
        /*---------------------------------------短链接end--------------------------------------------------------------------*/
        /*---------------------------------------点击次数start----------------------------------------------------------------*/
        $this->redisService->incr($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_click_num");
        /*---------------------------------------点击次数end------------------------------------------------------------------*/
        /*---------------------------------------独立UVstart------------------------------------------------------------------*/
        $pfaddt4 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_click_num",[$clicklog['short_url']]);
        if(0 == $pfaddt4){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_click_num".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_click_num",[$clicklog['short_url']]);
        }
        /*---------------------------------------独立UVend--------------------------------------------------------------------*/
        /*---------------------------------------唯一IPstart------------------------------------------------------------------*/
        $pfaddt5 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_ip",[$clicklog['click_ip']]);
        if(0 == $pfaddt5){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_ip".$clicklog['click_ip']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_ip",[$clicklog['click_ip']]);
        }
        /*---------------------------------------唯一IPend--------------------------------------------------------------------*/
        /*---------------------------------------终端占比start------------------------------------------------------------------*/
        $this->redisService->incr($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_device");
        
        /*---------------------------------------终端占比end--------------------------------------------------------------------*/
        /**---------------------------------------按批次任务生成时间进行统计end------------------------------------------------**/
        
        
        /**---------------------------------------按短地址点击时间进行统计start------------------------------------------------**/
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        $pfaddt7 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_short_url",[$clicklog['short_url']]);
        if(0 == $pfaddt7){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_short_url".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_short_url",[$clicklog['short_url']]);
        }
        /*---------------------------------------短链接end--------------------------------------------------------------------*/
        /*---------------------------------------点击次数start----------------------------------------------------------------*/
        $this->redisService->incr($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num");
        /*---------------------------------------点击次数end------------------------------------------------------------------*/
        /*---------------------------------------独立UVstart------------------------------------------------------------------*/
        $pfaddt9 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num",[$clicklog['short_url']]);
        if(0 == $pfaddt9){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num",[$clicklog['short_url']]);
        }
        /*---------------------------------------独立UVend--------------------------------------------------------------------*/
        /*---------------------------------------唯一IPstart------------------------------------------------------------------*/
        $pfaddt10 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip",[$clicklog['click_ip']]);
        if(0 == $pfaddt10){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskclick:".$today."_".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip".$clicklog['click_ip']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip",[$clicklog['click_ip']]);
        }
        /*---------------------------------------唯一IPend--------------------------------------------------------------------*/
        /**---------------------------------------按短地址点击时间进行统计end--------------------------------------------------**/
        
        return;
    }
    
    
    /*
     * 分时段按任务统计计数器
     * 利用redis的HyperLogLog 惊人高效的统计功能
     */             
    public function setStatisticsRedisHourTaskCounterByHyperLogLog($clicklog = [], $today = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog task hour counter is null".PHP_EOL;
            return false;
        }
        /*---------------------------------------总点击数start------------------------------------------------------------------*/
        //总体总点击数
        $this->redisService->incr($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num");
        /*---------------------------------------总点击数end------------------------------------------------------------------*/
        /*---------------------------------------独立uv 点击数start-----------------------------------------------------------*/
        //独立uv 点击数
        $pfaddh1 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num",[$clicklog['short_url']]);
        if(0 == $pfaddh1){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskhour:".$today.":".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num".$clicklog['short_url']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num",[$clicklog['short_url']]);
        }
        /*---------------------------------------独立uv 点击数end-----------------------------------------------------------*/
        /*---------------------------------------唯一IPstart-----------------------------------------------------------*/
        //唯一IP
        $pfaddh2 = $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskhour:".$today."_".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip",[$clicklog['click_ip']]);
        if(0 == $pfaddh2){
            echo "Date: " . date("Y-m-d H:i:s", time()) . ":taskhour:".$today."_".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip".$clicklog['click_ip']." lost next agin".PHP_EOL;
            $this->redisService->pfadd($this->config->redisShortUrlStatistics.":taskhour:".$today."_".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip",[$clicklog['click_ip']]);
        }
        /*---------------------------------------唯一IPend-----------------------------------------------------------*/
        return;
    }
    
    
    /*
     * 计算最终结果集
     */
    public function setStatisticsRedisTotalByHyperLogLog($clicklog = [],$today = 0, $task_create_time = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog expire time is null".PHP_EOL;
            return false;
        }
        $hour = date("H");
        //总体
        $total_today_json = json_encode([
            "total" => [
                "long_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":uniq_long_url"]),
                "short_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":uniq_short_url"]),
                "short_url_total_click_nums"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":click_num") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":click_num") : 0,
                "short_url_total_click_uv"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":uniq_click_num"]),
                "short_url_unique_ip_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip"]),
                "from_pc"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_device") : 0,
                "from_mobile"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_device") : 0,
                "from_unknow"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":2_device") : 0,
                "time" => $today,
                "create_time" => $clicklog['create_time']
            ],
            "webtotal" => [
                "long_url_nums"=> $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_long_url"]),
                "short_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_short_url"]),
                "short_url_total_click_nums"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_click_num") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_click_num") : 0,
                "short_url_total_click_uv"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_click_num"]),
                "short_url_unique_ip_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":0_uniq_ip"]),
                "from_pc"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_0_device") : 0,
                "from_mobile"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_1_device") : 0,
                "from_unknow"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":0_2_device") : 0,
                "time" => $today,
                "create_time" => $clicklog['create_time']
            ],
            "intertotal" => [
                "long_url_nums"=> $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_long_url"]),
                "short_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_short_url"]),
                "short_url_total_click_nums"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_click_num") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_click_num") : 0,
                "short_url_total_click_uv"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_clicknum"]),
                "short_url_unique_ip_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":all:".$today.":1_uniq_ip"]) ,
                "from_pc"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_0_device") : 0,
                "from_mobile"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_1_device") : 0,
                "from_unknow"=>$this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":all:".$today.":1_2_device") : 0,
                "time" => $today,
                "create_time" => $clicklog['create_time']
            ]
        ],JSON_UNESCAPED_UNICODE);
        
        
        //分时段按任务
        $hour_task_today_json = json_encode([
            "short_task_project" => $clicklog['short_task_project'],
            "short_task_id" => $clicklog['short_task_id'],
            "short_url_task_id" => $clicklog['short_url_task_id'],
            "short_from" => $clicklog['short_from'],
            "click_device" => $clicklog['click_device'],
            "short_url_total_click_nums" => $this->redisService->get($this->config->redisShortUrlStatistics.":taskhour:".$today.":".$hour.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num") ?
            $this->redisService->get($this->config->redisShortUrlStatistics.":taskhour:".$today.":".$hour.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num") : 0,
            "short_url_total_click_uv" => $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskhour:".$today.":".$hour.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num"]),
            "short_url_unique_ip_nums" => $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskhour:".$today."_".$hour.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip"]),
            "hour_time" => date('Y-m-d H:00:00', strtotime($clicklog['create_time'])),
            "create_time" => $clicklog['create_time']
        ]);
        
        //按点击时间分任务统计
        $click_task_today_json = json_encode([
            "short_task_project" => $clicklog['short_task_project'],
            "short_task_id" => $clicklog['short_task_id'],
            "short_url_task_id" => $clicklog['short_url_task_id'],
            "short_from" => $clicklog['short_from'],
            "click_device" => $clicklog['click_device'],
            "short_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_short_url"]),
            "short_url_total_click_nums" => $this->redisService->get($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num") ?
            $this->redisService->get($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num") : 0,
            "short_url_total_click_uv" => $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num"]),
            "short_url_unique_ip_nums" => $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip"]),
            "create_time" => $clicklog['create_time'] //点击时间
        ]);
        
        
        //按批次生成时间分任务统计
        $create_task_today_json = json_encode([
            "short_task_project" => $clicklog['short_task_project'],
            "short_task_id" => $clicklog['short_task_id'],
            "short_url_task_id" => $clicklog['short_url_task_id'],
            "short_from" => $clicklog['short_from'],
            "long_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_long_url"]),
            "short_url_nums"=>$this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_short_url"]),
            "short_url_total_click_nums" => $this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_click_num") ?
            $this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_click_num") : 0,
            "short_url_total_click_uv" => $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_click_num"]),
            "short_url_unique_ip_nums" => $this->redisService->pfcount([$this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_ip"]),
            "from_pc"=>$this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_0_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_0_device") : 0,
            "from_mobile"=>$this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_1_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_1_device") : 0,
            "from_unknow"=>$this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_2_device") ? $this->redisService->get($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_2_device") : 0,
            "create_time" => $task_create_time //批次任务生成时间
        ]);
        
        try {
            //总体统计
            $this->redisService->set($this->config->redisShortUrlStatistics.":".$today."_all_total",$total_today_json);
            //分时段按任务统计
            $this->redisService->lpush($this->config->redisShortUrlStatistics.":".$today."_hour_task_total",$hour_task_today_json);
            //按任务点击时间统计
            $this->redisService->lpush($this->config->redisShortUrlStatistics.":".$today."_click_task_total",$click_task_today_json);
            //按任务生成时间统计
            $this->redisService->lpush($this->config->redisShortUrlStatistics.":".$today."_create_task_total",$create_task_today_json);
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
    public function setStatisticsRedisExpireTimeByHyperLogLog($clicklog = [], $today = 0, $time = 0){
        if(empty($clicklog)){
            echo "Date: " . date("Y-m-d H:i:s", time()) . "to redis statistics clicklog expire time is null".PHP_EOL;
            return false;
        }
        $hour = date("H");
        //总点击量过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":click_num",$time);
        }

        //web or接口总点击数过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_click_num",$time);
        }

        //按任务生成时间统计过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_click_num",$time);
        }

        //按任务点击时间统计过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num",$time);
        }
        
        //分时段总点击量过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskhour:".$today.":".$hour.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskhour:".$today.":".$hour.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_click_num",$time);
        }

        //总体统计过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":".$today."_all_total")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":".$today."_all_total",$time);
        }

        //分时段按任务统计过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":".$today."_hour_task_total")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":".$today."_hour_task_total",$time);
        }

        //按任务点击时间过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":".$today."_click_task_total")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":".$today."_click_task_total",$time);
        }

        //按任务生成时间过期时间
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":".$today."_create_task_total")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":".$today."_create_task_total",$time);
        }
        
        
        /**---------------------------------------总体统计start----------------------------------------------**/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":uniq_long_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":uniq_long_url",$time);
        }
        
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_long_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_long_url",$time);
        }
        
        //总体短链接
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":uniq_short_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":uniq_short_url",$time);
        }

        //web or 接口短链接
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_short_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_short_url",$time);
        }
        
        //独立uv 点击数
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":uniq_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":uniq_click_num",$time);
        }
        //web or 接口独立uv 点击数
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_click_num",$time);
        }
        
        //唯一IP
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":uniq_ip",$time);
        }
        //web or 接口唯一IP
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_uniq_ip",$time);
        }
       
        //总体
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['click_device']."_device",$time); 
        }
        //pc端
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":all:".$today.":".$clicklog['short_from']."_".$clicklog['click_device']."_device",$time);
        }
        /**---------------------------------------总体统计end----------------------------------------------**/



        /**---------------------------------------按批次任务生成时间进行统计start----------------------------------------------**/
        /*---------------------------------------长连接start------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_long_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_long_url",$time);
        }
        /*---------------------------------------长连接end--------------------------------------------------------------------*/
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_short_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_short_url",$time);
        }
        /*---------------------------------------短链接end--------------------------------------------------------------------*/
 
        /*---------------------------------------独立UVstart------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_click_num",$time);
        }
        /*---------------------------------------独立UVend--------------------------------------------------------------------*/
        /*---------------------------------------唯一IPstart------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_uniq_ip",$time);
        }
        /*---------------------------------------唯一IPend--------------------------------------------------------------------*/
        /*---------------------------------------终端占比start------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_device")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskcreate:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_device",$time);
        }
        /*---------------------------------------终端占比end--------------------------------------------------------------------*/
        /**---------------------------------------按批次任务生成时间进行统计end------------------------------------------------**/
        
        
        /**---------------------------------------按短地址点击时间进行统计start------------------------------------------------**/
        /*---------------------------------------短链接start------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_short_url")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_short_url",$time);
        }
        /*---------------------------------------短链接end--------------------------------------------------------------------*/

        /*---------------------------------------独立UVstart------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num",$time);
        }
        /*---------------------------------------独立UVend--------------------------------------------------------------------*/
        /*---------------------------------------唯一IPstart------------------------------------------------------------------*/
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskclick:".$today.":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip",$time);
        }
        /*---------------------------------------唯一IPend--------------------------------------------------------------------*/
        /**---------------------------------------按短地址点击时间进行统计end--------------------------------------------------**/


		
        /*---------------------------------------独立uv 点击数start-----------------------------------------------------------*/
        //独立uv 点击数
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskhour:".$today.":".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_click_num",$time);
        }
        /*---------------------------------------独立uv 点击数end-----------------------------------------------------------*/
        /*---------------------------------------唯一IPstart-----------------------------------------------------------*/
        //唯一IP
        if(0 > $this->redisService->ttl($this->config->redisShortUrlStatistics.":taskhour:".$today."_".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip")){
            $this->redisService->expire($this->config->redisShortUrlStatistics.":taskhour:".$today."_".date("H").":".$clicklog['short_task_id']."_".$clicklog['short_from']."_".$clicklog['short_url_task_id']."_".$clicklog['click_device']."_uniq_ip",$time);
        }
        /*---------------------------------------唯一IPend-----------------------------------------------------------*/
  
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
        $json = '[{
		"short_task_project": "4",
		"short_task_id": "20",
		"id": "57",
                "long_url_nums" : "2",
                "long_url" : "https://www.liaoxuefeng.com/",
		"short_url_nums": "3000",
		"short_url_expiretime": "161",
		"short_from": "0",
                "status" : 0,
                "create_time" : "2018-05-04 14:28:40"
	}]';
        
        $jsonD = json_decode($json);
        foreach($jsonD as $jsv){
            $this->redisService->lpush($this->config->redisCommonShortTaskQueue,json_encode($jsv,JSON_UNESCAPED_UNICODE));
        }
        echo 'sucess';die;
        
        $clicklog = [
            "short_task_project" => 3,
            "short_task_id" => 1,
            "short_url_task_id" => 1,
            "short_from" => 0,
            "click_device" => 1,
            'create_time' => "2018-04-20 10:34:00", //短地址点击时间
            'task_create_time' => "2018-04-09 10:34:00", //批次任务生成时间 方便后面按任务统计使用
          ];
        //写入redis相关统计任务
        $this->setStatisticsRedisTotalByHyperLogLog($clicklog,"2018-04-20",$clicklog['task_create_time']);
        $clicklog = [
            "short_task_project" => 3,
            "short_task_id" => 1,
            "short_url_task_id" => 1,
            "short_from" => 1,
            "click_device" => 1,
            'create_time' => "2018-04-20 10:34:00", //短地址点击时间
            'task_create_time' => "2018-04-09 10:34:00", //批次任务生成时间 方便后面按任务统计使用
          ];
        //写入redis相关统计任务
        $this->setStatisticsRedisTotalByHyperLogLog($clicklog,"2018-04-20",$clicklog['task_create_time']);
        echo "sucess";
        die;
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
    
    
    
    /**
    * 获取 IP  地理位置
    * 淘宝IP接口
    * @Return: array
    */
    public function getLocationByIp($ip = '')
    {
       if ($ip == '') {
           $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json";
           $ip = json_decode(file_get_contents($url), true);
           $data = $ip;
       } else {
           $url = "http://ip.taobao.com/service/getIpInfo.php?ip=" . $ip;
           $ip = json_decode(file_get_contents($url));
           if ((string)$ip->code == '1') {
               return false;
           }
           $data = (array)$ip->data;
       }
       return $data;
    }
}
