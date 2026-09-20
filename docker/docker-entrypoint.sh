#!/bin/sh
# 坤发卡容器启动前置: 确保可写目录存在且属主正确
set -e

mkdir -p /var/www/html/data /var/www/html/uploads
chown -R www-data:www-data /var/www/html/data /var/www/html/uploads

exec "$@"
