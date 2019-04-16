<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ReplayDetail;

/**
 * 管理员 triggering
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-2-27 11:30:00
 */
class ReplayDetailService extends \Common\Services\BaseService
{
    /**
     * 营销类通道类型ID
     * @var int
     */
    const MARKETING_TYPE = 1;

    /**
     * 出发类通道类型ID
     * @var int
     */
    const TRIGGERING_TYPE = 2;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ReplayDetail();
    }

    /*
    * 获取发送数量
    * @author 苏云雷 <suyunlei@qiaodata.com>
    * @date 2017-04-27
    */
    public function getCount($where = [])
    {
        return $this->model->getCount($where);
    }

    /*
     * 获取分页数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-04-27
     */
    public function getList_Page($where = [], $page = 1, $pageSize = 10)
    {
        return $this->model->getList_Page($where, $page, $pageSize);
    }

    /**
     * 根据签名ID读取退订信息
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-03 15:12
     * @param int $signId 签名ID
     * @return obj
     */
    public function getUnsubBySignId($signId)
    {
        $signId = intval($signId);
        if (0 >= $signId) {
            return null;
        }

        return $this->model->getUnsubBySignId($signId);
    }

    /**
     * 根据手机号读取退订信息
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-04 09:52
     * @param int $mobile 签名ID  $hash 返回的键名参数，按照键名分类
     * @return obj
     */
    public function getByMobile($mobile = [],$columns = [], $hash = '')
    {
        if (empty($mobile)) {
            return null;
        }
        $data = $this->model->getByMobile($mobile,$columns);
        if (!empty($hash)) {
            $return = [];
            foreach($data as $d){
                $return[$d->$hash] = $d;
            }
            return $return;
        }
        return $data;
    }
    /**
     * 根据condition条件获取数据
     *
     */
    public function getInfoByCondition($condition = [])
    {
        return $this->model->getInfoByCondition($condition);
    }
}
