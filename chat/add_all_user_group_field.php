<?php
// 包含数据库连接文件
require_once 'db.php';

try {
    // SQL语句：添加all_user_group字段到groups表
    $sql = "ALTER TABLE groups ADD COLUMN all_user_group INT DEFAULT 0 AFTER owner_id";
    $conn->exec($sql);
    echo "成功添加all_user_group字段到groups表<br>";
    
    // SQL语句：创建索引
    $sql = "CREATE INDEX idx_groups_all_user_group ON groups(all_user_group)";
    $conn->exec($sql);
    echo "成功创建索引idx_groups_all_user_group<br>";
    
    echo "所有操作执行完成！";
} catch (PDOException $e) {
    echo "错误：" . $e->getMessage();
}

// 关闭数据库连接
$conn = null;
?>