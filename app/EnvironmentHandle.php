<?php


namespace App;

use Swoole\Coroutine\System;

/** 环境处理
 * Class EnvironmentHandle
 * @package App
 */
class EnvironmentHandle
{
    protected $data;
    protected $files;
    protected $replaces;
    protected $apkUrl;
    protected $keyUrl;

    protected $apkPath;
    protected $keyPath;
    protected $timeout = 60; // 锁超时时间
    protected $maxRequest = 10; // 最大请求数量
    protected $lock;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->files = $data['files'];
        $this->replaces = $data['replaces'];
        $this->apkUrl = $data['app_url'];
        $this->keyUrl = $data['key_url'];
    }

    /** 读取密钥文件
     * @param string $hash
     * @throws \Exception
     */
    protected function loadKey(string $hash)
    {
        $lock = new Lock('k:' . $hash, $this->timeout);
        if ($lock->lock()) {
            # 获取锁成功
            $this->keyPath = BASE_PATH . '/runtime/key/' . $hash . '.jks';
            if (file_exists($this->keyPath)) {
                line('忽略下载key文件');
                $lock->release();
            } else {
                // 下载证书文件
                line('正在下载key文件');
                file_put_contents($this->keyPath, file_get_contents($this->keyUrl));
                $lock->release();
            }
        } else {
            # 获取锁成功
            throw new \Exception('获取key部署锁失败');
        }
    }

    /** 加载Apk
     * @param string $hash
     * @throws \Exception
     */
    protected function loadApk(string $hash)
    {
        $lock = new Lock('k:' . $hash, $this->timeout);
        if ($lock->lock()) {
            # 获取锁成功
            $apkPath = BASE_PATH . '/runtime/apk/' . $hash . '.apk';
            $zipPath = BASE_PATH . '/runtime/recompile/' . $hash;
            if (file_exists($apkPath)) {
                line('忽略下载apk文件');
            } else {
                // 下载APK
                line('正在下载apk文件');
                file_put_contents($apkPath, file_get_contents($this->apkUrl));
            }
            if (is_dir($zipPath)) {
                line('忽略反编译apk文件');
                $lock->release();
            } else {
                // 构建参数
                line('正在反编译apk文件');
                $java = 'java';
                $tool = BASE_PATH . '/lib/apktool.jar';
                $base = BASE_PATH . '/runtime/recompile/' . $hash;
                // 删除原目录
                undirs($base);
                // 反编译到文件夹
                \system("{$java} -jar {$tool} d {$apkPath} -f -o $base");
                $lock->release();
            }
        } else {
            # 获取锁失败
            throw new \Exception('获取apk部署锁失败');
        }
    }

    /** 分配环境
     * @param string $hash
     * @throws \Exception
     */
    protected function loadAllow(string $hash)
    {
        $lock = new Lock('a:' . $hash, $this->timeout);
        if ($lock->lock()) {
            line('正在分配处理环境');
            # 获取锁成功
            for ($i = 1; $i < $this->maxRequest; $i++) {
                $this->lock = new Lock('f:' . $hash . ':' . $i, $this->timeout);
                // 判断是否可占用
                if ($this->lock->try()) {
                    $lock->release();
                    // 部署环境
                    $this->deploy($hash, $i);
                    return;
                }
            }
            line('未找到可用处理环境');
            $lock->release();
            throw new \Exception('获取apk环境锁失败');
        } else {
            # 获取锁失败
            throw new \Exception('获取apk环境锁失败');
        }
    }

    /** 部署环境
     * @param string $hash
     * @param int $index
     */
    protected function deploy(string $hash, int $index)
    {
        line('正在部署环境 ' . $hash . ':' . $index);
        $this->apkPath = BASE_PATH . '/runtime/compile/' . $hash . '/' . $index;
        $userPath = $this->apkPath . '/user.json';
        if (is_dir($this->apkPath)) {

            // 清除历史环境文件
            line('正在清理历史文件');
            $user = json_decode(file_get_contents($userPath), true);
            if ($user && isset($user['files']) && is_array($user['files'])) {
                foreach ($user['files'] as $item) {
                    @unlink($this->apkPath . '/app' . $item['path']);
                }
            }
            @unlink($this->apkPath . '/user.json');
            @unlink($this->apkPath . '/reapp.apk');
            @unlink($this->apkPath . '/zipalign.apk');
            @unlink($this->apkPath . '/app.apk');
            @unlink($this->apkPath . '/app/AndroidManifest.xml');

            // 重新复制 AndroidManifest.xml 文件
            line('正在恢复AndroidManifest.xml文件');
            @unlink($this->apkPath . '/app/AndroidManifest.xml');
            $reCompile = BASE_PATH . '/runtime/recompile/' . $hash;
            line("执行命令 cp {$reCompile}/AndroidManifest.xml {$this->apkPath}/app/AndroidManifest.xml");
            \system("cp {$reCompile}/AndroidManifest.xml {$this->apkPath}/app/AndroidManifest.xml");
        } else {
            line('正在复制新的环境');
            // 空文件夹则复制文件过去
            $reCompile = BASE_PATH . '/runtime/recompile/' . $hash;
            mkdir($this->apkPath, 0755, true);
            line("执行命令 cp -r {$reCompile} {$this->apkPath}/app");
            \system("cp -r {$reCompile} {$this->apkPath}/app");
        }

        // 写用户文件
        line('正在写用户文件');
        file_put_contents($userPath, json_encode($this->data));

        // 写渠道文件
        line('正在写渠道文件');
        foreach ($this->files as $item) {
            file_put_contents($this->apkPath . '/app' . $item['path'], $item['content']);
        }

        // 替换 AndroidManifest.xml
        line('替换渠道字符串');
        $manifestName = $this->apkPath . '/app/AndroidManifest.xml';
        $manifestValue = file_get_contents($manifestName);
        foreach ($this->replaces as $item) {
            $manifestValue = str_replace($item['key'], (string)$item['value'], $manifestValue);
        }
        file_put_contents($manifestName, mb_convert_encoding($manifestValue, "UTF-8", "auto"));
    }

    /** 释放环境
     */
    public function release()
    {
        if ($this->lock) {
            $this->lock->release();
        }
    }

    /** 分配环境
     * @throws \Exception
     */
    public function allow()
    {
        // 下载证书文件
        $this->loadKey(md5($this->keyUrl));
        // 下载APK文件
        $this->loadApk(md5($this->apkUrl));
        // 分配打包环境
        $this->loadAllow(md5($this->apkUrl));
    }

    public function getApkPath(): string
    {
        return $this->apkPath;
    }

    public function getKeyPath(): string
    {
        return $this->keyPath;
    }
}