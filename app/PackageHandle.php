<?php

namespace App;


use Swoole\Coroutine\System;

/** 打包处理
 * Class PackageHandle
 * @package App
 */
class PackageHandle
{
    protected $appPath;
    protected $keyPath;
    protected $secret;
    protected $alias;
    protected $aliasSecret;

    public function __construct(string $appPath, string $keyPath, string $secret, string $alias, string $aliasSecret)
    {
        $this->appPath = $appPath;
        $this->keyPath = $keyPath;
        $this->secret = $secret;
        $this->alias = $alias;
        $this->aliasSecret = $aliasSecret;
    }

    public function package(): string
    {
        $java = 'java';
        $tool = BASE_PATH . '/lib/apktool.jar';
        $sign = BASE_PATH . '/lib/apksigner.jar';

        # 编译APK
        line('正在编译APK');
        $this->exec("{$java} -jar {$tool} b {$this->appPath}/app -o {$this->appPath}/reapp.apk");
        # 对齐APK
        line('正在对齐APK');
        $this->exec("zipalign -v 4 {$this->appPath}/reapp.apk {$this->appPath}/zipalign.apk");
        # 签名APK
        line('正在签名APK');
        $this->exec("{$java} -jar {$sign} sign  --ks {$this->keyPath}  --ks-key-alias {$this->alias}  --ks-pass pass:{$this->secret}  --key-pass pass:{$this->aliasSecret}  --out {$this->appPath}/app.apk  {$this->appPath}/zipalign.apk");
        return "{$this->appPath}/app.apk";
    }

    protected function exec(string $cmd)
    {
        line('执行命令 ' . $cmd);
        \system($cmd);
    }
}