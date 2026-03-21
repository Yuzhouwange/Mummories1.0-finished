FROM docker.1ms.run/library/php:8.2-fpm

# 安装 PHP 扩展
RUN docker-php-ext-install pdo_mysql mysqli

# PHP 上传限制
RUN echo "upload_max_filesize = 50M\npost_max_size = 55M\nmax_execution_time = 120\nmemory_limit = 256M" > /usr/local/etc/php/conf.d/uploads.ini

# 创建上传目录
RUN mkdir -p /var/www/html/uploads /var/www/html/avatars /var/www/backend/uploads/media \
    && chown -R www-data:www-data /var/www/ \
    && chmod -R 755 /var/www/

EXPOSE 9000
