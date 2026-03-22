<?php
// 一键修复 chat.users 表缺少 agreed_to_terms 字段
$host = getenv('DB_HOST') ?: 'db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$dbname = 'chat';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'agreed_to_terms'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN agreed_to_terms BOOLEAN DEFAULT FALSE AFTER is_deleted");
        echo "已添加 agreed_to_terms 字段\n";
    } else {
        echo "agreed_to_terms 字段已存在\n";
    }
} catch (Exception $e) {
    echo "修复失败: ".$e->getMessage()."\n";
    exit(1);
}
echo "修复完成\n";
