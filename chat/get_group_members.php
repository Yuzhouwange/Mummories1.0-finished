<?php
// 启用错误报告以便调试
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置错误日志
ini_set('error_log', 'error.log');

// 开始会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    require_once 'config.php';
    require_once 'db.php';
    require_once 'Group.php';
    
    // 检查用户是否登录
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => '用户未登录']);
        exit;
    }
    
    // 检查是否是GET请求
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['success' => false, 'message' => '无效的请求方法']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    
    // 验证数据
    if ($group_id <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的群聊ID']);
        exit;
    }
    
    // 检查数据库连接
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }
    
    // 创建Group实例
    $group = new Group($conn);
    
    // 修复越权漏洞：检查用户是否是该群聊成员
    if (!$group->isUserInGroup($group_id, $user_id)) {
        echo json_encode(['success' => false, 'message' => '您不是该群聊成员，无权查看成员列表 ❌']);
        exit;
    }
    
    // 获取群聊成员列表
    $members = $group->getGroupMembers($group_id);
    
    // 处理成员数据，只返回需要的字段
    $result = [];
    foreach ($members as $member) {
        $result[] = [
            'id' => $member['user_id'],
            'username' => $member['username'],
            'nickname' => $member['nickname'] ?? '',
            'avatar' => $member['avatar'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'members' => $result
    ]);
} catch (Exception $e) {
    // 捕获所有异常并返回错误信息
    $error_msg = "服务器内部错误: " . $e->getMessage();
    error_log($error_msg);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}