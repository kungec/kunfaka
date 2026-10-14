#!/bin/sh
# 坤发卡容器启动前置: 确保可写目录存在且属主正确
set -e

mkdir -p /var/www/html/data /var/www/html/uploads
# 挂载卷可能改变属主, 启动时整站归一到www-data(安装向导需写data/并重命名admin.php)
chown -R www-data:www-data /var/www/html

exec "$@"
