<?php
namespace Common\Services\SmsPlatForm;
use Common\Models\SmsPlatForm\StatisticsShortIp;
use Common\Models\SmsPlatForm\StatisticsShortDay;
use Common\Models\SmsPlatForm\StatisticsShortTask;
class StatisticsShortService  extends \Common\Services\BaseService{
    private  $shortUrl = null;
	public function __construct(){
		parent::__construct();
		$this->model = new StatisticsShortIp();
        $this->shortUrl = new StatisticsShortDay();
        $this->shortTask = new StatisticsShortTask();
	}

	/**
     * [getListPage 获取IP点击列表]
     * @author liuguangyuan 2018-04-10
     * @param  integer $page     [页码]
     * @param  integer $pageSize [条数]
     * @param  array   $where    [查询条件]
     * @return [array]            [数据对象]
     */
    public function getListPage($page = 1, $pageSize = 10, $where = [])
    {
        $data = $this->model->getListPage($page,$pageSize,$where);
        return $data;
    }

   /**
    * [getListCount 获取Ip条数]
    * @author liuguangyuan 2018-04-10
    * @param  array  $where [查询条件]
    * @return [string]        [条数]
    */
    public function getListCount($where = [])
    {
        $data = $this->model->getListCount($where);
        return $data;
    }
    /**
     * [getShortUrlDayList 短地址总体统计 时间维度]
     * @author liuguangyuan 2018-04-11
     * @param  integer $page     [页码]
     * @param  integer $pageSize [每页条数]
     * @return [array]           [数据列表]
     */
    public function getShortUrlDayList($page = 1,$pageSize =10)
    {
        
        $data = $this->shortUrl->getTotalList($page,$pageSize);
        return $data;

    }
    /**
     * [getShortUrlDayCount 短地址总体统计条数]
     * @author liuguangyuan 2018-04-11
     * @return [int] [条数]
     */
    public function getShortUrlDayCount()
    {
        $data = $this->shortUrl->getTotalCount();
        return $data;
    }
    /**
     * [getShortUrlDayDeatil 获取不同终端的短地址统计 拼接用]
     * @author liuguangyuan 2018-04-11
     * @param  array  $where [查询条件]
     * @return [array]        [数据]
     */
    public function getShortUrlDayDeatil($where=[]){
        $data = $this->shortUrl->getFromList($where);
        return $data;
    }

    /**
     * [getShortUrlTaskList description]
     * @author liuguangyuan 2018-04-11
     * @param  integer $page     [当前页数]
     * @param  integer $pageSize [每页条数]
     * @param  array   $where    [查询条件]
     * @return [array]           [列表]
     */
    public function getShortUrlTaskList($page = 1, $pageSize = 10, $where = [],$time){
        $data = $this->shortTask->getTaskList($page,$pageSize,$where,$time);
        return $data;
    }

    /**
     * [getShortUrlTaskCount description]
     * @author liuguangyuan 2018-04-11
     * @param  array  $where [查询条件]
     * @return [string]      [条数]
     */
    public function getShortUrlTaskCount($where = [],$time){
        $data  = $this->shortTask->getTaskCount($where,$time);
        return $data;
    }   
    
    
    /*
     * [getTodayData 获取当天的统计数据]
     */
    public function getTodayData($date = ""){
        $data  = $this->shortUrl->getTodayData($date);
        return $data;
    }

}