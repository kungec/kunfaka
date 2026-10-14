FROM php:8.2-apache

# 系统依赖: GD(freetype/jpeg/png/webp 验证码与缩略图) + zip(应用商店解压) + 数据库与金额计算扩展
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        zip \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql bcmath zip opcache \
    && a2enmod rewrite expires headers \
    && rm -rf /var/lib/apt/lists/*

# PHP 生产运行配置(OPcache / 上传限制 / 隐藏版本)
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=30'; \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=12M'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/kunfaka.ini

# 站点配置(AllowOverride 开启后, 根目录 .htaccess 提供伪静态与 data 目录保护)
COPY docker/apache-kunfaka.conf /etc/apache2/sites-available/000-default.conf

COPY . /var/www/html

COPY docker/docker-entrypoint.sh /usr/local/bin/kunfaka-entrypoint
# 整站授予 www-data: 安装向导需要写data/并在根目录重命名admin.php; 在线更新/应用市场安装同样写站点目录。
# 更高安全要求的部署可在安装完成后手动收回根目录写权限(仅保留data/、uploads/可写)
RUN chmod 755 /usr/local/bin/kunfaka-entrypoint \
    && mkdir -p /var/www/html/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["kunfaka-entrypoint"]
CMD ["apache2-foreground"]
