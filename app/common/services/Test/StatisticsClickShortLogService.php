<?php
namespace Common\Services\SmsPlatForm;
use \Common\Models\SmsPlatForm\StatisticsClickShortLog;
use \Common\Models\SmsPlatForm\StatisticsShortIp;
use \Common\Models\SmsPlatForm\StatisticsShortUrlClickLog;
use \Common\Models\SmsPlatForm\StatisticsShortDay;
use \Common\Models\SmsPlatForm\StatisticsShortTask;
use \Common\Models\SmsPlatForm\StatisticsShortTaskClickTime;
use \Common\Models\SmsPlatForm\StatisticsShortTaskHour;
/**
 * 获取短地址点击日志
 * @author [刘光远] [liuguangyuan@qiaodata.com]
 * @date 2018-04-17
 */
class StatisticsClickShortLogService extends \Common\Services\BaseService{

	private $totalLog = null;
	private $ipLog = null;
	private $shortUrlLog = null;
	private $taskLog = null;
	private $DayLog = null;
	private $taskLogByCreateTime = null;
	private $taskLogByClickTime = null;
	private $taskLogByHour = null;

	public function __construct(){
		parent::__construct();
		$this->totalLog = new StatisticsClickShortLog();//任务列表下短链接点击统计
		$this->ipLog = new StatisticsShortIp();//短链接统计 按ip统计
		$this->shortUrlLog = new StatisticsShortUrlClickLog();
		$this->DayLog = new StatisticsShortDay();//短链接点击统计 总体统计
		$this->taskLogByCreateTime = new StatisticsShortTask();//短链接点击统计 按任务下批次生成时间统计
		$this->taskLogByClickTime = new StatisticsShortTaskClickTime();//短链接点击统计 按任务下批次点击时间统计
		$this->taskLogByHour = new StatisticsShortTaskHour();//短链接点击统计 分时段统计
	}

	/**
	 * [getIpListByTime 通过时间段获取短地址日志]
	 * @author liuguangyuan 2018-04-17
	 * @param  array  $time [查询时间段]
	 * @return [type]       [description]
	 */
	public function getIpListByTime($time =[]){
		$data = $this->totalLog->getIpListByTime($time);
		return $data;
	}
	/**
	 * [checkOldIpList 获取所有已经统计的ip]
	 * @author liuguangyuan 2018-04-17
	 * @return [type] [description]
	 */
	public function checkOldIpList(){
		$data = $this->ipLog->checkOldIpList();
		return $data;
	}

	/**
	 * [updateOldIp 更新ip统计日志]
	 * @author liuguangyuan 2018-04-18
	 * @param  array  $newLogList [新的日志]
	 * @param  array  $oldLogList [现有日志]
	 * @return [type]             [description]
	 */
	public function updateOldIp($newLogList = []){
		$data = $this->ipLog->updateOldIp($newLogList);
		return $data;
	}

	/**
	 * [getNewClickLog 任务列表下点击统计]
	 * @author liuguangyuan 2018-04-18
	 * @param  array  $time [查询时间段]
	 * @return [type]       [description]
	 */
	public function getNewClickLog($time = []){
		$data = $this->totalLog->getNewClickLog($time);
		return $data;
	}	
	/**
	 * [getClickLogByTime 通过时间段获取原始点击日志]
	 * @author liuguangyuan 2018-04-23
	 * @param  [type] $time [时间段]
	 * @return [type]       [description]
	 */
	public function getClickLogByTime($time){
		$data = $this->totalLog->getClickLogByTime($time);
		return $data;
	}

	/**
	 * [getOldClickLog 检查表内是否有数据]
	 * @author liuguangyuan 2018-04-18
	 * @return [type] [description]
	 */
	public function checkOldClickLog(){
		$data = $this->shortUrlLog->checkOldClickLog();
		return $data;
	}

	/**
	 * [updateOldClickLog 更新任务列表点击统计]
	 * @author liuguangyuan 2018-04-18
	 * @param  array  $newClickLog [description]
	 * @return [type]              [description]
	 */
	public function updateOldClickLog($newClickLog = []){
		$data = $this->shortUrlLog->updateOldClickLog($newClickLog);
		return $data;
	} 

	/**
	 * [checkDataBytime 检测这一天是否有数据]
	 * @author liuguangyuan 2018-04-19
	 * @param  string $date [要检测的时间 Y-m-d]
	 * @return [type]       [description]
	 */
	public function checkDataBytime($date = ""){
		$data = $this->DayLog->checkDataBytime($date);
		return $data;
	}
	/**
	 * [updateStatisticsTotalByday 按日期更新数据]
	 * @author liuguangyuan 2018-04-19
	 * @param  array  $data [description]
	 * @return [type]       [description]
	 */
	public function updateStatisticsTotalByday($today,$data = []){
		$data = $this->DayLog->updateStatisticsTotalByday($today,$data);
		return $data;
	}


	/**
	 * [updateStatisticsTaskByCtime]
	 * 短链接统计 按任务下批次的生成时间统计 更新数据
	 * @author liuguangyuan 2018-04-20
	 * @param  array  $data [description]
	 * @return [type]       [description]
	 */
	public function updateStatisticsTaskByCtime($data = []){
		$data = $this->taskLogByCreateTime->updateStatisticsTaskByCtime($data);
		return $data;
	}

	/**
	 * [updateStatisticsTaskByCtime]
	 * 短链接统计 按任务下批次的点击时间统计 更新数据
	 * @author liuguangyuan 2018-04-20
	 * @param  array  $data [description]
	 * @return [type]       [description]
	 */
	public function updateStatisticshorturlTaskClickTime($data = []){
		$data = $this->taskLogByClickTime->updateStatisticshorturlTaskClickTime($data);
		return $data;
	}

	/**
	 * [updateStatisticsTaskByCtime]
	 * 短链接统计 短链接点击日志 分时段按任务统计
	 * @author liuguangyuan 2018-04-20
	 * @param  array  $data [description]
	 * @return [type]       [description]
	 */
	public function updateStatisticshorturlHourTask($data = []){
		$data = $this->taskLogByHour->updateStatisticshorturlHourTask($data);
		return $data;
	}
}