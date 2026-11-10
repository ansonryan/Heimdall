FROM lscr.io/linuxserver/heimdall:latest

LABEL org.opencontainers.image.title="heimdall-lg" \
      org.opencontainers.image.description="Heimdall 2.8.3 with Liquid Glass UI, square grid, Casdoor OIDC and TMDB auto background" \
      org.opencontainers.image.source="https://github.com/ansonryan/Heimdall"

# 只覆盖改动过的文件，其余全部沿用上游镜像
COPY overlay/ /app/www-tmp/

# php-fpm 以 abc 运行：保证可读、public 可写，并恢复 /storage 软链
RUN chmod -R a+rX,u+w /app/www-tmp \
    && ln -sfn /config/www /app/www-tmp/public/storage

# 运行期约定：
#   /config/www/.env 追加 CASDOOR_* 与 TMDB_* 后执行
#   docker exec heimdall php artisan config:clear
