<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\Whitelist;

/**
 * 管理员
 * @author wangjianghua
 * @date 2018-2-25 11:25:22
 */
class WhitelistService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Whitelist();
    }

    /**
     * 获取列表数据 分页代码
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function getList_Page($where = [], $page = 1, $pageSize = 10)
    {
        $data = $this->model->getList_Page($where, $page, $pageSize);
        return $data;
    }

    /**
     * 根据sign_id 和 mobile查找用户信息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-02-28 17:32
     * @param string $sign_id $mobile int
     * @return
     */
    public function isonly($mobile)
    {
        if ((int)$mobile <= 0) {
            return array();
        }
        return $this->model->isonly($mobile);
    }

    /**
     * 根据白名单状态读取白名单数据
     * @author wangjianghua
     * @date 2018-05-03 16:40
     * @param int $status 状态0：已删除；1：正常使用。
     * @return obj
     */
    public function getByStatus($status = 1, $columns = [],$where = [])
    {
        $status = intval($status);
        return $this->model->getByStatus($status, $columns, $where);
    }
}
