<?php

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
date_default_timezone_set('Asia/Shanghai');
error_reporting(E_ALL ^ E_NOTICE);
!defined('BASE_PATH') && define('BASE_PATH', __DIR__);

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/function.php';

/*
 * 创建目录
 */
!is_dir(BASE_PATH . '/runtime/key') && @mkdir(BASE_PATH . '/runtime/key', 0755, true);
!is_dir(BASE_PATH . '/runtime/apk') && @mkdir(BASE_PATH . '/runtime/apk', 0755, true);
!is_dir(BASE_PATH . '/runtime/lock') && @mkdir(BASE_PATH . '/runtime/lock', 0755, true);
!is_dir(BASE_PATH . '/runtime/compile') && @mkdir(BASE_PATH . '/runtime/compile', 0755, true);
!is_dir(BASE_PATH . '/runtime/recompile') && @mkdir(BASE_PATH . '/runtime/recompile', 0755, true);

/*
 * 读取配置
 */
$config = require BASE_PATH . '/config.php';

/*
 * 创建Http
 */
$http = new \Swoole\Http\Server($config['server']['host'], $config['server']['port'], $config['server']['mode']);
$http->set($config['server']['setting']);
$http->on('request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($http, $config) {

    line('Request Start #' . $request->streamId);
    if(method_exists($request, 'getContent')) {
        $data = $request->getContent();
    } else {
        $data = $request->rawContent();
    }
    $data = json_decode($data, true);
    # 验证请求参数
    $check = new \App\CheckHandle($data, $config['mch_id']);
    if ($check->verify()) {
        # 投递任务
        $http->task(json_encode($data));
        $response->end(bi_ok());
    } else {
        $response->end(bi_fail('无效的传入参数'));
    }
    line('Request End #' . $request->streamId);
});
$http->on('Task', function ($server, $task_id, $reactor_id, $data) use ($config) {
    # 读取参数
    $data = json_decode($data, true);
    # 部署环境
    $env = new \App\EnvironmentHandle($data);
    try {
        line('Task Start #' . $task_id);
        # 分配环境
        $env->allow();
        # 打包文件
        $path = (new \App\PackageHandle($env->getApkPath(), $env->getKeyPath(), $data['key_secret'], $data['key_alias'], $data['key_alias_secret']))->package();
        # 上传文件
        $path = (new \App\UploadHandle($config['tencent']))->upload($path);
        # 通知用户
        (new \App\NotifyHandle($path, $data['mch_id'], $data['notify_url'], $data['attach']))->notify();
        # 释放环境
        $env->release();
        line('Task End #' . $task_id);
    } catch (\Throwable $e) {
        # 释放环境
        $env->release();
        line('Task Error #' . $task_id . PHP_EOL . get_class($e) . '#' . $e->getCode() . PHP_EOL . $e->getMessage() . PHP_EOL . $e->getFile() . '#' . $e->getLine() . PHP_EOL . $e->getTraceAsString());
    }
});
line('======================================', false);
line('=                                    =', false);
line('= 欢迎使用 Android APK Build', false);
line('= 访问地址 http://' . $config['server']['host'] . ':' . $config['server']['port'], false);
line('= 启动时间 ' . date('Y-m-d H:i:s'), false);
line('=                                    =', false);
line('======================================', false);
$http->start();
