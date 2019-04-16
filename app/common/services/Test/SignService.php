<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\Sign;

/**
 * 管理员
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-2-28 13:00:00
 */
class SignService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Sign();
    }

    /**
     * 根据where查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-28 13:00:00
     * @return
     */
    public function getByNameProjectId($name = '', $projectId = 0, $columns = [])
    {
        if (empty($name)) {
            return array();
        }
        return $this->model->getByNameProjectId($name, $projectId, $columns);
    }

    /**
     * 获取某个产品的签名数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09 11:00:00
     * @return
     */
    public function getByProjectId($projectId = 0, $columns = [])
    {
        if (empty($projectId)) {
            return array();
        }
        return $this->model->getByProjectId($projectId, $columns);
    }

    /**
     * 根据where查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-28 13:00:00
     * @return
     */
    public function getByStatus($status = 1, $columns = [], $name = '')
    {
        return $this->model->getByStatus($status, $columns, $name);
    }

    /**
     * 获取列表数据 分页代码
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getList_Page($page = 1, $pageSize = 10,$name = '')
    {
        $data = $this->model->getList_Page($page, $pageSize, $name);
        return $data;
    }

    /**
     * 根据signId 获取name
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-13 18:30
     * @return array()
     */
    public function getSignNames($signId = array())
    {
        if (empty($signId) || !is_array($signId)) {
            return false;
        }
        $result = $this->model->getSignNames($signId);
        $names = array();
        if (!empty($result)) {
            foreach ($result as $key => $va) {
                $names[$va->id] = $va->name;
            }
        }
        return $names;
    }

    /**
     * 获取全部签名
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-04-26 11:49
     * @param int $status 状态，0：删除；1：正常。
     * @return obj
     */
    public function getAll($status = 1)
    {
        return $this->model->getAll($status);
    }
    
    /**
     * 
     * @param type $signId
     */
    public function getSignChannel($signId)
    {
        
    }
}
