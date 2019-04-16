<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;
use Common\Services\SmsPlatForm\SignService;

/**
 * 数米API参数
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterMengWangService
{

    public function __construct()
    {

    }

    /**
     * 创建单条发送短信的接口参数
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-03-09 11:00
     * @return array
     */
    public function createSendOneParam($apiId, $data,$ext)
    {
        return;
    }

    /**
     * 创建多条发送短信的接口参数  暂不支持批量发送不同内容(批量发送返还无手机号只有msgid)
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-03-09 15:16
     * @param int $apiId API id   array  $data  array('手机号'=>'短信内容');
     * @return array
     */
    public function createSendMoreParam($apiId, $data, $ext, $signId)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        $param['multixmt'] = '';
        $signInfo = (new SignService())->getByPrimaryKey($signId, ['name']);
        foreach( $data as $d ){
            $param['multixmt'] .= '0|*|'.$d['mobile'].'|'.base64_encode(iconv('UTF-8','GB2312',str_replace('【'.$signInfo->name.'】','',$d['content']))).',';
        }
        $param['multixmt'] = trim($param['multixmt'], ',');
        return $param;
    }

    /**
     * 创建获取余额的接口参数
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-03-09 11:08
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
     * @author 苏云雷 <suyunlei@qiaodata.com>
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
