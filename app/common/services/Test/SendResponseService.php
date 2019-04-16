<?php
namespace Common\Services\SmsPlatForm;

use Common\Models\SmsPlatForm\SendResponse;

/**
 * 发送结果，响应结果。
 * @author wangjianghua
 * @date 2018-3-4 21:36:04
 */
class SendResponseService extends \Common\Services\BaseService
{
    public function __construct()
    {
        $this->model = new SendResponse();
    }
}
