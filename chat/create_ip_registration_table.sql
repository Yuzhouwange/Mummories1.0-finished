-- 创建IP注册记录表
USE chat;

CREATE TABLE IF NOT EXISTS ip_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建索引以提高查询性能
CREATE INDEX idx_ip_registrations_ip_address ON ip_registrations(ip_address);
CREATE INDEX idx_ip_registrations_user_id ON ip_registrations(user_id);
CREATE INDEX idx_ip_registrations_registered_at ON ip_registrations(registered_at);
