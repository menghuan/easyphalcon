<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\StatisticsDay;

/**
 * Description of StatisticsService
 *
 * @author wangjianghua
 * @date 2018-4-20 21:08:07
 */
class StatisticsDayService extends \Common\Services\BaseService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new StatisticsDay();
    }

    /**
     * 根据通道类型获取
     * @author wangjianghua
     * @date 2018-04-25 11:26
     * @param int $type 类型
     * @param int $page 页数
     * @param int $pageSize 每页数据量
     * @param int $fields 要查询的字段
     * @return obj
     */
    public function getListByType_Page($type, $page, $pageSize, $fields = [])
    {
        $type = intval($type);
        $page = intval($page);
        $pageSize = intval($pageSize);

        if (0 >= $page || 0 >= $pageSize) {
            return null;
        }

        return $this->model->getListByType_Page($type, $page, $pageSize, $fields);
    }

    /**
     * 根据通道类型统计总数
     * @author wangjianghua
     * @date 2018-04-25 14:06
     * @param type $type 通道类型，1：营销类型；2：出发类型。
     * @return int
     */
    public function countByType($type)
    {
        $type = intval($type);

        return $this->model->countByType($type);
    }
    
    /**
     * 添加数据，当重复时更新数据。
     * @author wangjianghua
     * @date 2018-05-05 17:20
     * @param array $data 新数据
     * @return int 受影响行数
     */
    public function insertOnDuplicate($data)
    {
        if ( empty($data) || !is_array($data) ) {
            return false;
        }
        return $this->model->insertOnDuplicate($data);
    }
    
    /**
     * 根据 day_timestamp 查询数据
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-06-13 10:15
     * @param int $dayTimestamp 当天凌晨零点零分零秒的时间戳
     * @return array()
     */
    public function getByDayTimestamp($dayTimestamp,$type)
    {
        $day_timestamp = intval($dayTimestamp);
        $type = intval($type);
        if (0 >= $day_timestamp || 0 >= $type) {
            return false;
        }

        return $this->model->getByDayTimestamp($day_timestamp,$type);
    }
}
