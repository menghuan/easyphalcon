<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * API参数
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2017-3-2 20:07:27
 */
class ParameterAnXinJieService
{
    /**
     * 创建单条发送短信的接口参数
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 11:00
     * @param int $apiId API id
     * @param array  $data
     *      [
     *          'name' => '123',
     *          'pass' => '123',
     *          'mobiles' => '13641145677',
     *          'content' => '内容1,内容2',
     *      ],
     * @return array
     */
    public function createSendOneParam($apiId, $data, $ext)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $param = [];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        if(!empty($ext)){
            $param['subid'] = $ext;
        }
        $param['content'] = urlencode($data['content']);
        $param['mobiles'] = $data['mobile'];
        return $param;
    }

    /**
     * 创建多条发送短信的接口参数
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-06-15 11:00
     * @param int $apiId API id
     * @param array  $data
     *[
     *      [
     *          'name' => '123',
     *          'pass' => '123',
     *          'mobile' => '13641145677,15210089171',
     *          'content' => '内容1,内容2',
     *      ],
     * ]
     * @return array
     */
    public function createSendMoreParam($apiId, $data,$ext)
    {
        $parameters = $this->getByApiId($apiId);
        if (empty($parameters) || 0 >= $parameters->count()) {
            return [];
        }
        $param = [
            'mobiles'=>'',
            'content'=>''
        ];
        foreach ($parameters as $key => $paramTmp) {
            $param[$paramTmp->param_key] = $paramTmp->param_value;
        }
        foreach($data as $d){
            $param['mobiles'] .= $d['mobile'].',';
            $param['content'] .= urlencode($d['content']).',';
        }
        $param['mobiles'] = trim($param['mobiles'],',');
        $param['content'] = trim($param['content'],',');
        if(!empty($ext)){
            $param['subid'] = $ext;
        }
        return $param;
    }

    /**
     * 创建获取余额的接口参数
     * @author 苏云雷 <suyunlei@qiaodata.com>
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
     * @author 苏云雷 <suyunlei@qiaodata.com>
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
     * @author 苏云雷 <suyunlei@qiaodata.com>
     * @date 2017-03-14 09:33
     * @param int $apiId API id
     * @return array
     */
    public function createReplayParam($apiId)
    {
       return;
    }

    /**
     * 根据API ID 查找接口参数
     * @author 苏云雷 <dongguangming@qiaodata.com>
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