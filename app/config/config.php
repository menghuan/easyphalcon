<?php
/**
 * 配置文件
 */
$base_path = ROOT_PATH;
//数据库配置
$database = include $base_path.'/app/config/db.php';

//队列配置
$mq = include $base_path.'/app/config/mq.php';

//redis
$redis = include $base_path.'/app/config/redis.php';

//项目配置
$config =  [    
    //有效发送时间，工作时间。
    'working_time' => [
        'start' => '8:00',
        'end' => '20:00',
    ],
    
    //黑名单
    'blacklist' => [
        "unsub_number" => 2,
        'expire' => 180, //签名中的普通黑名单有效期，单位“天”。
    ],
    'statistics'=>[
        'time'=>300,
    ],
    'logDays'=>60,
    // 分库分表    
    'shardTable' => [
        /**  
         * 下面的配置对应1库8表 
         * 如果根据 产品id 进行分表，假设 project 为 12，对应的库表为：  
         *  (12 / 1) % 1 = 0 为编号为 1 的库  
         *  (12 / 1) % 8 = 4 为编号为 5 的表  
         */  
        'database_split' => array(1, 1),    
        'table_split' => array(1, 8), 
    ]
    
];
return array_merge($config, $database, $mq, $redis);