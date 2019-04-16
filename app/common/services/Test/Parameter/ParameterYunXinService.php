<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterYunXinService
{

    public function __construct()
    {

    }

    /**
     * 创建单条发送短信的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:16
     * @param int $apiId API id
     * @param array  $data
     *      [
     *          'mobile' => 13641145677,
     *          'content' => '短信内容',
     *      ],
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
        $param['msg'] = $data['content'];
        $param['DesNo'] = $data['mobile'];
        return $param;
    }

    /**
     * 创建多条发送短信的接口参数  (批量发送未返回手机号码暂不接入)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:16
     * @param int $apiId API id
     * @param array  $data
     *[
     *      [
     *          'mobile' => 13641145677,
     *          'content' => '短信内容',
     *      ],
     * ]
     * @return array
     */
    public function createSendMoreParam($apiId, $data)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        $msg = array();
        foreach ($data as $key => $va) {
            $contentArr = explode('】',$va['content']);
            $contentStr = $contentArr[1].$contentArr[0].'】';
            $msg[] = $va['mobile'] . '|!|' . $contentStr;
        }
        $param['msg'] = implode('|^|', $msg);
        return $param;
    }

    /**
     * 创建获取余额的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:54
     * @param int $apiId API id
     * @return array
     */
    public function createBalanceParam($apiId)
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
     * 创建获取短信状态的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-14 09:33
     * @param int $apiId API id
     * @return array
     */
    public function createStatusParam($apiId)
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
     * 创建获取短信回复的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-14 09:33
     * @param int $apiId API id
     * @return array
     */
    public function createReplayParam($apiId)
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
     * 根据API ID 查找接口参数
     * @author 董光明 <dongguangming@qiaodata.com>
     * @date 2017-0303 10:10
     * @param int $apiId API id
     * @return Common\Models\SmsPlatForm\ChannelApi
     */
    protected function getByApiId($apiId)
    {
        $parameters = (new ChannelApiParamService())->getByApiIds($apiId);
        return $parameters;
    }

}
