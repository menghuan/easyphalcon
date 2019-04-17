<?php
namespace Common\Services\Test;

use Common\Models\Test\Admin;

/**
 * 管理员
 * @author wangjianghua
 * @date 2018-2-25 11:25:22
 */
class AdminService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 根据手机号码查找用户信息
     * @author wangjianghua
     * @date 2018-02-25 11:32
     * @param string $mobile 手机号码
     * @return
     */
    public function getByMobile($mobile)
    {
        if (empty($mobile)) {
            return false;
        }

        return Admin::getByMobile($mobile);
    }
}
