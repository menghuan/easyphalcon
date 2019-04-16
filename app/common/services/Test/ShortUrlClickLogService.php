<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ShortUrlClickLog;
use Common\Models\SmsPlatForm\StatisticsShortUrlClickLog;
/**
 * 短链接管理
 * @author 王江华 <wangjianghua@qiaodata.com>
 * @date 2018-03-30 11:30:00
 */
class ShortUrlClickLogService extends \Common\Services\BaseService
{   
    private $statistics_short_url_clicklog = null;
    public function __construct($tableName = "")
    {
        parent::__construct();
        $this->model = new ShortUrlClickLog();
        if(!empty($tableName)){
            $this->model->setSource($tableName);
        }
        $this->statistics_short_url_clicklog = new StatisticsShortUrlClickLog();
    }

    /**
     * 根据name查询
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getByStatus($status = '', $columns = [] , $hash = '')
    {
        $data = $this->model->getByStatus($status, $columns);
        if (!empty($hash)) {
            $return = [];
            foreach( $data as $d){
                $return[$d[$hash]] = $d;
            }
            return $return;
        }
        return $data;
    }
    
    /*
     * 根据id批量获取数据 
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     */
    public function getListByIds($ids = [],$fields = ''){
        $data = [];
        if(empty($ids)){
            return $data;
        }
        
        $data = $this->model->getListByIds($ids,$fields);
        return $data;
    }
    
    
    /*
     * 根据id获取数据 
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     */
    public function getListById($id = 0,$fields = ''){
        $data = [];
        if(empty($id)){
            return $data;
        }
        $data = $this->model->getListById($id,$fields);
        return $data;
    }
    

    /**
     * 获取列表数据 分页代码
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getList_Page($page = 1, $pageSize = 10, $condition = [],$download = 0)
    {
        $data = $this->model->getList_Page($page, $pageSize, $condition,$download);
        return $data;
    }

    /**
     * 获取列表count数量，做分页处理
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getListCount($condition = [])
    {
        $data = $this->model->getListCount($condition);
        return $data;
    }

    /**
     * [dowmLoadList 下载Csv日志]
     * @author liuguangyuan  2018-05-02
     * @param  array  $condition [查询条件]
     * @return []            []
     */
    public function dowmLoadList($condition = []){
        $data = $this->model->getList($condition);
        return $data;
    }

    /**
     * [getClickLogList 获取日志列表]
     * @author liuguangyuan 2018-04-13
     * @param  integer $page     [页码]
     * @param  integer $pageSize [每页条数]
     * @param  array   $where    [查询条件]
     * @param  [type]  $time     [时间范围]
     * @param  [type]  $flag     [时间查询条件]
     * @return [type]            [description]
     */
    public function getClickLogList($page = 1,$pageSize = 10, $where = [], $time, $flag)
    {
       $data = $this->statistics_short_url_clicklog->getClickLogList($page = 1, $pageSize, $where, $time, $flag);
       return $data;
    }

    /**
     * [getClickLogCount 获取日志条数]
     * @author liuguangyuan 2018-04-13
     * @param  array  $where [查询条件]
     * @param  [type] $time  [时间范围]
     * @param  [type] $flag  [时间查询条件]
     * @return [type]        [description]
     */
    public function getClickLogCount($where = [], $time, $flag)
    {
        $data = $this->statistics_short_url_clicklog->getClickLogCount($where, $time, $flag);
        return $data;
    }

    /**
     * [getAllClickLogList 获取所有日志列表]
     * @author liuguangyuan 2018-04-13
     * @param  array  $where [查询条件]
     * @param  [type] $time  [时间范围]
     * @param  [type] $flag  [时间查询条件]
     * @return [type]        [description]
     */
    public function getAllClickLogList($where = [], $time ,$flag)
    {
        $data = $this->statistics_short_url_clicklog->getAllClickLogList($where, $time, $flag);
       return $data;
    }
}
