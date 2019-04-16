<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * 数米API参数
 * @author 董光明 <dongguangming@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterShuMiService
{

    public function __construct()
    {

    }

    /**
     * 创建单条发送短信的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-09 11:00
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
        }
        //时间戳
        $param['timespan'] = date('YmdHis', time());
        $param['pwd'] = strtoupper(md5($param['pwd'] . $param['timespan']));
        $param['content'] = base64_encode($data['content']);
        $param['mobile'] = $data['mobile'];
        if(!empty($ext)){
            $param['ext'] = $ext;
        }
        return $param;
    }

    /**
     * 创建多条发送短信的接口参数  暂不支持批量发送不同内容(批量发送返还无手机号只有msgid)
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-09 15:16
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
     * 创建获取余额的接口参数
     * @author 李新招 <lixinzhao@qiaodata.com>
     * @date 2017-03-09 11:08
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
        $param['timespan'] = date('YmdHis', time());
        $param['pwd'] = strtoupper(md5($param['pwd'] . $param['timespan']));
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
