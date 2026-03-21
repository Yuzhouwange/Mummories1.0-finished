-- 创建封禁表
USE chat;

CREATE TABLE IF NOT EXISTS bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    banned_by INT NOT NULL,
    reason TEXT NOT NULL,
    ban_duration INT NOT NULL, -- 封禁时长（秒）
    ban_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ban_end TIMESTAMP NULL,
    status ENUM('active', 'expired', 'lifted') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_ban (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建封禁日志表
CREATE TABLE IF NOT EXISTS ban_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ban_id INT NOT NULL,
    action ENUM('ban', 'lift', 'expire') NOT NULL,
    action_by INT NULL,
    action_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ban_id) REFERENCES bans(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建索引以提高查询性能
CREATE INDEX idx_bans_user_id ON bans(user_id);
CREATE INDEX idx_bans_status ON bans(status);
CREATE INDEX idx_bans_ban_end ON bans(ban_end);
CREATE INDEX idx_ban_logs_ban_id ON ban_logs(ban_id);
CREATE INDEX idx_ban_logs_action ON ban_logs(action);