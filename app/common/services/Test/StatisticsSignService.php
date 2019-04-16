<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\StatisticsSign;

/**
 * Description of StatisticsSignService
 *
 * @author wangjianghua
 * @date 2018-4-25 15:32:29
 */
class StatisticsSignService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StatisticsSign();
    }
    
    /**
     * 根据类型查找签名统计数据
     * @author wangjianghua
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
}
