<?php
namespace Common\Services\SmsPlatForm;

/**
 * 解析结果类工厂
 * @author wangjianghua
 * @date 2018-3-3 14:43:31
 */
class ParseResultFactoryService
{
    /**
     * 创建解析放回置的类实例
     * @author wangjianghua
     * @date 2018-03-03 14:47
     * @param Common\Models\SmsPlatForm\Channel $channel 短信通道对象
     */
    public static function createParseServiceInstance($channel)
    {
        if (empty($channel) || empty($channel->parse_result_class)) {
            return null;
        }

        $class = "Common\\Services\\SmsPlatForm\\ParseResult\\{$channel->parse_result_class}";
        return new $class();
    }
}
