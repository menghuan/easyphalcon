<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author wangjianghua
 * @date 2018-3-2 20:07:27
 */
class ParameterService
{

    public function __construct()
    {

    }

    /**
     * 创建发送短信的接口参数
     * @author wangjianghua
     * @date 2018-03-03 09:24
     * @param int $apiId API id
     * @return array
     */
    public function createSendParam($apiId)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }

        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        return $param;
    }

    /**
     * 创建获取余额的接口参数
     * @author wangjianghua
     * @date 2018-03-03 09:24
     * @param int $apiId API id
     * @return array
     */
    public function createBalanceParam($apiId)
    {
        /**
         * @todo
         */
    }

    /**
     * 根据API ID 查找接口参数
     * @author wangjianghua
     * @date 2018-0303 10:10
     * @param int $apiId API id
     * @return Common\Models\SmsPlatForm\ChannelApi
     */
    protected function getByApiId($apiId)
    {
        $parameters = (new ChannelApiParamService())->getByApiId($apiId);
        return $parameters;
    }

}
