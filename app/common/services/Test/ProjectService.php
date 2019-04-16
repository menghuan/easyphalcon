<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\Project;

/**
 * 管理员
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2018-2-27 11:30:00
 */
class ProjectService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Project();
    }

    /**
     * 根据code查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function getByCode($code = '', $columns = [])
    {
        if (empty($code)) {
            return [];
        }
        $data = $this->model->getByCode($code, $columns);
        return $data;
    }

    /**
     * 根据name查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function getByName($name = '', $columns = [])
    {
        if (empty($name)) {
            return [];
        }
        $data = $this->model->getByName($name, $columns);
        return $data;
    }

    /**
     * 根据name查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-27 11:30
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
     * 获取列表数据 分页代码
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function getList_Page($page = 1, $pageSize = 10, $name = '')
    {
        $data = $this->model->getList_Page($page, $pageSize, $name);
        return $data;
    }

    /**
     * 获取列表count数量，做分页处理
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function getListCount($name = '')
    {
        $data = $this->model->getListCount($name);
        return $data;
    }

    /**
     * [getById 通过id找产品]
     * @author liuguangyuan 2018-04-13
     * @param  integer $projectId [产品id]
     * @param  string  $field     [查询字段]
     * @return [type]             [description]
     */
    public function getById($projectId = 0 , $field = "*"){
        if(empty($projectId)){
            return array();
        }
        $data = $this->model->getById($projectId,$field);
        return $data;
    }

}
