<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\StatisticsChannel;

/**
 * Description of StatisticsChannelService
 *
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-4-25 20:05:22
 */
class StatisticsChannelService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StatisticsChannel();
    }

    /**
     * 根据类型查找签名统计数据
     * @author 董光明 <dongguangming@qiaodata.com>
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
     * 添加数据，当重复时更新数据。
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-05 17:20
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
