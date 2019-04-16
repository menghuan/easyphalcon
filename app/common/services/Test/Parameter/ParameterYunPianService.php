<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author wangjianghua
 * @date 2018-3-2 20:07:27
 */
class ParameterYunPianService
{

    public function __construct()
    {

    }

    /**
     * 创建单条发送短信的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-07 15:16
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
        $param['text'] = $data['content'];
        $param['mobile'] =$data['mobile'];
        return $param;
    }

    /**
     * 创建多条发送短信的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2018-03-07 15:16
     * @param int $apiId API id
     * @param array  $data
     * [
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
        $param['text'] = '';
        foreach ($data as $d) {
            $param['text'] .= urlencode(mb_convert_encoding($d['content'], 'utf-8')) . ',';
        }
        $param['text'] = trim($param['text']);
        $param['mobile'] = implode(',', array_column($data,'mobile'));
        return $param;
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
