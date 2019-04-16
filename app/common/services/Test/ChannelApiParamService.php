<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ChannelApiParam;

/**
 * 管理员
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-2-25 11:25:22
 */
class ChannelApiParamService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ChannelApiParam();
    }

    /**
     * 查询数据 全部
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-02-25 18:02
     * @param array $data
     * @return
     */
    public function selectAll($parameters = array())
    {
        return $this->model->selectAll($parameters);
    }

    /**
     * 根据sign_id 和 mobile查找用户信息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-02-28 17:32
     * @param string $sign_id $mobile int
     * @return
     */
    public function isonly($sign_id, $mobile)
    {
        if ((int)$sign_id < 0 && (int)$mobile <= 0) {
            return array();
        }
        return $this->model->isonly($sign_id, $mobile);
    }

    /**
     * 根据API ID查找接口的所有参数
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-03 10:15
     * @param int $apiId 接口ID
     * @return Common\Models\SmsPlatForm\ChannelApiParam
     */
    public function getByApiId($apiId)
    {
        $apiId = intval($apiId);
        if (0 >= $apiId) {
            return false;
        }

        return $this->model->getByApiId($apiId);
    }

    /**
     * 根据API ID查找接口的所有参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-03 10:15
     * @param int $apiId 接口ID
     * @return Common\Models\SmsPlatForm\ChannelApiParam
     */
    public function getByApiIds($apiId = [])
    {
        if (empty($apiId)) {
            return false;
        }
        return $this->model->getByApiIds($apiId);
    }
    /**
     * 根据API ID修改参数状态
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-23
     * @param int $apiId 接口ID
     */
    public function updateByApiId($apiId, $status = 1)
    {
        $apiId = intval($apiId);
        if (0 >= $apiId) {
            return false;
        }
        return $this->model->updateByApiId($apiId, $status);
    }
}
