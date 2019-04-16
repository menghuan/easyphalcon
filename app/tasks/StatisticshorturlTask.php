<?php
use Phalcon\Cli\Task;
use Common\Services\RedisService;
use Common\Services\SmsPlatForm\StatisticsClickShortLogService;
use Common\Services\SmsPlatForm\StatisticsShortService;
use Common\Services\SmsPlatForm\ShortUrlTaskService;
use Common\Util\IteratorUtil;

/**
 * 短地址统计
 * 有则更新 没有则插入 
 * @author 刘光远 <liuguangyuan@qiaodata.com>
 * @date 2018-04-03 
 */
class StatisticshorturlTask extends Task		
{	
	protected $redis = null;
	protected $logTask = null;
	protected $curtime = null;
	protected $today = null;
	/**
	 * [initializeAttribute 初始化一些]
	 * @author liuguangyuan 2018-04-19
	 * @return [type] [description]
	 */
	public function initializeAttribute(){
		$this->curtime = date("Y-m-d H:i:s");
		$this->today = date("Y-m-d");
		$this->redis = new RedisService();
		$this->logTask = new StatisticsClickShortLogService();
	}
	/**
	 * [statisticsIp 点击ip排名统计任务]
	 * 暂定为每半小时运行一次，redis记录上次运行的时间
	 * 查询上次时间到当前时间的数据进行更新
	 * @author liuguangyuan 2018-04-16
	 * @return [type] [description]
	 */
	public function statisticsIp(){
		$this->initializeAttribute();
		$curtime = $this->curtime;
		$preTime = $this->redis->get($this->config->redisStstatisticsIp);//上次的时间
		$this->logTask = new StatisticsClickShortLogService();
		if(!$preTime){
			$time['stime'] = 0;
			$time['etime'] = $curtime;
		}else{
			$time['stime'] = $preTime;
			$time['etime'] = $curtime;	
		}
		$Result = 1;//初始化结果为1
		//日志信息
		$msg = "stime:".$preTime."||etime:".$curtime;
		$newLogList = $this->logTask->getIpListByTime($time)->toArray();
		//增加日志
		$this->setLog("ip_array",$this->today,$msg);
		$this->setLog("ip_array",$this->today,$newLogList);
		try{
			$Result = $this->logTask->updateOldIp($newLogList);
		}catch(Exception $e){
			$this->setLog("ip_errorMsg",$today,$e->getMessage());
			echo $e->getMessage();
		}
		$today = $this->today;
		if($Result == 1){
			$this->redis->set($this->config->redisStstatisticsIp,$curtime);//更新时间查询时间
			
			//记录日志
			$this->setLog("ip_success",$today,$msg);

			echo "Date: " . date("Y-m-d H:i:s", time())."  run  success";	
		}else{
			//记录日志
			$this->setLog("ip_fail",$today,$msg);

			echo "Date: " . date("Y-m-d H:i:s", time())."  run  fail";	
		}
		
		exit();
	}

	/**
	 * [statisticsTotal 总体统计]
	 * @author liuguangyuan 2018-04-16
	 * @return [type] [description]
	 */
	public function statisticsTotal(){
		$this->initializeAttribute();
		
		$today = $this->today;
		//开始更新今天的数据
		$t_add = 0;//插入条数
		$t_update = 0;//更新条数
		$t_data = json_decode($this->redis->get($this->config->redisShortUrlStatistics.":".$today."_all_total"),true);
		$this->setLog("total_redis_key",$today,$this->config->redisShortUrlStatistics.":".$today."_all_total");
		if(!empty($t_data)){
			if(!empty($t_data['total'])){
				try{
					list($t_add,$t_update) = $this->logTask->updateStatisticsTotalByday($today,$t_data);//按定时任务时间更新今天的数据
				}catch(Exception $e){
					$this->setLog("total_errorMsg",$today,$e->getMessage());
					echo $e->getMessage();
					exit();
				}
			}
		}
		if($t_add == -1 && $t_update == -1){
			$this->setLog("total_errorMsg",$today,date("Y-m-d H:i:s")."的时间点出现redis的key日期与数据内部日期不符合,key日期为：".$today."数据日期为:".$t_data['total']['create_time']."time为".$t_data['total']['time']);
		}
		echo "Date: " . date("Y-m-d H:i:s", time())." run success || ".$today." add ".$t_add."  records and  update  ".$t_update."   records.";
		exit();
	}
	/**
	 * [StatisticsShortUrlClick ]
	 * 短链接点击日志统计任务 针对每个产品 每个任务下面的
	 * @author liuguangyuan 2018-04-17
	 * @return [type] [description]
	 */
	public function StatisticsShortUrlClick(){
		$this->initializeAttribute();
		$curtime = $this->curtime;
		$preTime = $this->redis->get($this->config->redisStstatisticsShorturl);//上次的时间
		if(!$preTime){
			//如果没没有上次时间则查所有的数据 并记录本次运行时间
			$time['stime'] = 0;
			$time['etime'] = $curtime;
		}else{
			$time['stime'] = $preTime;
			$time['etime'] = $curtime;
		}
		//需要新统计的日志
		$newClickLog = $this->logTask->getNewClickLog($time);
		if(!empty($newClickLog)){
			//组合长地址等缺少的参数
			//获取日志
			$clickLog = $this->logTask->getClickLogByTime($time)->toArray();
			//拼接长连接等到数组中
			foreach ($newClickLog as $key => $value) {
				foreach ($clickLog as $k => $v) {
					if($value['short_task_project'] == $v['short_task_project'] && $value['short_task_id'] == $v['short_task_id'] && $value['short_url_task_id'] == $v['short_url_task_id'] && $value['short_url'] == $v['short_url'] && $value['short_from'] == $v['short_from'] && $value['short_click_last_time'] == $v['create_time']){
						$newClickLog[$key]['long_url'] = $v['long_url'];
						$newClickLog[$key]['click_ip'] = $v['click_ip'];
						$newClickLog[$key]['click_ip_location'] = $v['click_ip_location'];
						$newClickLog[$key]['click_ip_area'] = $v['click_ip_area'];
						$newClickLog[$key]['click_device'] = $v['click_device'];
						break;
					}
				}
			}
			//获取批次生成时间	
			//任务id数组
			$taskIdArr = array();
			foreach ($newClickLog as $key => $value) {
				if(empty($value['short_task_project']) || empty($value['short_task_id'])||empty($value['short_url_task_id'])||empty($value['short_url'])){
					unset($newClickLog[$key]);
				}else{
					$taskIdArr[] = $value['short_url_task_id'];
				}	
			}
			if(!empty($taskIdArr)){
				$shortUrlTask = new  ShortUrlTaskService();
				$task_create_times = $shortUrlTask->getListByIds($taskIdArr,'id,create_time')->toArray();
				//拼接任务批次生成时间到数组中
				foreach ($newClickLog as $key => $value) {
					foreach ($task_create_times as $k => $v) {
						if($value['short_url_task_id'] == $v['id']){
							$newClickLog[$key]['create_time'] = $v['create_time'];
							break;
						}
					}
				}
			}
		}
		//初始化Result
		$Result = 1;
		try{
			$Result = $this->logTask->updateOldClickLog($newClickLog);
		}catch(Exception $e){
			$this->setLog("ShortUrlClick_errorMsg",$this->today,$e->getMessage());
		}
		
		//更新时间查询时间
		//记录运行日志
		$msg = "stime:".$preTime." || etime:".$curtime;
		$today = $this->today;
		if($Result == 1){
			$this->redis->set($this->config->redisStstatisticsShorturl,$curtime);
			//记录运行日志
			
			$this->setLog("ShortUrlClick_success",$today,$msg);

			echo "Date: " . date("Y-m-d H:i:s", time())."  run success  ";	
		}else{
			$this->setLog("ShortUrlClick_fail",$today,$msg);

			echo "Date: " . date("Y-m-d H:i:s", time())."  run fail  ";	
		}		
		exit();

	}


	/**
	 * [statisticsTaskByCtime]
	 * 短链接点击日志 按任务下批次生成时间统计
	 * @author liuguangyuan 2018-04-20
	 * @return [type] [description]
	 */
	public function statisticsTaskByCtime(){
		$this->initializeAttribute();
		$today = $this->today;
		//更新今天的数据
		$t_len = $this->redis->llen($this->config->redisShortUrlStatistics.":".$today."_create_task_total");
		$res = 1;
		if($t_len > 0){
			for ($i=0; $i < $t_len; $i++) { 
				$t_data = json_decode($this->redis->rpop($this->config->redisShortUrlStatistics.":".$today."_create_task_total"),true);
				//记录日志
				$this->setLog("createTime_all",$today,$t_data);
				if(!empty($t_data['short_task_project']) || !empty($t_data['short_task_id'])||!empty($t_data['short_url_task_id'])){
					try{
						$res = $this->logTask->updateStatisticsTaskByCtime($t_data);
					}catch(Exception $e){
						$this->setLog("createTime_errorMsg",$today,$e->getMessage());
						continue;
					}
				}	
				if($res  == 0 ){
					$this->redis->rpush($this->config->redisShortUrlStatistics.":".$today."_create_task_total",json_encode($t_data));
					$this->setLog("createTime_fail",$today,$t_data);
					break;
				}else{
					$res = 1;
					$this->setLog("createTime_success",$today,$t_data);
					continue;
				}
			}
		} 
		if($res == 0){
			$msg = "fail";
		}else{
			$msg ="success";
		}
		echo "Date:".date("Y-m-d H:i:s")." run $msg";
		exit();
	}


	/**
	 * [statisticsTaskByCtime]
	 * 短链接点击日志 按任务下批次点击时间统计
	 * @author liuguangyuan 2018-04-20
	 * @return [type] [description]
	 */
	public function statisticshorturlTaskClickTime(){
		$this->initializeAttribute();
		$today = $this->today;
		//更新今天的数据
		$t_len = $this->redis->llen($this->config->redisShortUrlStatistics.":".$today."_click_task_total");
		$res = 1;
		if($t_len > 0){
			for ($i=0; $i < $t_len; $i++) { 
				$t_data = json_decode($this->redis->rpop($this->config->redisShortUrlStatistics.":".$today."_click_task_total"),true);
				//记录全部日志	
				$this->setLog("clickTime_all",$today,$t_data);

				if(!empty($t_data['short_task_project']) || !empty($t_data['short_task_id']) || !empty($t_data['short_url_task_id']) ||!empty($t_data['create_time'])){
					try{
						$res = $this->logTask->updateStatisticshorturlTaskClickTime($t_data);
					}catch(Exception $e){
						$this->setLog("clickTime_errorMsg",$today,$e->getMessage());
						continue;
					}
				}
				if($res  == 0 ){
					$this->redis->rpush($this->config->redisShortUrlStatistics.":".$today."_click_task_total",$t_data);
					$this->setLog("clickTime_fail",$today,$t_data);
					break;
				}else{
					$res = 1;
					$this->setLog("clickTime_success",$today,$t_data);
					continue;
				}
			}
		} 
		if($res == 0){
			$msg = "fail";
		}else{
			$msg ="success";
		}
		echo "Date:".date("Y-m-d H:i:s")." run $msg";
		exit();
	}

	/**
	 * [statisticsTaskByCtime]
	 * 短链接点击日志 分时段按任务统计
	 * @author liuguangyuan 2018-04-20
	 * @return [type] [description]
	 */
	public function statisticshorturlHourTask(){
		$this->initializeAttribute();
		$today = $this->today;
		//更新今天的数据
		$t_len = $this->redis->llen($this->config->redisShortUrlStatistics.":".$today."_hour_task_total");
		$res = 1;
		if($t_len > 0){
			for ($i=0; $i < $t_len; $i++) { 
				$t_data = json_decode($this->redis->rpop($this->config->redisShortUrlStatistics.":".$today."_hour_task_total"),true);
				//记录日志	
				$this->setLog("hour_all",$today,$t_data);

				if(!empty($t_data['short_task_project']) || !empty($t_data['short_task_id'])||!empty($t_data['short_url_task_id'])||!empty($t_data['hour_time'])){
					try{
						$res = $this->logTask->updateStatisticshorturlHourTask($t_data);
					}catch(Exception $e){
						$this->setLog("hour_errorMsg",$today,$e->getMessage());
						continue;
					}
				}
				if($res  == 0 ){
					$this->redis->rpush($this->config->redisShortUrlStatistics.":".$today."_hour_task_total",$t_data);
					$this->setLog("hour_fail",$today,$t_data);
					break;
				}else{
					$res = 1;
					$this->setLog("hour_success",$today,$t_data);
					continue;
				}
			}
		} 
		if($res == 0){
			$msg = "fail";
		}else{
			$msg ="success";
		}
		echo "Date:".date("Y-m-d H:i:s")." run $msg";
		exit();
	}

	/**
	 * [setLog description]
	 * @author liuguangyuan  2018-04-25
	 * @param  [type] $name [日志名字]
	 * @param  [type] $date [日期]
	 * @param  [type] $data [数据]
	 */
	public function setLog($name,$date,$data){
		$logPath = LOG_PATH . 'task/statistics/' . $date . '/';
	 	if (!file_exists($logPath)) {
            mkdir($logPath, 0775, true);
        }
        if(is_array($data)){
        	file_put_contents($logPath.$date."-".$name.".log",var_export($data,true)."\r\n",FILE_APPEND);
        }else{
        	file_put_contents($logPath.$date."-".$name.".log",$data."\r\n",FILE_APPEND);
        }
        
	}
	
}