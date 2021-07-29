<?php


namespace App;


use Swoole\Coroutine\System;

class Lock
{
    protected $fp;
    protected $path;
    protected $timeout;

    public function __construct(string $name, int $timeout = 30)
    {
        $this->path = BASE_PATH . '/runtime/lock/' . md5($name);
        $this->fp = fopen($this->path, 'a');
        if ($this->fp === false) {
            throw new \Exception('获取' . $name . '锁失败');
        }
        $this->timeout = $timeout;
    }

    /** 获取锁-非阻塞版本
     * @return bool
     */
    public function try(): bool
    {
        return flock($this->fp, LOCK_EX | LOCK_NB);
    }

    /** 获取锁-阻塞版本
     */
    public function lock()
    {
        // 第一次获取
        $lock = $this->try();
        if ($lock) {
            return true;
        }
        // 重复获取直到成功或超时
        $currentTime = $startTime = time(); # 当前时间
        while (!$lock && $currentTime < ($startTime + $this->timeout)) {
            usleep(50000); // 暂停50毫秒 单位=微秒
            $currentTime = time();
            $lock = $this->try();
        }
        return $lock;
    }

    /** 释放锁
     */
    public function release()
    {
        $i = 0;
        do {
            $r = flock($this->fp, LOCK_UN);
            $i++;
            if ($i > 3) {
                throw new \Exception('释放锁失败');
            }
        } while (!$r);
        @unlink($this->path);
    }
}