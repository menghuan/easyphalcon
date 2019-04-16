<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ChannelApi;

/**
 * 管理员
 * @author wangjianghua
 * @date 2018-2-25 11:25:22
 */
class ChannelApiService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ChannelApi();
    }

    /**
     * 查找通道API是否已经存在
     * channel_id 和 api_type查找
     * channel_id 和 api_type是联合唯一索引
     */
    public function exists($channelId, $apiType)
    {
        $channelId = intval($channelId);
        $apiType = intval($apiType);
        if (0 >= $channelId || 0 >= $apiType) {
            return false;
        }
        return $this->model->exists($channelId, $apiType);
    }

    /**
     * 获取列表数据 分页代码
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2018-02-27 11:30
     * @return
     */
    public function getList_Page($channelId, $page = 1, $pageSize = 10)
    {
        $data = $this->model->getList_Page($channelId, $page, $pageSize);
        //分页代码数据
        $data = $this->page($data);
        return $data;
    }

    /**
     * 根据api类型查找通道中的API
     * @author wangjianghua
     * @date 2018-03-02 18:54
     * @param int $channelId 通道ID
     * @param int $apiType 接口类型
     * @return Common\Models\SmsPlatForm\ChannelApi | false
     */
    public function getChannelApiByType($channelId = [], $apiType = 0)
    {
        $apiType = intval($apiType);
        if (empty($channelId) || 0 >= $apiType) {
            return false;
        }

        return $this->model->getChannelApiByType($channelId, $apiType);
    }
}
