#!/bin/sh
# 一键更新：拉镜像、重建容器、执行数据库迁移
# 用法：在飞牛的 compose 目录执行  sh /path/to/deploy.sh
set -e

COMPOSE_DIR="${COMPOSE_DIR:-/vol1/1000/Docker/docker_compose/heimdall}"

echo "==> pull image"
docker pull ghcr.io/ansonryan/heimdall-lg:2.8.3-lg

echo "==> recreate container"
cd "$COMPOSE_DIR"
docker compose up -d

echo "==> wait for php-fpm"
i=0
while [ $i -lt 30 ]; do
  if docker exec heimdall sh -c 'wget -qO- --timeout=2 http://127.0.0.1/login >/dev/null 2>&1'; then
    break
  fi
  i=$((i + 1))
  sleep 2
done

echo "==> migrate"
docker exec -w /app/www heimdall php artisan migrate --force

echo "==> clear caches"
docker exec -w /app/www heimdall sh -c 'php artisan config:clear >/dev/null; php artisan view:clear >/dev/null'

echo "==> done"
docker exec heimdall sh -c 'wget -qO- --timeout=3 http://127.0.0.1/login >/dev/null && echo "login OK"'
