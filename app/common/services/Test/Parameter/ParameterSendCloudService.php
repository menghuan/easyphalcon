<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterSendCloudService
{

    public function __construct()
    {}

    /**
     * 发送同一内容到一个或多个手机号
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param int $apiId API id
     * @param array  $data
     *      [
     *          'mobile' => 13641145677,
     *          'content' => '短信内容',
     *      ],
     * @return array
     */
    public function createSendOneParam($apiId, $data,$ext)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
            if($paramTmp->param_key == 'smsKey'){
                $smsKey = $paramTmp->param_value;
            }
        }
        unset($param['smsKey']);
        $param['vars'] = json_encode($data['content']);
        $param['phone'] = $data['mobile'];
        $param['templateId'] = $ext;
        $param['signature'] = $this->getEncrty($param,$smsKey);
        return $param;
    }

    /**
     * 批量发送不同短信
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-05-09
     * @param int $apiId API id
     * @param array  $data
     *  [
     *      [
     *          'mobile' => 13641145677,
     *          'content' => '数据参数json串',
     *      ]
     *  ],
     * @return array
     */
    public function createSendMoreParam($apiId, $data, $ext)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $tosArr = $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
            if($paramTmp->param_key == 'smsKey'){
                $smsKey = $paramTmp->param_value;
            }
        }
        unset($param['smsKey']);
        foreach($data as $k=>$d) {
            $tosArr[$k]['vars'] = $d['content'];
            $tosArr[$k]['phone'] = $d['mobile'];
        }
        $param['tos'] = json_encode($tosArr);
        $param['templateId'] = $ext;
        $param['signature'] = $this->getEncrty($param,$smsKey);
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

    public function getEncrty($param,$key)
    {
        $str = '';
        ksort($param);
        foreach($param as $k=>$p){
            $str .= $k.'='.$p.'&';
        }
        return md5($key.'&'.$str.$key);
    }

}