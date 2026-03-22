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
    require_once 'Message.php';
    require_once 'Group.php';

    // 检查用户是否登录
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => '用户未登录']);
        exit;
    }

    // 检查是否是POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => '无效的请求方法']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $chat_type = isset($_POST['chat_type']) ? $_POST['chat_type'] : '';
    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;

    // 验证数据
    if (!$chat_type || !$message_id) {
        echo json_encode(['success' => false, 'message' => '缺少必要参数']);
        exit;
    }

    // 检查数据库连接
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
        exit;
    }

    $result = [];

    if ($chat_type === 'friend') {
        // 撤回好友消息
        $message = new Message($conn);
        $result = $message->recallMessage($message_id, $user_id);
    } elseif ($chat_type === 'group') {
        // 撤回群聊消息
        $group = new Group($conn);
        $result = $group->recallGroupMessage($message_id, $user_id);
    } else {
        $result = ['success' => false, 'message' => '无效的聊天类型'];
    }

    echo json_encode($result);
} catch (Exception $e) {
    // 捕获所有异常并返回错误信息
    $error_msg = "服务器内部错误: " . $e->getMessage();
    error_log($error_msg);
    echo json_encode(['success' => false, 'message' => $error_msg]);
}
?>