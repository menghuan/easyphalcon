<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author wangjianghua
 * @date 2018-3-2 20:07:27
 */
class ParameterHuoNiService
{

    public function __construct()
    {

    }

    /**
     * 创建单条发送短信的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-07 15:16
     * @param int $apiId API id   array  $data  array('手机号'=>'短信内容');
     * @return array
     */
    public function createSendOneParam($apiId, $data)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        $param['content'] = $data['content'];
        $param['phonelist'] = $data['mobile'];
        $param['taskId'] = $param['account']."_".date("YmdHis",time())."_http_".rand(10000,99999);
        return $param;
    }

    /**
     * 创建多条发送短信的接口参数  (批量发送未返回手机号码暂不接入)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-07 15:16
     * @param int $apiId API id   array  $data  array('手机号'=>'短信内容');
     * @return array
     */
    public function createSendMoreParam($apiId, $data)
    {
        return;
    }

    /**
     * 创建获取余额的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-07 15:54
     * @param int $apiId API id
     * @return array
     */
    public function createBalanceParam($apiId)
    {
        return;
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
        $parameters = (new ChannelApiParamService())->getByApiIds($apiId);
        return $parameters;
    }

}
