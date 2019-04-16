<?php

namespace Common\Services\SmsPlatForm\Parameter;

use Common\Services\SmsPlatForm\ChannelApiParamService;

/**
 * 鼎汉API参数 @todo 待测试
 * @author 苏云雷 <suyunlei@qiaodata.com>
 * @date 2018-02-23 14:00
 */
class ParameterHangYuanService
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
     *          'numbers' => '13641145677',
     *          'msgContent' => '内容1',
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
        $cutNum = strpos($data['content'],'】');
        if($cutNum) {
            $data['content'] = substr($data['content'],$cutNum+3,strlen($data['content'])-$cutNum);
        }

        $message  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $message .= '<MtMessage>';
        $message .= '<content>' . $data['content'] . '</content>';
        $message .=  '<phoneNumber>'.$data['mobile'].'</phoneNumber>';
        $message .= '<sendTime></sendTime>';
        $message .= '<smsId></smsId>';
        $message .= '<subCode>'.$ext.'</subCode>';
        $message .= '<templateId></templateId>';
        $message .= '</MtMessage>';

        $param['message'] = $message;
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
        return false;
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
        return false;
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
