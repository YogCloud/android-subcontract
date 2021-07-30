# 安卓渠道包构建服务

- 环境要求 
    - `Ubuntu:20`
    - `PHP7.4`
    - `Swoole4.x`
    - `Composer`
- 打包工具
    - `Java1.8` 打包工具环境基础
    - `apksigner.jar` APK签名工具V1&V2
    - `apktool.jar` APK编译与反编译工具
    - `zipalign` APK文件对齐工具
- 实现原理
    - 1. 通过向外提供接口服务
    - 2. 请求进来后 扔进异步队列中处理打包
    - 3. 处理成功后 安装包上传到腾讯云
    - 4. 上传成功后 通知下载地址到服务器
- 特别说明
    - APK安装包的渠道信息应当在 `AndroidManifest.xml` 中定义
    
### 目录结构

- `/start.php` 启动程序
- `/config.php` 配置文件
- `/runtime/lock/*` 进程锁文件
- `/runtime/key/<md5>.jks` 签名文件
- `/runtime/apk/<md5>.apk` 传入原始APK文件
- `/runtime/recompile/<md5>/*` 反编译后压缩文件
- `/runtime/compile/<md5>/<index>/app/*` 正在编译文件（多位用户操作多个索引）
- `/runtime/compile/<md5>/<index>/user.json` 是否正在占用（存放替换信息，方便重置环境）
- `/runtime/compile/<md5>/<index>/reapp.apk` 编译后APK
- `/runtime/compile/<md5>/<index>/zipalign.apk` 对齐后APK
- `/runtime/compile/<md5>/<index>/app.apk` 签名后APK

### 接口流程

- 传入参数
    - `mch_id` 商户ID
    - `app_url` 安装文件下载地址
    - `key_url` 密钥文件下载地址
    - `key_secret` 密钥文件密码
    - `key_alias` 密钥文件别名
    - `key_alias_secret` 密钥文件别名密码
    - `notify_url` 通知发送地址
    - `files` 增加文件信息
        - `files.*.path` 路径
        - `files.*.content` 内容
    - `replaces` 替换 `AndroidManifest.xml` 字符串内容
        - `replaces.*.key` 主键
        - `replaces.*.value` 内容
    - `attach` 回传参数
- 判断 下载安装文件
- 判断 下载密钥文件
- 判断 反编译安装文件
- 判断 压缩反编译文件到 zip
- 判断 解压到编译环境目录
    - 写入 渠道文件信息
    - 执行 文件编译命令
    - 执行 文件对齐命令
    - 执行 文件签名命令
    - 上传 签名后文件到云
    - 通知 打包后下载地址
        - 通知参数 `mch_id, download_url, attach`
        - 判断返回 `SUCCESS`
    
### 环境安装

- 安装 `Ubuntu:20`
    - 购买云主机/搭建虚拟机
    - 执行命令 `sudo apt-get update -y`
- 安装 `Java1.8`
    - 执行命令 `sudo apt-get install openjdk-8-jdk  -y`
    - 执行命令 `java -version`
- 安装 `zipalign`
    - 执行命令 `sudo apt-get install zipalign -y`
- 安装 `PHP7.4`
    - 执行命令 `sudo apt-get install wget vim -y`
    - 执行命令 `sudo apt-get install libzip-dev bison autoconf build-essential pkg-config git-core libltdl-dev libbz2-dev libxml2-dev libxslt1-dev libssl-dev libicu-dev libpspell-dev libenchant-dev libmcrypt-dev libpng-dev libjpeg8-dev libfreetype6-dev libmysqlclient-dev libreadline-dev libcurl4-openssl-dev librecode-dev libsqlite3-dev libonig-dev -y`
    - 执行命令 `wget https://www.php.net/distributions/php-7.4.0.tar.gz`
    - 执行命令 `tar zxvf php-7.4.0.tar.gz && cd php-7.4.0/`
    - 执行命令 `./configure --prefix=/usr/local/php7 --with-config-file-scan-dir=/usr/local/php7/etc/php.d --with-config-file-path=/usr/local/php7/etc --enable-mbstring --enable-zip --enable-bcmath --enable-pcntl --enable-ftp --enable-xml --enable-shmop --enable-soap --enable-intl --with-openssl --enable-exif --enable-calendar --enable-sysvmsg --enable-sysvsem --enable-sysvshm --enable-opcache --enable-fpm --enable-session --enable-sockets --enable-mbregex --enable-wddx --with-curl --with-iconv --with-gd --with-jpeg-dir=/usr --with-png-dir=/usr --with-zlib-dir=/usr --with-freetype-dir=/usr --enable-gd-jis-conv --with-openssl --with-pdo-mysql=mysqlnd --with-gettext=/usr --with-zlib=/usr --with-bz2=/usr --with-recode=/usr --with-xmlrpc --with-mysqli=mysqlnd`
    - 执行命令 `sudo make install`
    - 执行命令 `sudo cp /usr/local/php7/etc/php-fpm.conf.default /usr/local/php7/etc/php-fpm.conf`
    - 执行命令 `sudo cp /usr/local/php7/etc/php-fpm.d/www.conf.default /usr/local/php7/etc/php-fpm.d/www.conf`
    - 执行命令 `sudo cp php.ini-production /usr/local/php7/etc/php.ini`
    - 编辑文件 `sudo vim /etc/profile` 末尾加入行 `PATH=/usr/local/php7/bin:/usr/local/php7/sbin:$PATH`
    - 执行命令 `source /etc/profile`
    - 编辑文件 `sudo vim /etc/sudoers` 在 `Defaults secure_path` 后加入行 `/usr/local/php7/bin:/usr/local/php7/sbin:`
    - 执行命令 `php -v`
- 安装 `Swoole4.x`
    - 执行命令 `sudo wget http://pecl.php.net/get/swoole-4.4.18.tgz`
    - 执行命令 `tar zxvf swoole-4.4.18.tgz`
    - 执行命令 `sudo /usr/local/php7/bin/phpize`
    - 执行命令 `sudo ./configure --with-php-config=/usr/local/php7/bin/php-config \
                 --enable-coroutine \
                 --enable-openssl  \
                 --enable-http2  \
                 --enable-async-redis \
                 --enable-sockets \
                 --enable-mysqlnd`
    - 执行命令 `sudo make clean`
    - 执行命令 `sudo make`
    - 执行命令 `sudo make install`
    - 编辑文件 `sudo vim /usr/local/php7/etc/php.ini` 末尾加入 `extension=swoole.so`
    - 执行命令 `php -m`
- 安装 `Composer`
    - 执行命令 `php -r "copy('https://install.phpcomposer.com/installer', 'composer-setup.php');"`
    - 执行命令 `php composer-setup.php`
    - 执行命令 `php -r "unlink('composer-setup.php');"`
    - 执行命令 `sudo mv composer.phar /usr/local/bin/composer`
    - 执行命令 `composer self-update`
    - 执行命令 `composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/`
- 部署 `服务代码`
    - 执行命令 `sudo apt-get install git -y`
    - 拉取代码 `git clone https://github.com/yog-union/android-subcontract`
    - 执行命令 `cd android-subcontract`
    - 执行命令 `composer update`
    - 执行命令 `sudo chmod 777 -R *`
    - 更改配置 `sudo vim config.php`
    - 执行命令 `php start.php`

### 模拟请求

```shell script
# POST http://127.0.0.1:9501
{
    "mch_id": "xxx",
    "app_url": "https://cos.ap-chongqing.myqcloud.com/app.apk",
    "key_url": "https://cos.ap-chongqing.myqcloud.com/keystore",
    "key_secret": "B6570A1B5AF373D269BE27B8C1244650",
    "key_alias": "xxx",
    "key_alias_secret": "B6570A1B5AF373D269BE27B8C1244650",
    "notify_url": "http://service.com/notify",
    "files": [
        {"path": "/META-INF/services/channel", "content": "10086"}
    ],
    "replaces": [
        {"key":"CHANNEL_VALUE", "value":"10086"}
    ],
    "attach": "10086"
}
```
