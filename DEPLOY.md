# Mummories 部署教程

本教程将指导你从零开始部署 Mummories 个人博客系统。

---

## 目录

1. [环境要求](#1-环境要求)
2. [本地部署（Windows）](#2-本地部署windows)
3. [本地部署（Linux/Mac）](#3-本地部署linuxmac)
4. [服务器部署](#4-服务器部署)
5. [配置说明](#5-配置说明)
6. [首次使用](#6-首次使用)
7. [常用命令](#7-常用命令)
8. [常见问题](#8-常见问题)

---

## 1. 环境要求

- **Docker Desktop** >= 4.0（包含 Docker Compose v2）
- **磁盘空间** >= 2GB
- **内存** >= 2GB

### 安装 Docker

| 系统 | 下载地址 |
|------|----------|
| Windows | https://docs.docker.com/desktop/install/windows-install/ |
| Mac | https://docs.docker.com/desktop/install/mac-install/ |
| Linux | https://docs.docker.com/engine/install/ |

> Windows 用户需要启用 WSL2 或 Hyper-V。

---

## 2. 本地部署（Windows）

### 步骤 1：下载项目

```bash
git clone https://github.com/你的用户名/Mummories.git
cd Mummories
```

### 步骤 2：配置环境变量

```bash
# 复制配置模板
copy .env.example .env
copy chat\.env.example chat\.env
```

用记事本打开 `.env`，修改以下内容：

```ini
# 设置一个安全的数据库密码（必须修改）
DB_PASSWORD=MySecure123!

# 设置管理员后台登录密钥（必须修改）
HOMEPAGE_API_KEY=my_secret_admin_key
```

同样修改 `chat\.env`：

```ini
DB_PASS=MySecure123!
DB_PASSWORD=MySecure123!
HOMEPAGE_API_KEY=my_secret_admin_key
```

> ⚠️ 两个 `.env` 文件中的数据库密码必须一致。

### 步骤 3：一键启动

双击 `start.bat`，或在终端运行：

```bash
docker compose up -d --build
```

### 步骤 4：访问

等待约 30 秒服务启动，然后打开浏览器：

- 博客主页：http://localhost:8080
- 聊天室：http://localhost:8080/chat/chat.php
- 后台管理：http://localhost:8080/admin.html
- 数据库管理：http://localhost:9888

---

## 3. 本地部署（Linux/Mac）

### 步骤 1：下载项目

```bash
git clone https://github.com/你的用户名/Mummories.git
cd Mummories
```

### 步骤 2：配置环境变量

```bash
cp .env.example .env
cp chat/.env.example chat/.env
```

编辑 `.env`：

```bash
nano .env
```

```ini
DB_PASSWORD=MySecure123!
HOMEPAGE_API_KEY=my_secret_admin_key
```

编辑 `chat/.env`，填入相同的数据库密码。

### 步骤 3：启动

```bash
docker compose up -d --build
```

### 步骤 4：验证

```bash
# 查看服务状态
docker compose ps

# 查看日志
docker compose logs -f
```

全部 4 个服务显示 `running` 即为成功。

---

## 4. 服务器部署

以 Ubuntu/Debian 服务器为例。

### 步骤 1：安装 Docker

```bash
# 安装 Docker
curl -fsSL https://get.docker.com | sh

# 启动 Docker
sudo systemctl enable docker
sudo systemctl start docker

# 当前用户加入 docker 组（免 sudo）
sudo usermod -aG docker $USER
newgrp docker
```

### 步骤 2：上传项目

```bash
# 方法 A：git clone
git clone https://github.com/你的用户名/Mummories.git
cd Mummories

# 方法 B：scp 上传
scp -r Mummories/ user@your-server:/home/user/
```

### 步骤 3：配置

```bash
cp .env.example .env
cp chat/.env.example chat/.env
nano .env
```

修改 `.env`：

```ini
DB_PASSWORD=一个强密码
HOMEPAGE_API_KEY=一个复杂的管理员密钥

# 如果要改端口
HTTP_PORT=80
PHPMYADMIN_PORT=9888
```

> 生产环境建议：使用强密码，关闭或限制 phpMyAdmin 访问。

### 步骤 4：启动

```bash
docker compose up -d --build
```

### 步骤 5：配置防火墙

```bash
# 放行 HTTP 端口
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 不要放行 phpMyAdmin 的 9888 端口到公网！
```

### 步骤 6：配置域名（可选）

如果你有域名，在 DNS 解析中添加 A 记录指向服务器 IP，然后可以用 Nginx 反向代理或 Certbot 配置 HTTPS：

```bash
# 安装 certbot（以宿主机 Nginx 为例）
sudo apt install certbot python3-certbot-nginx

# 宿主机 Nginx 配置示例（/etc/nginx/sites-available/mummories）
server {
    listen 80;
    server_name your-domain.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        client_max_body_size 50M;
    }
}

# 启用站点 & 申请证书
sudo ln -s /etc/nginx/sites-available/mummories /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d your-domain.com
```

---

## 5. 配置说明

### .env 文件

| 变量 | 说明 | 默认值 |
|------|------|--------|
| `DB_HOST` | 数据库地址 | `db` (Docker 内部) |
| `DB_NAME` | 数据库名 | `mummories` |
| `DB_USER` | 数据库用户 | `root` |
| `DB_PASSWORD` | 数据库密码 | **必填** |
| `APP_URL` | 站点地址 | `http://localhost:8080` |
| `HTTP_PORT` | HTTP 端口 | `8080` |
| `PHPMYADMIN_PORT` | phpMyAdmin 端口 | `9888` |
| `HOMEPAGE_API_KEY` | 管理员密钥 | **必填** |

### chat/.env 文件

| 变量 | 说明 |
|------|------|
| `DB_PASS` / `DB_PASSWORD` | 数据库密码（与主 .env 一致） |
| `HOMEPAGE_API_KEY` | 管理员密钥（与主 .env 一致） |

### 架构说明

```
用户浏览器
    │
    ▼ :8080
┌──────────┐
│  Nginx   │──── 静态文件 ──→ frontend/
│          │──── /api/v1/  ──→ PHP-FPM (homepage_api.php)
│          │──── /chat/    ──→ PHP-FPM (聊天室 PHP)
│          │──── /avatars/ ──→ backend/avatars/
└──────────┘
    │
    ▼ :9000
┌──────────┐
│ PHP-FPM  │──→ backend/ + chat/
└──────────┘
    │
    ▼ :3306
┌──────────┐
│  MySQL   │──→ mummories 库 + chat 库
└──────────┘
```

---

## 6. 首次使用

### 6.1 注册账号

1. 打开 http://localhost:8080/blog-auth.html
2. 点击「注册」
3. 填写用户名、邮箱、密码
4. 注册成功后自动登录

### 6.2 登录后台管理

1. 打开 http://localhost:8080/admin.html
2. 输入 `.env` 中设置的 `HOMEPAGE_API_KEY`
3. 可管理文章、说说、用户、站点设置

### 6.3 聊天室

1. 打开 http://localhost:8080/chat/chat.php
2. 首次访问会进入安装向导，按提示完成配置：
   - 数据库地址填 `db`
   - 数据库名填 `chat`
   - 用户名 `root`，密码为你设置的 `DB_PASSWORD`
3. 注册聊天室账号即可使用

### 6.4 个性化设置

登录博客后，在主页侧栏可以：
- 点击头像上传自定义头像
- 编辑「关于我」简介
- 编辑技能标签
- 设置隐私选项（文章/说说可见性）
- 管理快捷入口

---

## 7. 常用命令

```bash
# 启动所有服务
docker compose up -d

# 停止所有服务
docker compose down

# 重启服务
docker compose restart

# 仅重启后端（修改 PHP 代码后）
docker compose restart app

# 查看日志
docker compose logs -f

# 查看某个服务日志
docker compose logs -f app

# 重建镜像（修改 Dockerfile 后）
docker compose up -d --build

# 进入 PHP 容器调试
docker compose exec app bash

# 进入 MySQL 容器
docker compose exec db mysql -u root -p mummories

# 备份数据库
docker compose exec db mysqldump -u root -p mummories > backup.sql
docker compose exec db mysqldump -u root -p chat >> backup.sql

# 恢复数据库
docker compose exec -T db mysql -u root -p mummories < backup.sql
```

---

## 8. 常见问题

### Q: 启动后访问 8080 白屏？

检查服务状态：

```bash
docker compose ps
```

如果 `db` 服务状态不是 `healthy`，等待数据库初始化完成（首次约 30-60 秒）。

### Q: 数据库连接失败？

确认 `.env` 和 `chat/.env` 中的密码一致，然后重启：

```bash
docker compose down
docker compose up -d
```

### Q: 如何修改端口？

编辑 `.env`：

```ini
HTTP_PORT=80
PHPMYADMIN_PORT=9999
```

然后 `docker compose up -d`。

### Q: 头像上传失败？

检查目录权限：

```bash
docker compose exec app chown -R www-data:www-data /var/www/backend/avatars
docker compose exec app chmod 755 /var/www/backend/avatars
```

### Q: 如何完全重置？

```bash
# 停止并删除容器和数据卷（会清除所有数据！）
docker compose down -v

# 重新启动
docker compose up -d --build
```

### Q: 生产环境安全建议？

1. **修改默认密码**：使用强密码（至少 16 位，含大小写字母+数字+符号）
2. **关闭 phpMyAdmin**：注释掉 `docker-compose.yml` 中的 phpmyadmin 服务，或限制访问 IP
3. **启用 HTTPS**：通过宿主机 Nginx + Certbot 配置 SSL
4. **定期备份**：设置 cron 定时备份数据库
5. **更新镜像**：定期 `docker compose pull` 更新基础镜像

---

## 项目结构速查

```
Mummories/
├── frontend/              # 前端静态文件
│   ├── index.html         # 博客主页
│   ├── app.js             # 核心 JS 逻辑
│   ├── style.css          # 全局样式
│   ├── articles.html      # 文章列表/详情
│   ├── moments.html       # 说说/动态
│   ├── friends.html       # 好友列表
│   ├── profile.html       # 个人主页
│   ├── blog-auth.html     # 登录/注册
│   ├── admin.html         # 后台管理
│   ├── chatroom.html      # 聊天室入口
│   ├── creative.html      # 创意作品
│   ├── ui.html            # UI 组件库
│   └── projects.html      # 项目管理
├── backend/               # PHP 后端
│   ├── homepage_api.php   # 博客 REST API
│   ├── community_api.php  # 社区 API（文章/说说/评论/点赞）
│   └── Dockerfile         # PHP-FPM 镜像
├── chat/                  # 聊天室模块
│   ├── chat.php           # 聊天室主页
│   ├── api.php            # 聊天 API
│   ├── config.php         # 聊天室配置
│   └── ...                # 其他聊天功能文件
├── nginx/                 # Web 服务器配置
│   └── default.conf       # Nginx 配置
├── db/                    # 数据库
│   ├── init.sql           # 博客库初始化
│   └── 02_chat_init.sql   # 聊天库初始化
├── docker-compose.yml     # Docker 编排
├── .env.example           # 环境变量模板
├── start.bat              # Windows 一键启动
└── README.md              # 项目说明
```
