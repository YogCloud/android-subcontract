<?php

/** 响应成功
 * @param array|null $data
 * @param string $message
 * @param int $code
 * @return string
 */
function bi_ok(?array $data = null, string $message = '响应成功', int $code = 0): string
{
    return json_encode(['code' => $code, 'message' => $message, 'data' => $data]);
}

/** 响应失败
 * @param string $message
 * @param int $code
 * @return string
 */
function bi_fail(string $message = '响应失败', int $code = 1): string
{
    return json_encode(['code' => $code, 'message' => $message, 'data' => null]);
}

/** 输出信息
 * @param string $message
 * @param bool $date
 */
function line(string $message, bool $date = true)
{
    if ($date) {
        echo '[' . date('Y-m-d H:i:s') . ']' . $message . PHP_EOL;
    } else {
        echo $message . PHP_EOL;
    }
}

/** 删除指定文件夹
 * @param $dirName
 * @return bool
 */
function undirs($dirName)
{
    if (!is_dir($dirName)) {
        return false;
    }
    $handle = @opendir($dirName);
    while (($file = @readdir($handle)) !== false) {
        //判断是不是文件 .表示当前文件夹 ..表示上级文件夹 =2
        if ($file != '.' && $file != '..') {
            $dir = $dirName . '/' . $file;
            is_dir($dir) ? undirs($dir) : @unlink($dir);
        }
    }
    closedir($handle);
    @rmdir($dirName);
}