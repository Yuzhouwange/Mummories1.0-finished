-- Mummories 个人博客 数据库初始化
SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS mummories DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mummories;

-- 用户表 (博客用户注册/登录)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default_avatar.png',
    bio TEXT DEFAULT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    skills JSON DEFAULT NULL,
    status ENUM('online', 'offline', 'away') DEFAULT 'offline',
    is_admin BOOLEAN DEFAULT FALSE,
    is_deleted BOOLEAN DEFAULT FALSE,
    agreed_to_terms BOOLEAN DEFAULT FALSE,
    ip_address VARCHAR(50) DEFAULT NULL,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 站点资料 (key-value 存储)
CREATE TABLE IF NOT EXISTS homepage_profile (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 项目展示
CREATE TABLE IF NOT EXISTS homepage_projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    url VARCHAR(500) DEFAULT '#',
    icon VARCHAR(50) DEFAULT 'default',
    tags VARCHAR(500) DEFAULT '',
    sort_order INT DEFAULT 0,
    visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 联系留言
CREATE TABLE IF NOT EXISTS homepage_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    ip VARCHAR(50) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 自定义页面
CREATE TABLE IF NOT EXISTS homepage_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'file',
    content LONGTEXT,
    sort_order INT DEFAULT 0,
    visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 限流表
CREATE TABLE IF NOT EXISTS homepage_rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(50) NOT NULL,
    resource VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_resource (ip, resource),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户快捷入口
CREATE TABLE IF NOT EXISTS user_shortcuts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL DEFAULT '',
    icon VARCHAR(50) DEFAULT 'link',
    type ENUM('link','image','video','audio','text','file') NOT NULL DEFAULT 'link',
    content TEXT,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    mime_type VARCHAR(100),
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户隐私设置
CREATE TABLE IF NOT EXISTS user_privacy (
    user_id INT PRIMARY KEY,
    show_email TINYINT(1) DEFAULT 1,
    show_skills TINYINT(1) DEFAULT 1,
    show_bio TINYINT(1) DEFAULT 1,
    show_contact TINYINT(1) DEFAULT 1,
    allow_profile_view TINYINT(1) DEFAULT 1,
    show_articles TINYINT(1) DEFAULT 1,
    show_moments TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 媒体内容库（UI组件库 & 创意作品共用）
CREATE TABLE IF NOT EXISTS media_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('ui','creative') NOT NULL DEFAULT 'ui',
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('image','video','audio','text','file','link') NOT NULL,
    url VARCHAR(1000) DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    file_name VARCHAR(255) DEFAULT NULL,
    file_size INT DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    content LONGTEXT DEFAULT NULL,
    tags VARCHAR(500) DEFAULT '',
    sort_order INT DEFAULT 0,
    visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 文章分类
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(500) DEFAULT '',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 标签
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 文章
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    cover VARCHAR(500) DEFAULT '',
    category_id INT DEFAULT NULL,
    tags VARCHAR(500) DEFAULT '',
    status ENUM('published','draft','private') DEFAULT 'draft',
    is_top TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    like_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_category (category_id),
    INDEX idx_top_created (is_top, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 说说
CREATE TABLE IF NOT EXISTS moments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    content TEXT NOT NULL,
    images VARCHAR(2000) DEFAULT '',
    is_top TINYINT(1) DEFAULT 0,
    status ENUM('public','private') DEFAULT 'public',
    like_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论（通用，支持文章和说说）
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('article','moment') NOT NULL,
    target_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    nickname VARCHAR(100) DEFAULT '匿名',
    avatar VARCHAR(255) DEFAULT '',
    content TEXT NOT NULL,
    parent_id INT DEFAULT NULL,
    reply_to_id INT DEFAULT NULL,
    ip_address VARCHAR(50) DEFAULT '',
    status ENUM('approved','pending','rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target (target_type, target_id),
    INDEX idx_parent (parent_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 点赞（通用）
CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('article','moment','comment') NOT NULL,
    target_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(50) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_like (target_type, target_id, user_id),
    INDEX idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户在线记录
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) DEFAULT '',
    ip_address VARCHAR(50) DEFAULT '',
    browser VARCHAR(200) DEFAULT '',
    os VARCHAR(100) DEFAULT '',
    location VARCHAR(200) DEFAULT '',
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL DEFAULT NULL,
    is_online TINYINT(1) DEFAULT 1,
    INDEX idx_user (user_id),
    INDEX idx_online (is_online)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 操作日志
CREATE TABLE IF NOT EXISTS operation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(50) DEFAULT '',
    action VARCHAR(100) NOT NULL,
    detail VARCHAR(500) DEFAULT '',
    ip_address VARCHAR(50) DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认站点资料
INSERT INTO homepage_profile (`key`, `value`) VALUES
    ('name', 'Mummories'),
    ('bio', '欢迎来到我的个人博客'),
    ('email', 'admin@mummories.cn'),
    ('skills', 'HTML,CSS,JavaScript,PHP,Vue,Docker')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

-- Bot 助手用户
INSERT INTO users (username, email, password, display_name, bio, skills, avatar, is_admin, agreed_to_terms)
SELECT 'Mummories助手', 'bot@mummories.local',
       '$2y$10$botplaceholderpasswordhashnotloginable000000000000000000',
       'Mummories助手',
       '我是 Mummories 智能助手 🤖，随时为你提供帮助！',
       '["AI","聊天机器人","博客助手"]',
       'https://ui-avatars.com/api/?name=Bot&background=6366f1&color=fff&size=128&bold=true',
       0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='bot@mummories.local');
