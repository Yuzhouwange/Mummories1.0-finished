#!/bin/bash
set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

print_banner() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║     Mummories 个人博客 - 一键部署        ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
    echo ""
}

ok()   { echo -e "      ${GREEN}$1${NC}"; }
warn() { echo -e "  ${YELLOW}[!] $1${NC}"; }
fail() { echo -e "  ${RED}[✗] $1${NC}"; exit 1; }
info() { echo -e "  ${BLUE}[*] $1${NC}"; }

print_banner
cd "$(dirname "$0")"

# ========== 1. 检测 Docker ==========
echo -e "[1/6] 检测 Docker 环境..."

# --- 检测并自动安装 Docker ---
if ! command -v docker &>/dev/null; then
    warn "未检测到 Docker，正在自动安装..."

    # 检测操作系统
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_ID="$ID"
    elif command -v sw_vers &>/dev/null; then
        OS_ID="macos"
    else
        OS_ID="unknown"
    fi

    case "$OS_ID" in
        ubuntu|debian|linuxmint|pop)
            info "检测到 $OS_ID，使用 apt 安装 Docker..."
            sudo apt-get update -y
            sudo apt-get install -y ca-certificates curl gnupg lsb-release
            sudo install -m 0755 -d /etc/apt/keyrings
            curl -fsSL "https://download.docker.com/linux/$OS_ID/gpg" | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg 2>/dev/null || \
            curl -fsSL "https://download.docker.com/linux/ubuntu/gpg" | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
            sudo chmod a+r /etc/apt/keyrings/docker.gpg
            CODENAME=$(lsb_release -cs 2>/dev/null || echo "jammy")
            REPO_ID="$OS_ID"
            [ "$OS_ID" = "linuxmint" ] || [ "$OS_ID" = "pop" ] && REPO_ID="ubuntu"
            echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$REPO_ID $CODENAME stable" | \
                sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
            sudo apt-get update -y
            sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            ;;
        centos|rhel|fedora|rocky|almalinux)
            info "检测到 $OS_ID，使用 yum/dnf 安装 Docker..."
            if command -v dnf &>/dev/null; then
                PKG_MGR="dnf"
            else
                PKG_MGR="yum"
            fi
            sudo $PKG_MGR install -y yum-utils
            REPO_ID="$OS_ID"
            [ "$OS_ID" = "rocky" ] || [ "$OS_ID" = "almalinux" ] && REPO_ID="centos"
            sudo yum-config-manager --add-repo "https://download.docker.com/linux/$REPO_ID/docker-ce.repo" 2>/dev/null || \
            sudo $PKG_MGR config-manager --add-repo "https://download.docker.com/linux/centos/docker-ce.repo"
            sudo $PKG_MGR install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
            ;;
        arch|manjaro)
            info "检测到 $OS_ID，使用 pacman 安装 Docker..."
            sudo pacman -Sy --noconfirm docker docker-compose
            ;;
        macos)
            warn "macOS 需要手动安装 Docker Desktop"
            echo -e "      请下载并安装: https://docs.docker.com/desktop/install/mac-install/"
            if command -v brew &>/dev/null; then
                info "检测到 Homebrew，尝试用 brew 安装..."
                brew install --cask docker
                info "请启动 Docker Desktop 应用后重新运行此脚本"
            fi
            exit 1
            ;;
        *)
            warn "未知系统 ($OS_ID)，尝试使用官方一键安装脚本..."
            curl -fsSL https://get.docker.com | sudo sh
            ;;
    esac

    if ! command -v docker &>/dev/null; then
        fail "Docker 安装失败，请手动安装: https://docs.docker.com/engine/install/"
    fi
    ok "Docker 安装成功！"

    # 将当前用户加入 docker 组（避免每次 sudo）
    if [ "$(id -u)" -ne 0 ] && ! groups | grep -q docker; then
        sudo usermod -aG docker "$USER" 2>/dev/null || true
        warn "已将用户加入 docker 组，如遇权限问题请重新登录"
    fi
fi

DOCKER_VER=$(docker --version | awk '{print $3}' | tr -d ',')
ok "Docker $DOCKER_VER - OK"

# --- 检测 Docker Compose ---
if ! docker compose version &>/dev/null; then
    warn "未检测到 Docker Compose 插件，正在自动安装..."
    COMPOSE_VERSION="v2.27.0"
    ARCH=$(uname -m)
    [ "$ARCH" = "x86_64" ] && ARCH="x86_64"
    [ "$ARCH" = "aarch64" ] && ARCH="aarch64"
    sudo mkdir -p /usr/local/lib/docker/cli-plugins
    sudo curl -fsSL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-${ARCH}" \
        -o /usr/local/lib/docker/cli-plugins/docker-compose
    sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose

    if ! docker compose version &>/dev/null; then
        fail "Docker Compose 安装失败，请手动安装"
    fi
    ok "Docker Compose 安装成功！"
else
    ok "Docker Compose - OK"
fi

# --- 检测并自动启动 Docker 守护进程 ---
if ! docker info &>/dev/null; then
    warn "Docker 守护进程未运行，正在自动启动..."

    # 尝试多种方式启动
    if command -v systemctl &>/dev/null; then
        sudo systemctl start docker
        sudo systemctl enable docker 2>/dev/null || true
    elif command -v service &>/dev/null; then
        sudo service docker start
    else
        sudo dockerd &>/dev/null &
    fi

    # 等待守护进程就绪
    DAEMON_WAIT=0
    while [ $DAEMON_WAIT -lt 60 ]; do
        if docker info &>/dev/null; then
            break
        fi
        DAEMON_WAIT=$((DAEMON_WAIT + 3))
        echo "      等待 Docker 守护进程启动... (${DAEMON_WAIT}s / 60s)"
        sleep 3
    done

    if ! docker info &>/dev/null; then
        fail "Docker 守护进程启动失败\n      请手动启动: sudo systemctl start docker"
    fi
    ok "Docker 守护进程 - 已自动启动"
else
    ok "Docker 守护进程 - 运行中"
fi

# ========== 2. 检测端口 ==========
echo ""
echo -e "[2/6] 检测端口占用..."

HTTP_PORT=8080
PMA_PORT=9888

if [ -f ".env" ]; then
    HTTP_PORT=$(grep -E "^HTTP_PORT=" .env | cut -d= -f2 || echo "8080")
    PMA_PORT=$(grep -E "^PHPMYADMIN_PORT=" .env | cut -d= -f2 || echo "9888")
    [ -z "$HTTP_PORT" ] && HTTP_PORT=8080
    [ -z "$PMA_PORT" ] && PMA_PORT=9888
fi

if ss -tlnp 2>/dev/null | grep -q ":${HTTP_PORT} " || \
   netstat -tlnp 2>/dev/null | grep -q ":${HTTP_PORT} "; then
    fail "端口 ${HTTP_PORT} 已被占用\n      请修改 .env 中的 HTTP_PORT 或关闭占用程序"
fi
ok "端口 ${HTTP_PORT} - 可用"

if ss -tlnp 2>/dev/null | grep -q ":${PMA_PORT} " || \
   netstat -tlnp 2>/dev/null | grep -q ":${PMA_PORT} "; then
    warn "端口 ${PMA_PORT} 已被占用（phpMyAdmin 可能无法启动，不影响博客）"
else
    ok "端口 ${PMA_PORT} - 可用"
fi

# ========== 3. 配置环境变量 ==========
echo ""
echo -e "[3/6] 检测配置文件..."

generate_password() {
    local length=${1:-16}
    if command -v openssl &>/dev/null; then
        openssl rand -base64 $((length * 2)) | tr -dc 'A-Za-z0-9' | head -c "$length"
    else
        cat /dev/urandom | tr -dc 'A-Za-z0-9' | head -c "$length"
    fi
}

if [ ! -f ".env" ]; then
    info "未找到 .env，正在自动生成..."
    
    DB_PWD=$(generate_password 16)
    API_KEY=$(generate_password 20)
    
    cat > .env << EOF
# Mummories 环境配置 - 自动生成
DB_HOST=db
DB_NAME=mummories
DB_USER=root
DB_PASSWORD=${DB_PWD}
APP_NAME=Mummories
APP_URL=http://localhost:8080
HTTP_PORT=8080
PHPMYADMIN_PORT=9888
HOMEPAGE_API_KEY=${API_KEY}
EOF
    
    ok ".env 已自动生成（密码已随机生成）"
else
    ok ".env - 已存在"
    DB_PWD=$(grep -E "^DB_PASSWORD=" .env | cut -d= -f2)
    API_KEY=$(grep -E "^HOMEPAGE_API_KEY=" .env | cut -d= -f2)
fi

if [ ! -f "chat/.env" ]; then
    info "未找到 chat/.env，正在自动生成..."
    
    cat > chat/.env << EOF
# 聊天室环境配置 - 自动生成
DB_HOST=db
DB_NAME=chat
DB_USER=root
DB_PASS=${DB_PWD}
DB_PASSWORD=${DB_PWD}
APP_NAME=Mummories
APP_URL=http://localhost:8080/chat
TRUSTED_PROXIES=
HOMEPAGE_API_KEY=${API_KEY}
EOF
    
    ok "chat/.env 已自动生成"
else
    ok "chat/.env - 已存在"
fi

# ========== 4. 检测目录结构 ==========
echo ""
echo -e "[4/6] 检测项目完整性..."

MISSING=0
for f in "frontend/index.html" "backend/homepage_api.php" "backend/Dockerfile" \
         "nginx/default.conf" "db/init.sql" "docker-compose.yml"; do
    if [ ! -f "$f" ]; then
        warn "缺少 $f"
        MISSING=1
    fi
done

if [ "$MISSING" = "1" ]; then
    fail "项目文件不完整，请重新下载"
fi
ok "项目文件完整 - OK"

# 创建运行时目录
mkdir -p backend/avatars backend/uploads
ok "运行时目录 - OK"

# ========== 5. 构建并启动 ==========
echo ""
echo -e "[5/6] 构建并启动服务（首次约需 2-5 分钟）..."
echo ""

info "拉取最新镜像..."
docker compose pull --ignore-buildable 2>/dev/null || true

info "构建并启动..."
docker compose --profile dev up -d --build

if [ $? -ne 0 ]; then
    fail "Docker 启动失败，请检查上方错误信息"
fi

# ========== 6. 等待服务就绪 ==========
echo ""
echo -e "[6/6] 等待服务就绪..."

MAX_WAIT=60
WAITED=0

while [ $WAITED -lt $MAX_WAIT ]; do
    if docker compose exec -T db mysqladmin ping -h localhost -u root -p"${DB_PWD}" &>/dev/null; then
        break
    fi
    WAITED=$((WAITED + 5))
    echo "      等待数据库就绪... (${WAITED}s / ${MAX_WAIT}s)"
    sleep 5
done

if [ $WAITED -ge $MAX_WAIT ]; then
    warn "等待超时，服务可能仍在启动中"
    echo "      请稍后手动访问 http://localhost:${HTTP_PORT}"
else
    # 额外等待 Nginx
    sleep 3
    ok "所有服务已就绪！"
fi

# 显示结果
echo ""
echo -e "${CYAN}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║${GREEN}          部署完成！                       ${CYAN}║${NC}"
echo -e "${CYAN}╠══════════════════════════════════════════╣${NC}"
echo -e "${CYAN}║${NC}                                          ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  博客主页:   http://localhost:${HTTP_PORT}         ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  聊天室:     http://localhost:${HTTP_PORT}/chat    ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  后台管理:   http://localhost:${HTTP_PORT}/admin   ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  phpMyAdmin: http://localhost:${PMA_PORT}         ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}                                          ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}  管理员密钥: ${YELLOW}${API_KEY}${NC}"
echo -e "${CYAN}║${NC}  (密钥保存在 .env 文件中)                 ${CYAN}║${NC}"
echo -e "${CYAN}║${NC}                                          ${CYAN}║${NC}"
echo -e "${CYAN}╚══════════════════════════════════════════╝${NC}"
echo ""
echo -e "  提示: 首次使用请访问博客主页注册账号"
echo -e "        后台管理使用上方管理员密钥登录"
echo ""
