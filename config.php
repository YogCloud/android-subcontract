<?php

return [
    'debug' => false, # 是否测试环境
    'mch_id' => 'xxx', # 商户密钥
    /*
     * 腾讯云OSS配置
     */
    'tencent' => [
        'url' => 'https://xxx.cos.ap-chongqing.myqcloud.com/',
        'key' => 'xxx',
        'secret' => 'xxx',
        'region' => 'ap-chongqing',
        'bucket' => 'xxx',
    ],
    /*
     * 服务器配置
     */
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'mode' => SWOOLE_PROCESS,
        'setting' => [
            'worker_num' => 1,
            'task_worker_num' => swoole_cpu_num() * 2,
        ],
    ],
];