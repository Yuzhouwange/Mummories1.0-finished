<?php
require_once 'db.php';

// 更新群聊名称：全员群聊-1 改为 世界大厅-1
try {
    $stmt = $conn->prepare("UPDATE groups SET name = ? WHERE id = ?");
    $stmt->execute(['世界大厅-1', 4]);
    echo "群聊名称已更新\n";
    
    // 验证更新结果
    $stmt = $conn->prepare("SELECT id, name, all_user_group FROM groups WHERE id = ?");
    $stmt->execute([4]);
    $group = $stmt->fetch();
    echo "更新后的群聊：{$group['id']} | {$group['name']} | {$group['all_user_group']}\n";
    
} catch (PDOException $e) {
    echo "更新失败：" . $e->getMessage() . "\n";
}

// 关闭连接
$conn = null;
?>