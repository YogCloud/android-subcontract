<?php


namespace App;


/** 上传处理
 * Class UploadHandle
 * @package App
 */
class UploadHandle
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function upload(string $path): string
    {
        $key = date('YmdHis') . '/' . md5($path) . '.apk';

        // 构建腾讯云上传
        line('正在上传APK ' . $path);
        $cosClient = new \Qcloud\Cos\Client([
            'region' => $this->config['region'],
            'credentials' => [
                'secretId' => $this->config['key'],
                'secretKey' => $this->config['secret'],
            ]
        ]);

        $body = fopen($path, 'r');
        if($body === false) {
            throw new \Exception('文件句柄获取失败');
        }

        // 发起上传
        $cosClient->putObject([
            'Bucket' => $this->config['bucket'],
            'Key' => $key,
            'Body' => $body,
        ]);

        $url = $this->config['url'] . $key;
        line('成功上传APK文件 ' . $url);
        return $url;
    }
}