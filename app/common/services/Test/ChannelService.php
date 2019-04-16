<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\Channel;
use Common\Services\SmsPlatForm\ChannelSignService;
use Common\Services\SmsPlatForm\SignService;

/**
 * 管理员 triggering
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-2-27 11:30:00
 */
class ChannelService extends \Common\Services\BaseService
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
        $this->model = new Channel();
    }

    /**
     * 根据name查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getByName($name = '', $columns = [])
    {
        if (empty($name)) {
            return [];
        }
        $data = $this->model->getByName($name, $columns);
        return $data;
    }

    /**
     * 根据status查询
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getByStatus($status = 1, $columns = [], $name = '')
    {
        $data = $this->model->getByStatus($status, $columns, $name);
        return $data;
    }

    /**
     * 获取列表数据 分页代码
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getList_Page($page = 1, $pageSize = 10, $name = '')
    {
        $data = $this->model->getList_Page($page, $pageSize, $name);
        return $data;
    }

    /**
     * 根据条件查询 array( 'status' => xx,'xxx'=>xxxx)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-02 11:30
     * @return
     */
    public function getByWhere($data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }
        return $this->model->getByWhere($data);
    }

    /**
     * 根据主键列表获取有效的通道列表
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-02 10:28
     * @param array $pkList array 主键列表
     */
    public function getValidByPkList($pkList)
    {
        if ( empty($pkList) ) {
            return null;
        }
        
        return $this->model->getValidByPkList($pkList);
    }
    
    /**
     * 根据签名ID确定发送短信时使用的短信通道
     * 1.如果签名设置了默认通道并且默认通道有效，那么使用默认通道。
     * 2.没有设置默认通道从绑定的有效的通道中随机选用一个。
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-02 13:27
     * @param type $signId
     * @return type
     */
    public function getChannelIdBySignId($signId, $type =1, $column=['id', 'default_channel', 'default_trigger_channel'])
    {
        $signId = intval($signId);
        if ( 0 >= $signId ) {
            return null;
        }
        
        //随机获取签名绑定的有效通道
        $randomChannel = function ($signId,$type) {
            if ( 0 >= $signId ) {
                return 0;
            }
            
            //查找签名绑定的短信通道
            $bindChannel = (new ChannelSignService())->getBySignid($signId,[1],[],$type);
            if ( empty($bindChannel) || 0 >= $bindChannel->count() ) {
                return 0;
            }
            
            //报备成功的通道
            $channelIdList = [];
            foreach ( $bindChannel as $channel ) {
                if ($channel->check_status) {
                    $channelIdList[] = $channel->channel_id;
                }
            }
            if ( empty($channelIdList) ) { //没有报备成功的通道，签名下找不到能用的通道。
                return 0;
            }
            
            //根据通道ID列表查找使用中的通道
            $channelList = $this->getValidByPkList($channelIdList);
            if ( !empty($channelList) && 0 < $channelList->count() ) {
                $channelIdList = \Common\Util\IteratorUtil::arrayColumn($channelList, 'id');
                shuffle($channelIdList);
                return $channelIdList[0];
            } else {
                return 0;
            }            
        };
        
        //查询签名信息，获取默认通道。
        $sign = (new SignService())->getByPrimaryKey($signId, $column);
        $channelId = 0;
        if ( $sign->default_channel && $type == 1 ) { //如果有默认通道，那么查找默认通道，确认通道是否有效。
            $channelId = $sign->default_channel;
        } else if ( $sign->default_trigger_channel && $type == 2 ){
            $channelId = $sign->default_trigger_channel;
        }

        if(!empty($channelId)) {
            $channel = (new ChannelService())->getByPrimaryKey($channelId);
            //默认通道无效，随机选取一个绑定中的有效的短信通道。
            if (1 != $channel->status) {
                $channelId = $randomChannel($signId,$type);
            }
        }else{
            $channelId = $randomChannel($signId,$type);
        }
        return intval($channelId);
    }
    
     /**
     * 根据status,type查询
     * @author 李新招 <suyunlei@qiaodata.com>
     * @date 2017-06-13 14:50
     * @return
     */
    public function getByTypeStatus($status = 1,$type=[])
    {
        $data = $this->model->getByTypeStatus($status,$type);
        return $data;
    }
}
