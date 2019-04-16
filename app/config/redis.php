<?php
/**
 * Redis配置
 */
$redisPrefix = 'test:';
return [
    //默认数据库配置
    'redis' => [
        "host"       => "127.0.0.1",
        "port"       => 6379,
        "auth"       => "123456",
    ],
    'redisKeyPrefix'=>$redisPrefix,
];