<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\Blacklist;
use Common\Services\SmsPlatForm\ReplayDetailService;
use Phalcon\Config\Adapter\Php as ConfigPhp;

/**
 * 管理员
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-2-25 11:25:22
 */
class BlacklistService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Blacklist();
    }

    /**
     * 获取列表数据 分页代码
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-02-27 11:30
     * @return
     */
    public function getList_Page($where = [], $page = 1, $pageSize = 10)
    {
        $data = $this->model->getList_Page($where, $page, $pageSize);
        return $data;
    }

    public function getCount($where = [])
    {
        return $this->model->getCount($where);
    }
    /**
     * 根据sign_id 和 mobile查找用户信息
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-02-28 17:32
     * @param string $sign_id $mobile int
     * @return
     */
    public function isonly($signId, $mobile)
    {
        if ((int)$signId < 0 && (int)$mobile <= 0) {
            return [];
        }
        return $this->model->isonly($signId, $mobile);
    }

    /**
     * 根据签名id 和 mobiles 查询黑名单数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @data 2017-03-06 14:48
     */
    public function getBlackListBySignidMobiles($mobiles = [])
    {
        if (empty($mobiles)) {
            return [];
        }
        $data = $this->model->getBySignidMobiles($mobiles);
        $blackList = [];
        if (!empty($data)) {
            foreach ($data as $d) {
                $blackList[$d->mobile] = $d->name;
            }
        }
        return $blackList;
    }

    /**
     * 根据签名id 和 mobiles 查询黑名单数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @data 2017-03-06 14:48
     */
    public function getByMobiles($mobiles = [])
    {
        if (empty($mobiles)) {
            return [];
        }
        $data = $this->model->getBySignidMobiles($mobiles);
        return $data;
    }
    
    /**
     * 获取签名下的全部黑名单
     * 1.签名下的普通黑名单，带有效期的。
     * 2.签名下的永久黑名单。
     * 3.短信平台系统中的永久黑名单。
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-03 14:47
     * @param int $signId 签名ID
     * @return array 手机黑名单列表
     */
    public function getBySignId($signId)
    {
        $signId = intval($signId);
        if ( 0 >= $signId ) {
            return null;
        }
        
        //黑名单手机列表
        $mobileList = [];
        
        //从上行日志（用户恢复信息）中读取类型为退订类型的
        $unSub = (new ReplayDetailService())->getUnsubBySignId($signId);
        $unSubList = [];
        if ( 0 < $unSub->count() ) {
            foreach ( $unSub as $data ) {
                $unSubList[$data->mobile][] = $data;
            }
        }
        
        $config = new ConfigPhp('../app/config/config.php'); //读取配置文件，获取黑名单有效期。
        foreach ( $unSubList as $mobile => $data ) {
            if ( 1 < count($data) ) { //签名下的永久黑名单
                $mobileList[] = $mobile;
            } else { //在有效期内的黑名单
                $dayTimestamp = strtotime(date('Y-m-d 00:00:00', $data[0]->create_time));
                $expiration = $dayTimestamp + 86400 * $config->blacklist->expire;
                $todayTimestamp = strtotime(date('Y-m-d 23:59:59'));
                if ( $expiration >= $todayTimestamp ) {
                    $mobileList[] = $mobile;
                }
            }
        }
        
        //从黑名单列表中读取全局的永久黑名单
        $blacklist = $this->getByLevel(1);
        if ( $blacklist->count() ) {
            foreach ( $blacklist as $data ) {
                if ( $data->status ) {
                    $mobileList[] = $data->mobile;
                }
            }
        }
        return array_unique($mobileList);
    }
    
    /**
     * 根据黑名单等级查询黑名单列表
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-05-03 15:34
     * @param type $levle 黑名单等级，黑名单的总体状态 0短期黑名单 1巧达永久黑名单
     * @return obj
     */
    public function getByLevel($levle)
    {
        $levle = intval($levle);
        return $this->model->getByLevel($levle);
    }

    /**
     * 根据update_time获取黑名单数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-26
     * @return obj
     */
    public function getByTime($time = 0, $totalStatus = 0, $status = 1)
    {
        $time = intval($time);
        return $this->model->getByTime($time, $totalStatus, $status);
    }

    /**
     * 修改黑名单数据
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-26
     * @param array $where  修改的条件
     * @param array $data   修改的数据
     */
    public function updateByWhere($where = [], $data = [])
    {
        if (empty($data) || empty($where)) {
            return null;
        }
        return $this->model->updateByWhere($where, $data);
    }
}
