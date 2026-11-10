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

## 配置（后台设置页）

登录后进入 **Settings**，最后多出一组 **集成配置（OIDC / TMDB）**：

| 项 | 说明 |
|---|---|
| 启用 Casdoor 登录 | 打开后登录页出现 SSO 按钮 |
| Casdoor 地址 | 如 `https://auth.smartryan.com` |
| Casdoor Client ID / Secret | Casdoor 后台 Application 里取 |
| 回调地址 | 留空则自动用 `<站点地址>/auth/casdoor/callback` |
| 首次登录自动创建本地账号 | 关闭后只有已绑定用户能进 |
| 启用 TMDB 自动背景 | 打开后首页背景自动轮换 |
| TMDB API Key | v3 auth key |
| TMDB 语言 / 背景图类型 | `zh-CN`；横版剧照或竖版海报 |

这些项标记为 `system=1`，保存的是**全局值**（不是每个用户各一份）。

Casdoor 侧把 Redirect URL 配成 `https://<你的域名>/auth/casdoor/callback`。

改完不需要重启，保存即生效。`.env` 里的同名变量仍作为兜底（后台留空时才用）。

## 部署 / 升级

```sh
sh deploy.sh
```

脚本会拉镜像、重建容器、等待就绪、执行 `migrate`、清缓存。

容器重建本身**不会**自动跑迁移（`init-heimdall-config` 只在首次安装时执行），所以升级后必须显式执行一次：

```sh
docker exec -w /app/www heimdall php artisan migrate --force
```

## 注意

- 覆盖层里包含编译后的 `public/css/app.css`（原文件 + 主题）。改动 SCSS 后需要重新编译并同步到 `overlay/public/css/app.css`。
- 上游用的是 Laravel Mix（`app.css?v=`），不是 Vite 的 `@vite`，所以样式走 `public/css/app.css` 而不是 `public/build`。
- 容器内 `opcache.validate_timestamps=Off`，改文件后要 `s6-svc -r /run/service/svc-php-fpm`。
