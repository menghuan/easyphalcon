<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\SendStatusLog;

/**
 * 管理员 triggering
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-2-27 11:30:00
 */
class SendStatusLogService extends \Common\Services\BaseService
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
        $this->model = new SendStatusLog();
    }

    /**
     * 根据时间获取要处理的数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @data 2017-10-17
     * @param int $start
     * @param int $end
     * @return array
     */
    public function getCountByTime($start = 0, $end = 0)
    {
        if((empty($start) && empty($end) || ($start > $end))){
            return [];
        }
        return $this->model->getCountByTime($start, $end);
    }

    /**
     * 根据时间删除数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @data 2017-10-17
     * @param int $start
     * @param int $end
     * @param int $limit
     * @return bool
     */
    public function deleteByTime($start = 0, $end = 0, $limit = 1000)
    {
        if((empty($start) && empty($end) || ($start > $end))){
            return false;
        }
        return $this->model->deleteByTime($start, $end, $limit);
    }
}
