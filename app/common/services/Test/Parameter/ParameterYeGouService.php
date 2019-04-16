<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterYeGouService
{

    public function __construct()
    {

    }

    /**
     * 创建单条发送短信的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:16
     * @param int $apiId API id   array  $data  array('手机号'=>'短信内容');
     * @return array
     */
    public function createSendOneParam($apiId, $data)
    {
        return array();
    }

    /**
     * 创建多条发送短信的接口参数  (批量发送未返回手机号码暂不接入)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:16
     * @param int $apiId API id   array  $data  array('手机号'=>'短信内容');
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
            $msg[] = array('phone'=>$key,'content'=>$va);
        }
        //content
        $param['content'] = json_encode($msg,JSON_UNESCAPED_UNICODE);
         //timestame  时间戳
        $param['timestamp'] = round(microtime(true) * 1000);
        //signature 生成
        $signature_arr = array('content'=>$param['content'],'extno'=>$param['extno'],'timestamp'=>$param['timestamp']);
        $param['signature'] = hash('sha256', urldecode(http_build_query($signature_arr). '&' .$param['SMS_KEY']),false);
        unset($param['SMS_KEY']);
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
        //timestame  时间戳
        $param['timestamp'] = round(microtime(true) * 1000);
        //signature 生成
        $signature_arr = array('timestamp'=>$param['timestamp']);
        $param['signature'] = hash('sha256', urldecode(http_build_query($signature_arr). '&' .$param['SMS_KEY']),false);
        unset($param['SMS_KEY']);
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
