<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\SendTaskDetail;

/**
 * Description of SendTaskDetailService
 *
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 13:13:18
 */
class SendTaskDetailService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new SendTaskDetail();
    }

    /**
     * 根据任务ID查找详情列表
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-02 13:40
     * @param int $taskId 任务ID
     * @return array
     */
    public function getListByTaskId($taskId)
    {
        $taskId = intval($taskId);
        return $this->model->getListByTaskId($taskId);
    }

    /**
     * 更新任务
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-04 11:19
     * @param int $taskId 任务ID
     * @param string $mobile 手机号码
     * @param array $newData 新数据
     * @return int 受我影响行数
     */
    public function updateTask($taskId, $mobile, $newData)
    {
        $taskId = intval($taskId);
        if (0 >= $taskId || empty($newData)) {
            return false;
        }

        return $this->model->updateTask($taskId, $mobile, $newData);
    }

    /**
     * 批量修改任务详情数据
     * @param $taskId
     * @param $detail
     * @return bool
     */
    public function updateTaskAll($taskId, $detail)
    {
        $taskId = intval($taskId);
        if (0 >= $taskId || empty($detail)) {
            return false;
        }

        return $this->model->updateTaskAll($taskId, $detail);
    }
    
    /**
     * 批量更新某个人物下的发送详情。
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-09 14:59
     * @param int $taskId 任务ID
     * @param array $mobileList 手机号码列表
     * @param array $newData 新数据
     * @return int | boolean 返回受影响行数，如果失败返回false。
     */
    public function multiUpdateTask($taskId, $mobileList, $newData)
    {
        $taskId = intval($taskId);
        if ( 0 >= $taskId || empty($mobileList) || empty($newData) ) {
            return false;
        }
        return $this->model->multiUpdateTask($taskId, $mobileList, $newData);
    }

    /**
     * 更新任务
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-03-04 11:19
     * @param int $taskId 任务ID
     * @param string $mobile 手机号码
     * @param array $newData 新数据
     * @return int 受我影响行数
     */
    public function updateByChannelTaskIdMobile($channelTaskId = '', $mobile = 0, $newData)
    {
        if (empty($channelTaskId) || empty($mobile) || empty($newData)) {
            return false;
        }
        return $this->model->updateByChannelTaskIdMobile($channelTaskId, $mobile, $newData);
    }

    /**
     * 根据通道taskId 和 手机号 查找详情列表
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-03-08 10:10
     * @return array
     */
    public function getByChannelTaskIdMobile($channelTaskId, $mobile, $order = '', $limit = [],$type = 0)
    {
        if (empty($channelTaskId) || empty($mobile)) {
            return array();
        }

        return $this->model->getByChannelTaskIdMobile($channelTaskId, $mobile, $order, $limit,$type);
    }

    /**
     * 根据通道Id 和 手机号 查找详情列表
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-03-08 10:10
     * @return array
     */
    public function getByChannelIdMobile($channelId, $mobile, $order = '', $limit = [])
    {
        if (empty($channelId) || empty($mobile)) {
            return array();
        }

        return $this->model->getByChannelIdMobile($channelId, $mobile, $order, $limit);
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
    public function getList_Page($condition = [], $page = 1, $pageSize = 10)
    {
        return $this->model->getList_Page($condition, $page, $pageSize);
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
