<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterSaiYouService
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
     * [
     *      'mobile' => 13641145677,
     *      'content' => '短信内容',
     *],
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
        unset($param['key_status']);
        unset($param['key_message']);
        $param['content'] = $data['content'];
        $param['to'] = $data['mobile'];
        return $param;
    }

    /**
     * 创建多条发送短信的接口参数  (暂不支持批量发送不同内容)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:16
     * @param int $apiId API id   array  $data  array('手机号'=>'短信内容');
     * @return array
     */
    public function createSendMoreParam($apiId, $data)
    {
        return;
//        $parameters = $this->getByApiId($apiId);
//        if (empty($parameters) || 0 >= $parameters->count()) {
//            return [];
//        }
//        $param = [];
//        foreach ($parameters as $key => $paramTmp) {
//            $param[$paramTmp->param_key] = $paramTmp->param_value;
//        }
//        $param['msg'] = '';
//        foreach( $data as $d ){
//            $param['msg'] .= mb_convert_encoding($d, 'utf-8').',';
//        }
//        $param['msg'] = trim( $param['msg'] );
//        $param['mobile'] = implode( ',', array_keys($data) );
//        return $param;
    }

    /**
     * 创建获取余额的接口参数  (余额在发送中显示，没具体的查询接口)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-07 15:54
     * @param int $apiId API id
     * @return array
     */
    public function createBalanceParam($apiId)
    {
        return;
//        $parameters = $this->getByApiId($apiId);
//        if (empty($parameters) || 0 >= $parameters->count()) {
//            return [];
//        }
//        $param = [];
//        foreach ($parameters as $key => $paramTmp) {
//            $param[$paramTmp->param_key] = $paramTmp->param_value;
//        }
//        return $param;
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
