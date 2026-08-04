<?php

return [
    // 默认使用的数据库连接配置
    'default'         => env('DB_DRIVER', 'mysql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段
    // true为自动识别类型 false关闭
    // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
    'auto_timestamp'  => true,

    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',

    // 时间字段配置 配置格式：create_time,update_time
    'datetime_field'  => '',

    // 数据库连接配置信息
    'connections'     => [
        'mysql' => [
            'type'            => env('DB_TYPE', 'mysql'),
            'hostname'        => env('DB_HOST', '127.0.0.1'),
            'database'        => env('DB_NAME', 'qianzhi_admin'),
            'username'        => env('DB_USER', 'root'),
            'password'        => env('DB_PASS', ''),
            'hostport'        => env('DB_PORT', '3306'),
            'charset'         => env('DB_CHARSET', 'utf8mb4'),
            'prefix'          => '',
            'params'          => [],
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => env('APP_DEBUG', true),
            'fields_cache'    => false,
        ],

        // 谐音梗猜一猜 项目库
        'think1' => [
            'type'            => 'mysql',
            'hostname'        => env('PROJ_THINK1_DB_HOST', '127.0.0.1'),
            'database'        => env('PROJ_THINK1_DB_NAME', 'sofun_online'),
            'username'        => env('PROJ_THINK1_DB_USER', 'root'),
            'password'        => env('PROJ_THINK1_DB_PASS', ''),
            'hostport'        => env('PROJ_THINK1_DB_PORT', '3306'),
            'charset'         => 'utf8mb4',
            'prefix'          => '',
            'params'          => [],
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => false,
            'fields_cache'    => false,
        ],

        // 公司官网库（E:\php\qianzhigame）
        // 与管理库同一 MySQL 实例，未单独配置 WEBSITE_DB_* 时复用 DB_* 的账号
        'website' => [
            'type'            => 'mysql',
            'hostname'        => env('WEBSITE_DB_HOST', env('DB_HOST', '127.0.0.1')),
            'database'        => env('WEBSITE_DB_NAME', 'qianzhi_website'),
            'username'        => env('WEBSITE_DB_USER', env('DB_USER', 'root')),
            'password'        => env('WEBSITE_DB_PASS', env('DB_PASS', '')),
            'hostport'        => env('WEBSITE_DB_PORT', env('DB_PORT', '3306')),
            'charset'         => 'utf8mb4',
            'prefix'          => '',
            'params'          => [],
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => false,
            'fields_cache'    => false,
        ],

        // 支付库
        'qianzhi_pay' => [
            'type'            => 'mysql',
            'hostname'        => env('PAY_DB_HOST', '127.0.0.1'),
            'database'        => env('PAY_DB_NAME', 'qianzhi_pay'),
            'username'        => env('PAY_DB_USER', 'qianzhi_pay'),
            'password'        => env('PAY_DB_PASS', '7ArSFmzy6hKSteY8'),
            'hostport'        => env('PAY_DB_PORT', '3306'),
            'charset'         => 'utf8mb4',
            'prefix'          => '',
            'params'          => [],
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => false,
            'fields_cache'    => false,
        ],
    ],
];
