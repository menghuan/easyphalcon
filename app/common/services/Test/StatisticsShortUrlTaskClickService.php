<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\StatisticsShortUrlTaskClick;

/**
 * Description of StatisticsShortUrlTaskClick
 *
 * @author 王江华 <wangjianghua@qiaodata.com>
 * @date 2018-4-23 15:32:29
 */
class StatisticsShortUrlTaskClickService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StatisticsShortUrlTaskClick();
    }
    
    /**
     * 根据类型查找签名统计数据
     * @author 王江华 <wangjianghua@qiaodata.com>
     * @date 2017-04-25 15:39
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
     * @date 2017-04-25 15:39
     * @param int $type 渠道类型
     * @return obj
     */
    public function getByTimes($startTime = 0, $endTime = 0, $where = [], $columns = [])
    {
        $data = $this->model->getByTimes($startTime, $endTime, $where, $columns);
        return $data;
    }
}
