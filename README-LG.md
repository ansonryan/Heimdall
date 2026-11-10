# Heimdall LG

基于 [linuxserver/Heimdall](https://github.com/linuxserver/Heimdall) 2.8.3 的定制版，只覆盖必要文件，其余保持上游不动。

## 改了什么

| 模块 | 说明 |
|---|---|
| Liquid Glass 主题 | 白玻璃 + 镜面镶边 + 指针跟随高光，跟随系统浅/深色 |
| 正方形栅格 | `#sortable` 改为 CSS Grid，卡片 `aspect-ratio: 1/1`；桌面自适应、手机 3 列 |
| Casdoor 登录 | 标准 OIDC 授权码流程，`/auth/casdoor` 与 `/auth/casdoor/callback` |
| TMDB 自动背景 | `/api/background` 返回热门剧照，前端每 10 分钟轮换 |

## 目录

```
overlay/                      # 覆盖到镜像 /app/www-tmp 的文件
resources/assets/sass/_liquid-glass.scss   # 主题源码
resources/assets/js/liquidGlass.js         # 指针高光 + 背景轮换
docker/heimdall-lg/Dockerfile              # 镜像定义
.github/workflows/build-image.yml          # 推 GHCR
```

## 镜像

```
ghcr.io/ansonryan/heimdall-lg:latest
ghcr.io/ansonryan/heimdall-lg:2.8.3-lg
```

## 配置

在 `/config/www/.env` 追加：

```env
# Casdoor / OIDC
CASDOOR_ENABLED=true
CASDOOR_BASE_URL=https://auth.smartryan.com
CASDOOR_CLIENT_ID=
CASDOOR_CLIENT_SECRET=
CASDOOR_REDIRECT_URI=https://nav.example.com/auth/casdoor/callback
CASDOOR_AUTO_CREATE=true

# TMDB 自动背景
TMDB_ENABLED=true
TMDB_API_KEY=
TMDB_MODE=backdrop      # backdrop=横版剧照，poster=竖版海报
TMDB_LANGUAGE=zh-CN
```

改完执行 `docker exec heimdall php artisan config:clear`。

Casdoor 侧把 Redirect URL 配成 `https://<你的域名>/auth/casdoor/callback`。

## 注意

- 覆盖层里包含编译后的 `public/css/app.css`（原文件 + 主题）。改动 SCSS 后需要重新编译并同步到 `overlay/public/css/app.css`。
- 上游用的是 Laravel Mix（`app.css?v=`），不是 Vite 的 `@vite`，所以样式走 `public/css/app.css` 而不是 `public/build`。
- 容器内 `opcache.validate_timestamps=Off`，改文件后要 `s6-svc -r /run/service/svc-php-fpm`。
