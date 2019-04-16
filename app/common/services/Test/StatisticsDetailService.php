<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\StatisticsDetail;

/**
 * Description of StatisticsDetailService
 *
 * @author wangjianghua
 * @date 2018-4-26 15:36:14
 */
class StatisticsDetailService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StatisticsDetail();
    }
    
    /**
     * 搜索统计信息
     * 根据签名统计、根据通道统计、根据日期统计
     * @author wangjianghua
     * @date 2018-04-26 15:39
     * @param int $type 通道类型
     * @param int $signId 签名ID
     * @param int $channelId 通道ID
     * @param string $dateFrom 开始日期 yyyy-mm-dd
     * @param string $dateTo 结束日期 yyyy-mm-dd
     * @param int $page 页数
     * @param int $pageSize 每页数据量
     * @return obj
     */
    public function search( $type=1, $signId=0, $channelId=0, 
            $dateFrom='', $dateTo='', $page=1, $pageSize=30 )
    {
        $dateFromTimestamp = 0;
        $dateToTimestamp = 0;
        if ( $dateFrom ) {
            $dateFromTimestamp = strtotime($dateFrom);
        }
        if ( $dateTo ) {
            $dateToTimestamp = strtotime($dateTo);
        }
        return $this->model->search( $type, $signId, $channelId, $dateFromTimestamp, $dateToTimestamp, $page, $pageSize );
    }
    
    /**
     * 统计数量
     * @author wangjianghua
     * @date 2018-04-26 16:58
     * @param int $type 通道类型
     * @param int $signId 签名ID
     * @param int $channelId 通道ID
     * @param string $dateFrom 开始日期，时间戳。
     * @param string $dateTo 结束日期，时间戳。
     * @return type
     */
    public function countNumber($type=1, $signId=0, $channelId=0, $dateFrom='', $dateTo='')
    {
        $dateFromTimestamp = 0;
        $dateToTimestamp = 0;
        if ( $dateFrom ) {
            $dateFromTimestamp = strtotime($dateFrom);
        }
        if ( $dateTo ) {
            $dateToTimestamp = strtotime($dateTo);
        }
        return $this->model->countNumber($type, $signId, $channelId, $dateFromTimestamp, $dateToTimestamp);
    }
    
    /**
     * 添加数据，当重复时更新数据。
     * @author wangjianghua
     * @date 2018-05-06 08:52
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
