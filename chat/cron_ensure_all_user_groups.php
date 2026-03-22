<?php
// 确保全员群聊存在并包含所有用户的定时脚本

// 设置执行时间限制
set_time_limit(300);

// 包含必要的文件
require_once 'config.php';
require_once 'db.php';
require_once 'Group.php';

// 检查是否启用了全员群聊功能
$create_all_group = getConfig('Create_a_group_chat_for_all_members', false);
if (!$create_all_group) {
    error_log('全员群聊功能未启用，脚本终止');
    exit;
}

// 创建Group实例
$group = new Group($conn);

// 确保存在管理员用户
$stmt = $conn->prepare("SELECT id FROM users WHERE is_admin = TRUE ORDER BY id ASC LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();

if (!$admin) {
    error_log('没有管理员用户，无法创建全员群聊');
    exit;
}

// 确保全员群聊存在并包含所有用户
$success = $group->ensureAllUserGroups($admin['id']);

if ($success) {
    error_log('成功确保全员群聊存在并包含所有用户');
} else {
    error_log('确保全员群聊存在并包含所有用户失败');
}

// 关闭数据库连接
$conn = null;
?>