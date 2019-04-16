<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\SendTask;

/**
 * Description of SendTaskService
 *
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 11:45:18
 */
class SendTaskService extends \Common\Services\BaseService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new SendTask();
    }

    public function getInfoByCreateTime($createTime = 0,$etime = 0)
    {
        if(empty($createTime)){
            return false;
        }
        $data = $this->model->getInfoByCreateTime($createTime,$etime);
        return $data;
    }
}
