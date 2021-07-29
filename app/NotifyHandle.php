<?php


namespace App;


use Swoole\Coroutine\System;

class NotifyHandle
{
    protected $url;
    protected $mchId;
    protected $attach;
    protected $notifyUrl;

    public function __construct(string $url, string $mchId, string $notifyUrl, string $attach)
    {
        $this->url = $url;
        $this->mchId = $mchId;
        $this->attach = $attach;
        $this->notifyUrl = $notifyUrl;
    }

    public function notify()
    {
        $reInx = 0; # 执行次数
        $reMax = 1; # 重复请求3次
        do {
            line('正在通知签名完毕 ' . $this->notifyUrl . '#' . ($reInx + 1) . ' 间隔' . ($reInx * 10) . 's');
            $resp = (new \GuzzleHttp\Client)->post($this->notifyUrl, [
                'form_params' => [
                    'mch_id' => $this->mchId,
                    'download_url' => $this->url,
                    'attach' => $this->attach,
                ],
            ]);
            $isOk = $resp->getStatusCode() == 200 && $resp->getBody() == 'SUCCESS';
            $reInx += 1;
            if ($reInx > 0) {
                sleep($reInx * 10);
            }
        } while (!$isOk && $reInx < $reMax);
    }
}