<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ShorturlList;

/**
 * 短域名管理
 * @author 王江华 <wangjianghua@qiaodata.com>
 * @date 2018-03-30 11:30:00
 */
class ShorturlListService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ShorturlList();
    }

    /**
     * 根据短域名查询
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getByShortUrl($name = '', $columns = [])
    {
        if (empty($name)) {
            return [];
        }
        $data = $this->model->getByShortUrl($name, $columns);
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
    

    /**
     * 获取列表数据 分页代码
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getList_Page($page = 1, $pageSize = 10, $name = '')
    {
        $data = $this->model->getList_Page($page, $pageSize, $name);
        return $data;
    }

    /**
     * 获取列表count数量，做分页处理
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-03-30 11:30
     * @return
     */
    public function getListCount($name = '')
    {
        $data = $this->model->getListCount($name);
        return $data;
    }

}
