<?php


namespace App;


/** 验证处理类
 * Class CheckHandle
 * @package App
 */
class CheckHandle
{
    protected $mchId;
    protected $data;

    public function __construct(?array $data, string $mchId)
    {
        $this->mchId = $mchId;
        $this->data = $data;
    }

    public function verify(): bool
    {
        if (
            !isset($this->data['mch_id']) || empty($this->data['mch_id']) || $this->data['mch_id'] != $this->mchId ||
            !isset($this->data['app_url']) || empty($this->data['app_url']) || !@fopen($this->data['app_url'], 'r') ||
            !isset($this->data['key_url']) || empty($this->data['key_url']) || !@fopen($this->data['key_url'], 'r') ||
            !isset($this->data['key_secret']) || empty($this->data['key_secret']) || !is_string($this->data['key_secret']) ||
            !isset($this->data['key_alias']) || empty($this->data['key_alias']) || !is_string($this->data['key_alias']) ||
            !isset($this->data['key_alias_secret']) || empty($this->data['key_alias_secret']) || !is_string($this->data['key_alias_secret']) ||
            !isset($this->data['notify_url']) || empty($this->data['notify_url']) || !@fopen($this->data['notify_url'], 'r') ||
            !isset($this->data['files']) || !is_array($this->data['files']) ||
            !isset($this->data['replaces']) || !is_array($this->data['replaces']) ||
            !isset($this->data['attach']) || empty($this->data['attach']) || !is_string($this->data['attach'])
        ) {
            return false;
        }
        return true;
    }
}