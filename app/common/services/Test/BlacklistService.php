<?php
namespace Common\Services\Test;

use Common\Models\Test\Blacklist;
use Phalcon\Config\Adapter\Php as ConfigPhp;

/**
 * 管理员
 * @author wangjianghua
 * @date 2018-2-25 11:25:22
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
     * @author wangjianghua
     * @date 2018-02-27 11:30
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
     * @author wangjianghua
     * @date 2018-02-28 17:32
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
     * @author wangjianghua
     * @data 2018-03-06 14:48
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
     * @author wangjianghua
     * @data 2018-03-06 14:48
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
     * @author wangjianghua
     * @date 2018-05-03 14:47
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
     * @author wangjianghua
     * @date 2018-05-03 15:34
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
     * @author wangjianghua
     * @date 2018-05-26
     * @return obj
     */
    public function getByTime($time = 0, $totalStatus = 0, $status = 1)
    {
        $time = intval($time);
        return $this->model->getByTime($time, $totalStatus, $status);
    }

    /**
     * 修改黑名单数据
     * @author wangjianghua
     * @date 2018-05-26
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
