# Mummories 个人博客

基于 Docker 一键部署的个人博客系统，集成聊天室功能。

## 项目结构

```
Mummories/
├── frontend/          # 前端 (HTML/CSS/JS)
│   ├── index.html     # 主页
│   ├── app.js         # 核心逻辑
│   ├── style.css      # 样式
│   ├── articles.html  # 文章
│   ├── moments.html   # 说说
│   ├── friends.html   # 好友列表
│   ├── profile.html   # 个人主页
│   ├── admin.html     # 后台管理
│   ├── blog-auth.html # 登录/注册
│   └── ...
├── backend/           # 后端 (PHP API)
│   ├── homepage_api.php    # 博客 REST API
│   ├── community_api.php   # 社区 API (文章/说说/评论)
│   └── Dockerfile
├── chat/              # 聊天室 (PHP)
│   ├── chat.php       # 聊天室主页
│   ├── api.php        # 聊天 API
│   └── ...
├── nginx/             # Nginx 配置
│   └── default.conf
├── db/                # 数据库初始化
│   ├── init.sql       # 博客数据库
│   └── 02_chat_init.sql  # 聊天室数据库
├── docker-compose.yml
├── .env.example       # 环境变量模板
├── start.bat          # Windows 一键启动
└── .gitignore
```

## 快速开始

### 1. 配置环境变量

```bash
# 复制环境配置模板
cp .env.example .env
cp chat/.env.example chat/.env

# 编辑 .env，修改以下配置：
# - DB_PASSWORD: 数据库密码
# - HOMEPAGE_API_KEY: 管理员密钥
```

### 2. 启动服务

**Windows：**
```bash
双击 start.bat
```

**Linux/Mac：**
```bash
docker compose up -d --build
```

### 3. 访问

| 服务 | 地址 | 说明 |
|------|------|------|
| 博客主页 | http://localhost:8080 | 前端页面 |
| 聊天室 | http://localhost:8080/chat | 实时聊天 |
| 后台管理 | http://localhost:8080/admin.html | 需要 API Key |
| phpMyAdmin | http://localhost:9888 | 数据库管理 |

## 功能特性

- **个人博客**：文章发布、说说动态、技能展示、项目管理
- **用户系统**：注册/登录、个人资料、头像上传、隐私设置
- **社区互动**：评论、点赞、好友列表
- **聊天室**：实时聊天、群组、好友系统、文件分享
- **后台管理**：内容管理、用户管理、站点统计
- **响应式设计**：Glassmorphism 风格 UI

## 技术栈

- **前端**：HTML5 + CSS3 (Glassmorphism) + Vanilla JavaScript
- **后端**：PHP 8.2 (REST API)
- **数据库**：MySQL 5.7
- **部署**：Docker + Nginx + PHP-FPM

## 许可证

MIT License
