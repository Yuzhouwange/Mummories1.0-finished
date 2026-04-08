FROM docker.1ms.run/library/php:8.2-fpm

# 安装系统依赖（gd 需要图形库，mbstring 需要 oniguruma）
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli curl mbstring gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP 配置：上传限制 + 错误日志输出到 stderr（docker logs 可见）
RUN echo "upload_max_filesize = 100M\n\
post_max_size = 105M\n\
max_execution_time = 120\n\
memory_limit = 256M\n\
error_log = /proc/self/fd/2\n\
log_errors = On" > /usr/local/etc/php/conf.d/app.ini

# 创建运行时目录
RUN mkdir -p /var/www/html/uploads /var/www/html/avatars \
    /var/www/backend/uploads/media \
    /var/www/chat/config /var/www/chat/keys \
    /var/www/chat/uploads /var/www/chat/avatars \
    && chown -R www-data:www-data /var/www/ \
    && chmod -R 755 /var/www/

EXPOSE 9000
