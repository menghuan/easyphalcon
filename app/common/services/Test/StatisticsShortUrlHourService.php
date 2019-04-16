<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\StatisticsShortUrlHour;

/**
 * Description of StatisticsShortHourService
 *
 * @author 王江华 <wangjianghua@qiaodata.com>
 * @date 2018-4-23 15:32:29
 */
class StatisticsShortUrlHourService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StatisticsShortUrlHour();
    }
    
    /**
     * 根据类型查找签名统计数据
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-25 15:39
     * @param int $type 渠道类型
     * @return obj
     */
    public function getByType($type)
    {
        $type = intval($type);
        return $this->model->getByType($type);
    }

    /**
     * 根据类型查找签名统计数据
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2018-04-25 15:39
     * @param int $type 渠道类型
     * @return obj
     */
    public function getByHourTimes($startTime = 0, $endTime = 0, $where = [], $columns = [])
    {
        $data = $this->model->getByHourTimes($startTime, $endTime, $where, $columns);
        return $data;
    }
}
