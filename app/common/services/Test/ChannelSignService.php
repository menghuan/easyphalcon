<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ChannelSign;

/**
 * 管理员
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-2-27 11:30:00
 */
class ChannelSignService extends \Common\Services\BaseService
{


    public function __construct()
    {
        parent::__construct();
        $this->model = new ChannelSign();
    }

    /**
     * 根据name查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getByStatus($status = 1, $columns = [])
    {
        $data = $this->model->getByStatus($status, $columns);
        return $data;
    }

    /**
     * 获取通道ID和签名ID对应的绑定数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getByChannelSign($channelId = 0, $signId = 0)
    {
        if (empty($channelId) || empty($signId)) {
            return array();
        }
        $data = $this->model->getByChannelSign($channelId, $signId);
        return $data;
    }

    /**
     * 获取列表数据 分页代码
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getBySignid($signId, $checkStatus = [], $columns = [], $type =1)
    {
        $signId = intval($signId);
        if (0 >= $signId) {
            return null; //model返回的是迭代对象，是一个对象，所以将此处原来返回的空数组修改为null。 董光明 2017-05-02 09：12
        }
        $data = $this->model->getBySignid($signId,$checkStatus,$columns,$type);
        return $data;
    }
}
