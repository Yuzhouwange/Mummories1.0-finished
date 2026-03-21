<?php
// 添加ip_address字段到users表

// 包含必要的文件
require_once 'config.php';
require_once 'db.php';

try {
    // 检查users表是否已经有ip_address字段
    $stmt = $conn->prepare("SHOW COLUMNS FROM users LIKE 'ip_address'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // 添加ip_address字段
        $conn->exec("ALTER TABLE users ADD COLUMN ip_address VARCHAR(50) NOT NULL DEFAULT '' AFTER created_at");
        echo "成功添加ip_address字段到users表<br>";
        
        // 添加索引以提高查询性能
        $conn->exec("CREATE INDEX idx_users_ip_address ON users(ip_address)");
        echo "成功创建idx_users_ip_address索引<br>";
    } else {
        echo "users表已经有ip_address字段<br>";
    }
} catch (PDOException $e) {
    echo "错误：" . $e->getMessage();
}

// 关闭数据库连接
$conn = null;
?>