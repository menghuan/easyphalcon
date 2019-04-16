<?php
/**
 * 数据库配置
 */
return [
    //默认数据库配置
    'database' => [
        "host" => "127.0.0.1",
        "username" => "root",
        "password" => "123456",
        "dbname" => "test",
        'prefix' => '',
        'charset' => 'utf8'  
    ],
    //持久连接用于cron任务
    'pdatabase' => [
        "host" => "127.0.0.1",
        "username" => "root",
        "password" => "123456",
        "dbname" => "test",
        'prefix' => '',
        'charset' => 'utf8',
        'persistent' => true, //持久链接
    ]
];

