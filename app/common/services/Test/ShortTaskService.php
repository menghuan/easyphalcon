<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ShortTask;

/**
 * 任务管理
 * @author 王江华 <wangjianghua@qiaodata.com>
 * @date 2018-03-30 11:30:00
 */
class ShortTaskService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ShortTask();
    }

    /**
     * 根据任务查询
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getByShortTask($condition = "", $columns = [])
    {
        if (empty($condition)) {
            return [];
        }
        $data = $this->model->getByShortTask($condition, $columns);
        return $data;
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
    
    
    /**
     * 根据条件查询
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getByWhere($where = [], $columns = [] , $hash = '')
    {
        $data = $this->model->getByWhere($where, $columns);
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
    public function getList_Page($page = 1, $pageSize = 10, $condition = [])
    {
        $data = $this->model->getList_Page($page, $pageSize, $condition);
        return $data;
    }

    /**
     * 获取列表count数量，做分页处理
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getListCount( $condition = [])
    {
        $data = $this->model->getListCount($condition);
        return $data;
    }

}
