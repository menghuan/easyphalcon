<?php
namespace Common\Services\SmsPlatForm;

/**
 * 参数类工场
 * @author wangjianghua
 * @date 2018-3-2 10:29:51
 */
class ParamFactoryService
{
    /**
     * 获取参数Service实例
     * @author wangjianghua
     * @date 2018-03-02 19:54
     * @param \Common\Model\SmsPlatForm\Channel $channel Channel model object
     * @return \Common\Services\SmsPlatForm\Parameter\ParameterService
     */
    public static function createParamServiceInstance($channel)
    {
        if (empty($channel)) {
            return false;
        }

        $namespace = "Common\\Services\\SmsPlatForm\\Parameter\\";
        if (empty($channel->param_class)) {
            $className = $namespace . "ParameterService";
        } else {
            $className = $namespace . $channel->param_class;
        }
        return new $className();
    }
}
