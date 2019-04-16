<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\ReplayLog;

/**
 * 管理员 triggering
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2018-2-27 11:30:00
 */
class ReplayLogService extends \Common\Services\BaseService
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
        $this->model = new ReplayLog();
    }
}
