<?php
require_once 'config.php';
// 检查系统维护模式
if (getConfig('System_Maintenance', 0) == 1) {
    $maintenance_page = getConfig('System_Maintenance_page', 'cloudflare_error.html');
    include 'Maintenance/' . $maintenance_page;
    exit;
}

require_once 'db.php';
require_once 'User.php';
require_once 'Friend.php';
require_once 'Message.php';
require_once 'Group.php';

// 检测设备类型
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $mobileAgents = array('Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 'Windows Phone', 'Mobile', 'Opera Mini', 'Fennec', 'IEMobile');
    foreach ($mobileAgents as $agent) {
        if (stripos($userAgent, $agent) !== false) {
            return true;
        }
    }
    return false;
}

// 如果是电脑设备，跳转到桌面端聊天页面
if (!isMobileDevice()) {
    header('Location: Newchat.php');
    exit;
}

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 检查是否启用了全员群聊功能，如果启用了，确保全员群聊存在并包含所有用户
$create_all_group = getConfig('Create_a_group_chat_for_all_members', false);
if ($create_all_group) {
    // 检查是否需要添加all_user_group字段
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM groups LIKE 'all_user_group'");
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if (!$column_exists) {
            // 添加all_user_group字段
            $conn->exec("ALTER TABLE groups ADD COLUMN all_user_group INT DEFAULT 0 AFTER owner_id");
            error_log("Added all_user_group column to groups table");
        }
    } catch (PDOException $e) {
        error_log("Error checking/adding all_user_group column: " . $e->getMessage());
    }
    
    $group = new Group($conn);
    $group->ensureAllUserGroups($_SESSION['user_id']);
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 获取GET参数
$selected_friend_id = isset($_GET['friend_id']) ? intval($_GET['friend_id']) : 0;

// 创建实例
$user = new User($conn);
$friend = new Friend($conn);
$message = new Message($conn);
$group = new Group($conn);

// 获取当前用户信息
$current_user = $user->getUserById($user_id);

// 获取好友列表
$friends = $friend->getFriends($user_id);

// 获取好友申请列表
$friend_requests = $friend->getPendingRequests($user_id);

// 获取群聊列表
$groups = $group->getUserGroups($user_id);

// 获取未读好友申请数量
$unread_requests_count = count($friend_requests);

// 获取未读消息计数
$unread_counts = [];
try {
    // 确保unread_messages表存在
    $stmt = $conn->prepare("SHOW TABLES LIKE 'unread_messages'");
    $stmt->execute();
    if ($stmt->fetch()) {
        $stmt = $conn->prepare("SELECT * FROM unread_messages WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $unread_records = $stmt->fetchAll();
        
        foreach ($unread_records as $record) {
            $key = $record['chat_type'] . '_' . $record['chat_id'];
            $unread_counts[$key] = $record['count'];
        }
    }
} catch (PDOException $e) {
    error_log("Get unread counts error: " . $e->getMessage());
}

// 获取聊天类型和选中的聊天对象
$chat_type = isset($_GET['chat_type']) ? $_GET['chat_type'] : 'friend'; // 'friend' 或 'group'
$selected_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selected_friend = null;
$selected_group = null;

// 处理选中的聊天对象
if ($selected_id) {
    if ($chat_type === 'friend') {
        $selected_friend = $user->getUserById($selected_id);
    } elseif ($chat_type === 'group') {
        $selected_group = $group->getGroupInfo($selected_id);
    }
}

// 获取聊天记录
$chat_history = [];
if ($selected_id) {
    if ($chat_type === 'friend') {
        $chat_history = $message->getChatHistory($user_id, $selected_id);
    } elseif ($chat_type === 'group') {
        $chat_history = $group->getGroupMessages($selected_id, $user_id);
    }
}

// 检查用户是否被封禁
$ban_info = $user->isBanned($user_id);

// 检查用户是否同意协议
$agreed_to_terms = $user->hasAgreedToTerms($user_id);

// 获取待处理的好友请求
$pending_requests = $friend->getPendingRequests($user_id);

// 获取用户IP地址
// 使用config.php中定义的getUserIP()函数
$user_ip = getUserIP();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mummories - 移动端</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
            background: white;
            flex-direction: column;
        }
        
        /* 顶部导航栏 */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .top-nav h1 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-status {
            font-size: 12px;
        }
        
        .menu-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
        }
        
        /* 菜单面板 */
        .menu-panel {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .menu-panel.open {
            right: 0;
        }
        
        .menu-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        
        .menu-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: white;
            color: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 32px;
            margin: 0 auto 15px;
        }
        
        .menu-username {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .menu-email {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .menu-ip {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .menu-items {
            padding: 20px;
        }
        
        .menu-item {
            display: block;
            width: 100%;
            padding: 15px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
        }
        
        .menu-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .menu-item-danger {
            background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
        }
        
        .menu-item-danger:hover {
            box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
        }
        
        /* 遮罩层 */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
        }
        
        .overlay.open {
            opacity: 1;
            visibility: visible;
        }
        
        /* 主内容区域 */
        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* 好友列表 */
        .friends-list {
            width: 100%;
            background: white;
            overflow-y: auto;
            border-right: 1px solid #e0e0e0;
        }
        
        .friends-header {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .friend-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .friend-item:hover {
            background: #f8f9fa;
        }
        
        .friend-item.active {
            background: #e8f0fe;
        }
        
        .friend-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            position: relative;
            margin-right: 12px;
        }
        
        .status-indicator {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .status-indicator.online {
            background: #4caf50;
        }
        
        .status-indicator.offline {
            background: #ffa502;
        }
        
        .status-indicator.away {
            background: #ffa502;
        }
        
        .friend-info {
            flex: 1;
        }
        
        .friend-info h3 {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .friend-info p {
            font-size: 13px;
            color: #666;
        }
        
        .unread-count {
            background: #ff4757;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* 聊天区域 */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }
        
        .chat-header {
            padding: 15px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
        }
        
        .chat-header .friend-avatar {
            margin-right: 12px;
        }
        
        .chat-header-info h2 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }
        
        .chat-header-info p {
            font-size: 13px;
            color: #666;
        }
        
        .messages-container {
            flex: 1;
            padding: 20px;
            padding-bottom: 190px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 70%;
            margin-bottom: 15px;
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message.received {
            align-self: flex-start;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin: 0 8px;
        }
        
        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            word-break: break-word;
        }
        
        .message.sent .message-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .message-text {
            font-size: 14px;
            line-height: 1.4;
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 5px;
            text-align: right;
        }
        
        .message.sent .message-time {
            text-align: right;
        }
        
        .message.received .message-time {
            text-align: left;
        }
        
        /* 输入区域 */
        .input-area {
            padding: 15px;
            background: white;
            border-top: 1px solid #e0e0e0;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        
        #message-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .input-wrapper {
            flex: 1;
            position: relative;
        }
        
        #message-input {
            width: 100%;
            min-height: 40px;
            max-height: 120px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            font-size: 14px;
            resize: none;
            outline: none;
            transition: all 0.2s ease;
        }
        
        #message-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .input-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-icon {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        
        .btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        #file-input {
            display: none;
        }
        
        /* 链接样式 */
        .message-link {
            color: #3498db;
            text-decoration: none;
            border-bottom: 1px dashed #3498db;
            transition: all 0.2s ease;
        }
        
        .message-link:hover {
            color: #2980b9;
            border-bottom: 1px solid #2980b9;
        }
        
        /* 自定义音频播放器 */
        .custom-audio-player {
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 8px;
            padding: 8px 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            max-width: 300px;
            width: 100%;
            box-sizing: border-box;
        }
        
        .audio-play-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s ease;
            margin-right: 12px;
        }
        
        .audio-play-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .audio-play-btn.paused {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        
        .audio-progress-container {
            flex: 1;
            margin: 0 12px;
            position: relative;
        }
        
        .audio-progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
            cursor: pointer;
            overflow: hidden;
        }
        
        .audio-progress {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 3px;
            transition: width 0.1s ease;
            position: relative;
        }
        
        .audio-progress::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .audio-time {
            font-size: 12px;
            color: #666;
            min-width: 70px;
            text-align: center;
        }
        
        .audio-duration {
            font-size: 12px;
            color: #666;
            min-width: 40px;
            text-align: right;
        }
        
        /* 图片样式 */
        .message-content img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .message-content img:hover {
            transform: scale(1.05);
        }
        
        /* 图片查看器 */
        .image-viewer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            cursor: zoom-out;
            touch-action: none; /* 禁用浏览器默认触摸行为 */
            overflow: hidden;
        }
        
        .image-viewer.active {
            display: flex;
        }
        
        .image-viewer-content {
            position: absolute;
            top: 50%;
            left: 50%;
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
            border-radius: 8px;
            transform-origin: center;
            transform: translate(-50%, -50%) scale(1);
            transition: transform 0.1s ease;
            touch-action: none; /* 禁用浏览器默认触摸行为 */
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .main-content {
                flex-direction: column;
            }
            
            .friends-list {
                width: 100%;
                height: 100%;
            }
            
            .friends-list.hidden {
                display: none;
            }
            
            .chat-area {
                display: none;
                height: 100%;
            }
            
            .chat-area.active {
                display: flex;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['feedback_received']) && $_SESSION['feedback_received']): ?>
        <div style="position: fixed; top: 20px; right: 20px; background: #4caf50; color: white; padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1000; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            您的反馈已收到，正在修复中，感谢您的反馈！
        </div>
        <?php unset($_SESSION['feedback_received']); ?>
    <?php endif; ?>
    
    <!-- 封禁提示弹窗 -->
    <div id="ban-notification-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 5000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; text-align: center;">
            <h2 style="color: #d32f2f; margin-bottom: 20px; font-size: 24px;">账号已被封禁</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 16px;">您的账号已被封禁，即将退出登录</p>
            <p id="ban-reason" style="color: #333; margin-bottom: 20px; font-weight: 500;"></p>
            <p id="ban-countdown" style="color: #d32f2f; font-size: 36px; font-weight: bold; margin-bottom: 20px;">10</p>
            <p style="color: #999; font-size: 14px;">如有疑问请联系管理员</p>
        </div>
    </div>
    
    <!-- 协议同意提示弹窗 -->
    <div id="terms-agreement-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 5000; flex-direction: column; align-items: center; justify-content: center; overflow: auto;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; margin: 20px;">
            <h2 style="color: #333; margin-bottom: 20px; font-size: 24px; text-align: center;">用户协议</h2>
            <div style="max-height: 400px; overflow-y: auto; margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <p style="color: #666; line-height: 1.8; font-size: 16px;">
                    <strong>请严格遵守当地法律法规，若出现违规发言或违规文件一经发现将对您的账号进行封禁（最低1天）无上限。</strong>
                    <br><br>
                    作为Mummories的用户，您需要遵守以下规则：
                    <br><br>
                    1. 不得发布违反国家法律法规的内容
                    <br>
                    2. 不得发布暴力、色情、恐怖等不良信息
                    <br>
                    3. 不得发布侵犯他人隐私的内容
                    <br>
                    4. 不得发布虚假信息或谣言
                    <br>
                    5. 不得恶意攻击其他用户
                    <br>
                    6. 不得发布垃圾广告
                    <br>
                    7. 不得发送违规文件
                    <br><br>
                    违反上述规则的用户，管理员有权对其账号进行封禁处理，封禁时长根据违规情节轻重而定，最低1天，无上限。
                    <br><br>
                    请您自觉遵守以上规则，共同维护良好的聊天环境。
                </p>
            </div>
            <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
                <button id="agree-terms-btn" style="padding: 12px 40px; background: #4CAF50; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background-color 0.3s;">
                    同意
                </button>
                <button id="disagree-terms-btn" style="padding: 12px 40px; background: #f44336; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background-color 0.3s;">
                    不同意并注销账号
                </button>
            </div>
        </div>
    </div>
    
    <!-- 图片查看器 -->
    <div class="image-viewer" id="imageViewer" style="z-index: 9999;">
        <img class="image-viewer-content" id="imageViewerContent" src="" alt="查看大图">
        <div id="imageViewerClose" style="position: fixed; top: 10px; right: 10px; background: red; color: white; font-size: 24px; cursor: pointer; padding: 5px 10px; z-index: 10000; user-select: none;">×</div>
    </div>
    <div class="chat-container">
    <!-- 顶部导航栏 -->
    <div class="top-nav">
        <h1>Mummories</h1>
        <div class="user-info">
            <div class="user-avatar">
                <?php if (!empty($current_user['avatar'])): ?>
                    <img src="<?php echo $current_user['avatar']; ?>" alt="<?php echo $username; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <?php echo substr($username, 0, 2); ?>
                <?php endif; ?>
            </div>
            <span class="user-status">在线</span>
            <button class="menu-btn" onclick="toggleMenu()">⋮</button>
        </div>
    </div>
    
    <!-- 菜单面板 -->
    <div class="menu-panel" id="menu-panel">
        <div class="menu-header">
            <div class="menu-avatar">
                <?php if (!empty($current_user['avatar'])): ?>
                    <img src="<?php echo $current_user['avatar']; ?>" alt="<?php echo $username; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <?php echo substr($username, 0, 2); ?>
                <?php endif; ?>
            </div>
            <div class="menu-username"><?php echo $username; ?></div>
            <div class="menu-email"><?php echo htmlspecialchars($_SESSION['email']); ?></div>
            <div class="menu-ip">IP地址: <?php echo $user_ip; ?></div>
        </div>
        <div class="menu-items">
            <a href="edit_profile.php" class="menu-item">编辑资料</a>
            <button class="menu-item" onclick="showAddFriendModal()">添加好友</button>
            <button class="menu-item" onclick="showFriendRequests()">
                好友申请
                <?php if ($unread_requests_count > 0): ?>
                    <span style="background: #ff4757; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; margin-left: 5px;"><?php echo $unread_requests_count; ?></span>
                <?php endif; ?>
            </button>
            <button class="menu-item" onclick="showFeedbackModal()">反馈问题</button>
            <button class="menu-item" onclick="showScanLoginModal()">扫码登录PC端</button>
            <a href="logout.php" class="menu-item menu-item-danger">退出登录</a>
        </div>
    </div>
    
    <!-- 遮罩层 -->
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>
    
    <!-- 主内容区域 -->
    <div class="main-content">
        <!-- 好友列表 -->
        <div class="friends-list <?php echo $selected_id ? 'hidden' : ''; ?>">
            <div class="friends-header">
                <input type="text" class="search-input" placeholder="搜索好友..." id="search-input">
            </div>
            
            <!-- 聊天类型切换 -->
            <div style="display: flex; background: white; border-bottom: 1px solid #e0e0e0;">
                <button class="chat-type-btn <?php echo $chat_type === 'friend' ? 'active' : ''; ?>" onclick="switchChatType('friend')" style="flex: 1; padding: 12px; border: none; background: transparent; cursor: pointer; font-size: 14px; font-weight: 600; color: <?php echo $chat_type === 'friend' ? '#667eea' : '#666'; ?>; border-bottom: 2px solid <?php echo $chat_type === 'friend' ? '#667eea' : 'transparent'; ?>">好友</button>
                <button class="chat-type-btn <?php echo $chat_type === 'group' ? 'active' : ''; ?>" onclick="switchChatType('group')" style="flex: 1; padding: 12px; border: none; background: transparent; cursor: pointer; font-size: 14px; font-weight: 600; color: <?php echo $chat_type === 'group' ? '#667eea' : '#666'; ?>; border-bottom: 2px solid <?php echo $chat_type === 'group' ? '#667eea' : 'transparent'; ?>">群聊</button>
            </div>
            
            <!-- 好友列表内容 -->
            <div id="friends-list-content" style="<?php echo $chat_type === 'friend' ? 'display: block;' : 'display: none;'; ?>">
                <?php foreach ($friends as $friend_item): ?>
                    <?php 
                        $friend_id = $friend_item['friend_id'] ?? $friend_item['id'] ?? 0;
                        $friend_unread_key = 'friend_' . $friend_id;
                        $friend_unread_count = isset($unread_counts[$friend_unread_key]) ? $unread_counts[$friend_unread_key] : 0;
                    ?>
                    <div class="friend-item <?php echo $chat_type === 'friend' && $selected_id == $friend_id ? 'active' : ''; ?>" data-friend-id="<?php echo $friend_id; ?>">
                        <div class="friend-avatar">
                            <?php 
                                // 检查是否是默认头像
                                $is_default_avatar = !empty($friend_item['avatar']) && (strpos($friend_item['avatar'], 'default_avatar.png') !== false || $friend_item['avatar'] === 'default_avatar.png');
                            ?>
                            <?php if (!empty($friend_item['avatar']) && !$is_default_avatar): ?>
                                <img src="<?php echo $friend_item['avatar']; ?>" alt="<?php echo $friend_item['username']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo substr($friend_item['username'], 0, 2); ?>
                            <?php endif; ?>
                            <div class="status-indicator <?php echo $friend_item['status']; ?>"></div>
                        </div>
                        <div class="friend-info" style="position: relative;">
                            <h3><?php echo $friend_item['username']; ?></h3>
                            <p><?php echo $friend_item['status'] == 'online' ? '在线' : '离线'; ?></p>
                            <?php if ($friend_unread_count > 0): ?>
                                <div style="position: absolute; top: 0; right: -10px; background: #ff4757; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                                    <?php echo $friend_unread_count > 99 ? '99+' : $friend_unread_count; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 群聊列表内容 -->
            <div id="groups-list-content" style="<?php echo $chat_type === 'group' ? 'display: block;' : 'display: none;'; ?>">
                <?php foreach ($groups as $group_item): ?>
                    <?php 
                        $group_unread_key = 'group_' . $group_item['id'];
                        $group_unread_count = isset($unread_counts[$group_unread_key]) ? $unread_counts[$group_unread_key] : 0;
                    ?>
                    <div class="friend-item <?php echo $chat_type === 'group' && $selected_id == $group_item['id'] ? 'active' : ''; ?>" data-group-id="<?php echo $group_item['id']; ?>">
                        <div class="friend-avatar" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);">
                            <?php echo substr($group_item['name'], 0, 2); ?>
                        </div>
                        <div class="friend-info" style="position: relative;">
                            <h3><?php echo $group_item['name']; ?></h3>
                            <p>成员: <?php echo $group_item['member_count']; ?>人</p>
                            <?php if ($group_unread_count > 0): ?>
                                <div style="position: absolute; top: 0; right: -10px; background: #ff4757; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">
                                    <?php echo $group_unread_count > 99 ? '99+' : $group_unread_count; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- 聊天区域 -->
        <div class="chat-area <?php echo $selected_id ? 'active' : ''; ?>">
            <?php if ($selected_friend || $selected_group) { ?>
                <div class="chat-header">
                    <button class="back-btn" onclick="showFriendsList()" style="background: none; border: none; font-size: 18px; color: #667eea; margin-right: 10px;">
                        ←
                    </button>
                    <div class="friend-avatar" style="<?php echo $selected_group ? 'background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);' : ''; ?>">
                        <?php if ($selected_friend) { ?>
                            <?php 
                                // 检查是否是默认头像
                                $is_default_avatar = !empty($selected_friend['avatar']) && (strpos($selected_friend['avatar'], 'default_avatar.png') !== false || $selected_friend['avatar'] === 'default_avatar.png');
                            ?>
                            <?php if (!empty($selected_friend['avatar']) && !$is_default_avatar) { ?>
                                <img src="<?php echo $selected_friend['avatar']; ?>" alt="<?php echo $selected_friend['username']; ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <?php } else { ?>
                                <?php echo substr($selected_friend['username'], 0, 2); ?>
                            <?php } ?>
                            <div class="status-indicator <?php echo $selected_friend['status']; ?>"></div>
                        <?php } elseif ($selected_group) { ?>
                            <?php echo substr($selected_group['name'], 0, 2); ?>
                        <?php } ?>
                    </div>
                    <div class="chat-header-info">
                        <h2><?php echo $selected_friend ? $selected_friend['username'] : ($selected_group ? $selected_group['name'] : ''); ?></h2>
                        <p>
                            <?php if ($selected_friend) { ?>
                                <?php echo $selected_friend['status'] == 'online' ? '在线' : '离线'; ?>
                            <?php } elseif ($selected_group) { ?>
                                成员: <?php echo isset($selected_group['member_count']) ? $selected_group['member_count'] : 0; ?>人
                            <?php } ?>
                        </p>
                    </div>
                    <button class="chat-menu-btn" onclick="toggleChatMenu()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666; margin-left: auto; padding: 0 10px;">
                        ⋮
                    </button>
                </div>
                
                <!-- 聊天菜单 -->
                <div id="chat-menu" style="display: none; position: fixed; top: 80px; right: 20px; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); z-index: 1000; min-width: 150px;">
                    <div style="padding: 10px;">
                        <?php if ($selected_friend) { ?>
                            <!-- 好友聊天菜单 -->
                            <button onclick="deleteFriend(<?php echo $selected_friend['id']; ?>)" style="display: block; width: 100%; padding: 12px 15px; background: #f5f5f5; color: #d32f2f; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; margin-bottom: 10px; text-align: left; transition: background-color 0.2s;">
                                删除好友
                            </button>
                        <?php } elseif ($selected_group) { ?>
                            <!-- 群聊聊天菜单 -->
                            <button onclick="showGroupMembers(<?php echo $selected_group['id']; ?>)" style="display: block; width: 100%; padding: 12px 15px; background: #f5f5f5; color: #333; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; margin-bottom: 10px; text-align: left; transition: background-color 0.2s;">
                                查看成员
                            </button>
                            <button onclick="leaveGroup(<?php echo $selected_group['id']; ?>)" style="display: block; width: 100%; padding: 12px 15px; background: #f5f5f5; color: #d32f2f; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; text-align: left; transition: background-color 0.2s;">
                                退出群聊
                            </button>
                        <?php } ?>
                    </div>
                </div>
                
                <div class="messages-container" id="messages-container">
                    <!-- 聊天记录将通过JavaScript动态加载 -->
                </div>
                
                <!-- 初始聊天记录数据 -->
                <script>
    // 检查群聊是否被封禁
    let isGroupBanned = false;
    
    function checkGroupBanStatus(groupId) {
        return fetch(`check_group_ban.php?group_id=${groupId}`)
            .then(response => response.json())
            .then(data => {
                if (data.banned) {
                    isGroupBanned = true;
                    showGroupBanModal(data.group_name, data.reason, data.ban_end);
                    disableGroupOperations();
                } else {
                    isGroupBanned = false;
                }
                return data.banned;
            })
            .catch(error => {
                console.error('检查群聊封禁状态失败:', error);
                return false;
            });
    }
    
    // 显示群聊封禁弹窗
    function showGroupBanModal(groupName, reason, banEnd) {
        // 创建封禁弹窗
        const modal = document.createElement('div');
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        `;
        
        // 封禁图标
        const banIcon = document.createElement('div');
        banIcon.style.cssText = `
            font-size: 64px;
            margin-bottom: 20px;
            color: #ff4757;
        `;
        banIcon.textContent = '🚫';
        
        // 标题
        const title = document.createElement('h3');
        title.style.cssText = `
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        `;
        title.textContent = '群聊已被封禁';
        
        // 内容
        const content = document.createElement('div');
        content.style.cssText = `
            margin-bottom: 25px;
            color: #666;
            font-size: 14px;
        `;
        
        content.innerHTML = `
            <p>此群 <strong>${groupName}</strong> 已被封禁</p>
            <p style="margin: 10px 0;">原因：${reason}</p>
            <p>预计解封时长：${banEnd ? new Date(banEnd).toLocaleString() : '永久'}</p>
            <p style="color: #ff4757; margin-top: 15px;">群聊被封禁期间，无法使用任何群聊功能</p>
        `;
        
        // 关闭按钮
        const closeBtn = document.createElement('button');
        closeBtn.style.cssText = `
            padding: 12px 30px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
        `;
        closeBtn.textContent = '确定';
        
        closeBtn.addEventListener('click', () => {
            document.body.removeChild(modal);
            // 返回聊天列表
            window.location.href = 'mobilechat.php';
        });
        
        // 组装弹窗
        modalContent.appendChild(banIcon);
        modalContent.appendChild(title);
        modalContent.appendChild(content);
        modalContent.appendChild(closeBtn);
        modal.appendChild(modalContent);
        
        // 添加到页面
        document.body.appendChild(modal);
    }
    
    // 禁用所有群聊操作
    function disableGroupOperations() {
        // 禁用输入区域
        const inputArea = document.querySelector('.input-area');
        if (inputArea) {
            inputArea.style.display = 'none';
        }
        
        // 添加封禁提示
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            const banNotice = document.createElement('div');
            banNotice.style.cssText = `
                background: #ffebee;
                color: #d32f2f;
                padding: 12px 20px;
                border-radius: 8px;
                margin-bottom: 15px;
                text-align: center;
                font-size: 14px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            `;
            banNotice.textContent = '群聊被封禁，您暂时无法查看群聊成员和使用群聊功能';
            messagesContainer.insertBefore(banNotice, messagesContainer.firstChild);
        }
    }
    
    // 显示结果模态框
    function showResultModal(title, message, type = 'info') {
        // 移除已存在的模态框
        const existingModal = document.getElementById('result-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // 创建模态框
        const modal = document.createElement('div');
        modal.id = 'result-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        `;
        
        // 标题
        const modalTitle = document.createElement('h3');
        modalTitle.style.cssText = `
            margin-bottom: 15px;
            color: ${type === 'error' ? '#d32f2f' : '#333'};
            font-size: 18px;
        `;
        modalTitle.textContent = title;
        
        // 内容
        const modalMessage = document.createElement('p');
        modalMessage.style.cssText = `
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        `;
        modalMessage.textContent = message;
        
        // 关闭按钮
        const closeBtn = document.createElement('button');
        closeBtn.style.cssText = `
            padding: 10px 25px;
            background: ${type === 'error' ? '#d32f2f' : '#667eea'};
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
        `;
        closeBtn.textContent = '确定';
        
        closeBtn.addEventListener('click', () => {
            modal.remove();
        });
        
        // 组装模态框
        modalContent.appendChild(modalTitle);
        modalContent.appendChild(modalMessage);
        modalContent.appendChild(closeBtn);
        modal.appendChild(modalContent);
        
        // 添加到页面
        document.body.appendChild(modal);
        
        // 3秒后自动关闭
        setTimeout(() => {
            if (modal.parentNode) {
                modal.remove();
            }
        }, 3000);
    }
    
    // 显示确认模态框
    function showConfirmModal(title, message, onConfirm, onCancel = null) {
        // 移除已存在的模态框
        const existingModal = document.getElementById('confirm-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // 创建模态框
        const modal = document.createElement('div');
        modal.id = 'confirm-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        `;
        
        // 标题
        const modalTitle = document.createElement('h3');
        modalTitle.style.cssText = `
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        `;
        modalTitle.textContent = title;
        
        // 内容
        const modalMessage = document.createElement('p');
        modalMessage.style.cssText = `
            margin-bottom: 25px;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
            white-space: pre-wrap;
        `;
        modalMessage.textContent = message;
        
        // 按钮容器
        const buttonsContainer = document.createElement('div');
        buttonsContainer.style.cssText = `
            display: flex;
            gap: 10px;
            justify-content: center;
        `;
        
        // 取消按钮
        const cancelBtn = document.createElement('button');
        cancelBtn.style.cssText = `
            flex: 1;
            padding: 10px 25px;
            background: #f5f5f5;
            color: #333;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
        `;
        cancelBtn.textContent = '取消';
        
        cancelBtn.addEventListener('click', () => {
            modal.remove();
            if (onCancel) {
                onCancel();
            }
        });
        
        // 确认按钮
        const confirmBtn = document.createElement('button');
        confirmBtn.style.cssText = `
            flex: 1;
            padding: 10px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.2s;
        `;
        confirmBtn.textContent = '确定';
        
        confirmBtn.addEventListener('click', () => {
            modal.remove();
            onConfirm();
        });
        
        // 组装模态框
        buttonsContainer.appendChild(cancelBtn);
        buttonsContainer.appendChild(confirmBtn);
        modalContent.appendChild(modalTitle);
        modalContent.appendChild(modalMessage);
        modalContent.appendChild(buttonsContainer);
        modal.appendChild(modalContent);
        
        // 添加到页面
        document.body.appendChild(modal);
    }
    
    // 页面加载时检查当前群聊是否被封禁
    document.addEventListener('DOMContentLoaded', function() {
        const chatType = document.querySelector('input[name="chat_type"]')?.value;
        const groupId = document.querySelector('input[name="id"]')?.value;
        
        if (chatType === 'group' && groupId) {
            checkGroupBanStatus(groupId);
        }
    });
                    // 初始聊天记录数据
                    const initialChatHistory = <?php echo json_encode($chat_history); ?>;
                    
                    // 加载初始聊天记录
                    function loadInitialChatHistory() {
                        const messagesContainer = document.getElementById('messages-container');
                        if (!messagesContainer) return;
                        
                        initialChatHistory.forEach(msg => {
                            const isSent = msg.sender_id == <?php echo $user_id; ?>;
                            const messageElement = createMessage(msg, isSent);
                            messagesContainer.appendChild(messageElement);
                        });
                        
                        // 滚动到底部
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                    
                    // 标记消息为已读
                    function markMessagesAsRead() {
                        const chatType = '<?php echo $chat_type; ?>';
                        const selectedId = '<?php echo $selected_id; ?>';
                        
                        if (!selectedId) return;
                        
                        fetch('mark_messages_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `chat_type=${chatType}&chat_id=${selectedId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                console.error('标记消息为已读失败:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('标记消息为已读失败:', error);
                        });
                    }
                    
                    // 加载群聊禁言状态
                    async function loadChatMuteStatus() {
                        const chatType = document.querySelector('input[name="chat_type"]')?.value;
                        const chatId = document.querySelector('input[name="id"]')?.value;
                        

                    }
                    

                    
                    // 页面加载完成后加载初始聊天记录和标记消息为已读
                    document.addEventListener('DOMContentLoaded', () => {
                        loadInitialChatHistory();
                        markMessagesAsRead();
                    });
                </script>
                

                
                <div class="input-area">
                    <form id="message-form" enctype="multipart/form-data">
                        <?php if ($selected_friend) { ?>
                            <input type="hidden" name="chat_type" value="friend">
                            <input type="hidden" name="id" value="<?php echo $selected_id; ?>">
                            <input type="hidden" name="friend_id" value="<?php echo $selected_id; ?>">
                        <?php } elseif ($selected_group) { ?>
                            <input type="hidden" name="chat_type" value="group">
                            <input type="hidden" name="id" value="<?php echo $selected_id; ?>">
                            <input type="hidden" name="group_id" value="<?php echo $selected_id; ?>">
                        <?php } ?>
                        <div class="input-wrapper">
                            <textarea id="message-input" name="message" placeholder="输入消息..."></textarea>
                            
                            <!-- @用户下拉选择框 -->
                            <div id="mention-dropdown" style="display: none; position: absolute; bottom: 100%; left: 0; width: 100%; max-height: 200px; overflow-y: auto; background: white; border: 1px solid #e0e0e0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); z-index: 1000;">
                                <!-- 成员列表将通过JavaScript动态生成 -->
                            </div>
                        </div>
                        <div class="input-actions">
                            <label for="file-input" class="btn-icon" title="发送文件">
                                📎
                            </label>
                            <input type="file" id="file-input" name="file" accept="*/*">
                            <button type="submit" class="btn-icon" title="发送消息">
                                ➤
                            </button>
                        </div>
                    </form>
                </div>
            <?php } else { ?>
                <div class="messages-container" style="justify-content: center; align-items: center; text-align: center;">
                    <h2 style="color: #666; margin-bottom: 10px;">选择一个好友开始聊天</h2>
                    <p style="color: #999;">从左侧列表中选择一个好友，开始你们的对话</p>
                </div>
            <?php } ?>
        </div>
    </div>
    
    <!-- 扫码登录模态框 -->
    <div class="modal" id="scan-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 2000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="position: relative; width: 100%; max-width: 400px;">
            <button onclick="closeScanModal()" style="position: absolute; top: -40px; right: 0; background: rgba(0, 0, 0, 0.5); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                ×
            </button>
            <video id="qr-video" style="width: 100%; height: auto; border-radius: 8px;" playsinline></video>
            <div id="scan-hint" style="color: white; text-align: center; margin-top: 20px; font-size: 16px;">请将二维码对准相机</div>
        </div>
    </div>
    
    <!-- 登录确认模态框 -->
    <div class="modal" id="confirm-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 400px; text-align: center;">
            <h3 style="margin-bottom: 15px; color: #333;">确认登录</h3>
            <p id="confirm-message" style="margin-bottom: 20px; color: #666; font-size: 14px;"></p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="rejectLogin()" style="padding: 10px 20px; background: #f5f5f5; color: #333; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; flex: 1;">取消</button>
                <button onclick="confirmLogin()" style="padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; flex: 1;">确认</button>
            </div>
        </div>
    </div>
    
    <!-- 登录成功提示 -->
    <div class="modal" id="success-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 300px; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
            <h3 style="margin-bottom: 10px; color: #333;">登录成功</h3>
            <p style="margin-bottom: 20px; color: #666; font-size: 14px;">已成功在PC端登录</p>
            <button onclick="closeSuccessModal()" style="padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">确定</button>
        </div>
    </div>
    
    <!-- 添加好友模态框 -->
    <div class="modal" id="add-friend-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 400px;">
            <h3 style="margin-bottom: 20px; color: #333; text-align: center;">添加好友</h3>
            <form id="add-friend-form">
                <div style="margin-bottom: 20px;">
                    <label for="friend-username" style="display: block; margin-bottom: 8px; color: #666; font-weight: 500;">用户名</label>
                    <input type="text" id="friend-username" name="username" placeholder="请输入要添加的好友用户名" style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; outline: none; transition: all 0.2s ease;" required>
                </div>
                <div style="margin-bottom: 20px;">
                    <label for="friend-message" style="display: block; margin-bottom: 8px; color: #666; font-weight: 500;">验证消息</label>
                    <textarea id="friend-message" name="message" placeholder="请输入验证消息" rows="3" style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; resize: vertical; outline: none; transition: all 0.2s ease;"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeAddFriendModal()" style="flex: 1; padding: 12px; background: #f5f5f5; color: #333; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">取消</button>
                    <button type="submit" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">发送请求</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 好友申请列表模态框 -->
    <div class="modal" id="friend-requests-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 400px; max-height: 80vh; overflow: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #333;">好友申请</h3>
                <button type="button" onclick="closeFriendRequestsModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
            </div>
            <div id="friend-requests-list">
                <?php if (empty($friend_requests)): ?>
                    <p style="text-align: center; color: #999; margin: 40px 0;">暂无好友申请</p>
                <?php else: ?>
                    <?php foreach ($friend_requests as $request): ?>
                        <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 18px;">
                                    <?php echo substr($request['username'], 0, 2); ?>
                                </div>
                                <div style="flex: 1;">
                                    <h4 style="margin: 0 0 5px; color: #333;"><?php echo $request['username']; ?></h4>
                                    <p style="margin: 0; color: #666; font-size: 14px;"><?php echo $request['email']; ?></p>
                                    <p style="margin: 5px 0 15px; color: #999; font-size: 12px;">申请时间: <?php echo $request['created_at']; ?></p>
                                    <div style="display: flex; gap: 10px;">
                                        <button onclick="acceptFriendRequest(<?php echo $request['id']; ?>)" style="flex: 1; padding: 8px 12px; background: #4caf50; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">同意</button>
                                        <button onclick="rejectFriendRequest(<?php echo $request['id']; ?>)" style="flex: 1; padding: 8px 12px; background: #f44336; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;">拒绝</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 反馈模态框 -->
    <div class="modal" id="feedback-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 2000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 20px; border-radius: 12px; width: 90%; max-width: 400px;">
            <h3 style="margin-bottom: 20px; color: #333; text-align: center;">反馈问题</h3>
            <form id="feedback-form" enctype="multipart/form-data">
                <div style="margin-bottom: 20px;">
                    <label for="feedback-content" style="display: block; margin-bottom: 8px; color: #666; font-weight: 500;">问题描述</label>
                    <textarea id="feedback-content" name="content" placeholder="请详细描述您遇到的问题" rows="5" style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; resize: vertical; outline: none; transition: all 0.2s ease;" required></textarea>
                </div>
                <div style="margin-bottom: 20px;">
                    <label for="feedback-image" style="display: block; margin-bottom: 8px; color: #666; font-weight: 500;">添加图片（可选）</label>
                    <input type="file" id="feedback-image" name="image" accept="image/*" style="width: 100%; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 14px; outline: none; transition: all 0.2s ease;">
                    <p style="font-size: 12px; color: #999; margin-top: 5px;">支持JPG、PNG、GIF格式，最大5MB</p>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeFeedbackModal()" style="flex: 1; padding: 12px; background: #f5f5f5; color: #333; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">取消</button>
                    <button type="submit" style="flex: 1; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">提交反馈</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 切换菜单
        function toggleMenu() {
            const menuPanel = document.getElementById('menu-panel');
            const overlay = document.getElementById('overlay');
            menuPanel.classList.toggle('open');
            overlay.classList.toggle('open');
        }
        
        // 显示添加好友模态框
        function showAddFriendModal() {
            const modal = document.getElementById('add-friend-modal');
            modal.style.display = 'flex';
            toggleMenu();
        }
        
        // 关闭添加好友模态框
        function closeAddFriendModal() {
            const modal = document.getElementById('add-friend-modal');
            modal.style.display = 'none';
            // 重置表单
            document.getElementById('add-friend-form').reset();
        }
        
        // 显示好友申请列表
        function showFriendRequests() {
            const modal = document.getElementById('friend-requests-modal');
            modal.style.display = 'flex';
            toggleMenu();
        }
        
        // 关闭好友申请列表
        function closeFriendRequestsModal() {
            const modal = document.getElementById('friend-requests-modal');
            modal.style.display = 'none';
        }
        
        // 接受好友申请
        function acceptFriendRequest(requestId) {
            if (confirm('确定要接受这个好友申请吗？')) {
                window.location.href = `accept_request.php?request_id=${requestId}`;
            }
        }
        
        // 拒绝好友申请
        function rejectFriendRequest(requestId) {
            if (confirm('确定要拒绝这个好友申请吗？')) {
                window.location.href = `reject_request.php?request_id=${requestId}`;
            }
        }
        
        // 处理添加好友表单提交
        document.getElementById('add-friend-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const username = formData.get('username').trim();
            const message = formData.get('message')?.trim() || '';
            
            if (!username) {
                alert('请输入好友用户名');
                return;
            }
            
            try {
                // 首先通过用户名获取用户ID
                const userResponse = await fetch(`get_user_id.php?username=${encodeURIComponent(username)}`);
                const userData = await userResponse.json();
                
                if (userData.success) {
                    // 发送好友请求
                    const requestResponse = await fetch('send_friend_request.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `friend_id=${userData.user_id}`
                    });
                    
                    const requestResult = await requestResponse.json();
                    
                    if (requestResult.success) {
                        alert('好友请求已发送');
                        closeAddFriendModal();
                    } else {
                        alert(requestResult.message || '发送失败，请稍后重试');
                    }
                } else {
                    alert(userData.message || '未找到该用户');
                }
            } catch (error) {
                console.error('添加好友请求失败:', error);
                alert('网络错误，请稍后重试');
            }
        });
        
        // 显示反馈模态框
        function showFeedbackModal() {
            const modal = document.getElementById('feedback-modal');
            modal.style.display = 'flex';
            toggleMenu();
        }
        
        // 关闭反馈模态框
        function closeFeedbackModal() {
            const modal = document.getElementById('feedback-modal');
            modal.style.display = 'none';
            // 重置表单
            document.getElementById('feedback-form')?.reset();
        }
        
        // 处理反馈表单提交
        document.getElementById('feedback-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append('action', 'submit_feedback');
            
            try {
                const response = await fetch('feedback-2.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('反馈提交成功，感谢您的反馈！');
                    closeFeedbackModal();
                } else {
                    alert(result.message || '提交失败，请稍后重试');
                }
            } catch (error) {
                console.error('提交反馈错误:', error);
                alert('网络错误，请稍后重试');
            }
        });
        
        // 扫码登录相关变量
        let scanner = null;
        let currentScanUrl = '';
        let currentQid = '';
        let currentIpAddress = '';
        
        // 显示扫码登录模态框
        function showScanLoginModal() {
            toggleMenu(); // 关闭菜单
            const modal = document.getElementById('scan-modal');
            modal.style.display = 'flex';
            initScanner();
        }
        
        // 关闭扫码登录模态框
        function closeScanModal() {
            const modal = document.getElementById('scan-modal');
            modal.style.display = 'none';
            stopScanner();
        }
        
        // 初始化扫码器
        async function initScanner() {
            try {
                // 请求相机权限，优先使用后置相机（适合扫码）
                // 提高相机分辨率，添加自动对焦
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        focusMode: 'continuous',
                        exposureMode: 'continuous'
                    }
                });
                
                const video = document.getElementById('qr-video');
                video.srcObject = stream;
                await video.play();
                
                // 立即开始扫描，不需要等待onloadeddata
                startScanning(video);
            } catch (error) {
                console.error('相机访问失败:', error);
                const hint = document.getElementById('scan-hint');
                hint.textContent = '相机访问失败，请检查权限设置';
                hint.style.color = '#ff4757';
            }
        }
        
        // 开始扫描
        function startScanning(video) {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            
            // 设置扫码提示
            const hint = document.getElementById('scan-hint');
            hint.textContent = '正在扫描二维码...';
            hint.style.color = '#4caf50';
            
            function scanFrame() {
                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    // 确保canvas尺寸与视频尺寸匹配
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    
                    try {
                        // 获取图像数据
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        
                        // 检查jsQR库是否已加载
                        if (typeof jsQR === 'undefined') {
                            // jsQR库未加载，显示错误
                            hint.textContent = '二维码库加载中...';
                            hint.style.color = '#ff9800';
                            // 继续扫描
                            requestAnimationFrame(scanFrame);
                            console.log('jsQR库未加载，等待加载完成');
                            return;
                        }
                        
                        // 使用jsQR库解码二维码，添加更详细的配置
                        const code = jsQR(imageData.data, imageData.width, imageData.height, {
                            inversionAttempts: 'both', // 尝试识别正常和反色二维码，提高识别率
                            // 提高识别率的配置
                        });
                        
                        if (code) {
                            // 扫描成功，更新提示
                            hint.textContent = '扫描成功！';
                            hint.style.color = '#4caf50';
                            console.log('扫描成功，二维码内容:', code.data);
                            // 处理扫描结果
                            handleScanResult(code.data);
                        } else {
                            // 继续扫描
                            requestAnimationFrame(scanFrame);
                            console.log('未识别到二维码，继续扫描');
                        }
                    } catch (error) {
                        console.error('扫描错误:', error);
                        // 继续扫描
                        requestAnimationFrame(scanFrame);
                    }
                } else {
                    // 视频还没准备好，继续等待
                    requestAnimationFrame(scanFrame);
                }
            }
            
            // 使用requestAnimationFrame提高扫描频率
            requestAnimationFrame(scanFrame);
        }
        
        // 停止扫描
        function stopScanner() {
            const video = document.getElementById('qr-video');
            if (video.srcObject) {
                const tracks = video.srcObject.getTracks();
                tracks.forEach(track => track.stop());
                video.srcObject = null;
            }
        }
        
        // 处理扫描结果
        function handleScanResult(result) {
            if (!result) return;
            
            console.log('扫描到的二维码内容:', result);
            
            // 检查是否是本站的扫码登录链接
            const domain = window.location.host;
            console.log('当前域名:', domain);
            
            if (result.includes(domain) && result.includes('scan_login.php')) {
                // 解析URL获取qid
                try {
                    const url = new URL(result);
                    const qid = url.searchParams.get('qid');
                    
                    console.log('解析到的qid:', qid);
                    
                    if (qid) {
                        currentScanUrl = result;
                        currentQid = qid;
                        
                        // 扫描后立即更新状态为scanned
                        fetch('scan_login.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                'qid': qid,
                                'action': 'scan',
                                'source': 'mobilechat.php'
                            })
                        }).catch(error => {
                            console.error('更新扫描状态失败:', error);
                        });
                        
                        // 显示确认登录对话框
                        console.log('显示确认登录对话框');
                        showConfirmModal();
                        
                        // 停止扫描
                        console.log('停止扫描');
                        closeScanModal();
                    } else {
                        console.log('未解析到qid');
                    }
                } catch (error) {
                    console.error('URL解析错误:', error);
                    alert('二维码格式错误，请扫描正确的登录二维码');
                }
            } else {
                console.log('不是本站的扫码登录链接');
                alert('不是本站的扫码登录链接');
            }
        }
        
        // 显示确认登录模态框
        function showConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            const message = document.getElementById('confirm-message');
            const confirmBtn = modal.querySelector('button[onclick="confirmLogin()"]');
            
            // 设置倒计时初始值
            let countdown = 6;
            
            // 禁用确认按钮
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.cursor = 'not-allowed';
            
            // 显示加载中状态
            message.innerHTML = `确定要在PC网页端登录吗？<br><br>正在获取登录IP地址...<br><br><small>请等待 ${countdown} 秒后点击确认</small>`;
            modal.style.display = 'flex';
            
            // 从服务器获取扫码登录的IP地址
            fetch(`get_scan_ip.php?qid=${currentQid}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentIpAddress = data.ip_address;
                        message.innerHTML = `确定要在PC网页端登录吗？<br><br>登录IP地址: <strong>${currentIpAddress}</strong><br><br><small>请等待 ${countdown} 秒后点击确认</small>`;
                    } else {
                        currentIpAddress = '获取IP失败';
                        message.innerHTML = `确定要在PC网页端登录吗？<br><br>登录IP地址: <strong>${currentIpAddress}</strong><br><br><small>请等待 ${countdown} 秒后点击确认</small>`;
                    }
                })
                .catch(error => {
                    console.error('获取IP地址失败:', error);
                    currentIpAddress = '获取IP失败';
                    message.innerHTML = `确定要在PC网页端登录吗？<br><br>登录IP地址: <strong>${currentIpAddress}</strong><br><br><small>请等待 ${countdown} 秒后点击确认</small>`;
                });
            
            // 倒计时功能
            const countdownInterval = setInterval(() => {
                countdown--;
                message.innerHTML = `确定要在PC网页端登录吗？<br><br>登录IP地址: <strong>${currentIpAddress}</strong><br><br><small>请等待 ${countdown} 秒后点击确认</small>`;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    // 启用确认按钮
                    confirmBtn.disabled = false;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                    message.innerHTML = `确定要在PC网页端登录吗？<br><br>登录IP地址: <strong>${currentIpAddress}</strong>`;
                }
            }, 1000);
        }
        
        // 确认登录
        function confirmLogin() {
            const modal = document.getElementById('confirm-modal');
            modal.style.display = 'none';
            
            // 发送登录请求
            sendLoginRequest();
        }
        
        // 拒绝登录
        function rejectLogin() {
            const modal = document.getElementById('confirm-modal');
            modal.style.display = 'none';
            
            // 发送拒绝登录请求，更新状态为rejected
            fetch('scan_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'qid': currentQid,
                    'action': 'reject',
                    'source': 'mobilechat.php'
                })
            }).then(response => response.json())
              .then(result => {
                  console.log('拒绝登录结果:', result);
              })
              .catch(error => {
                  console.error('发送拒绝登录请求失败:', error);
              });
        }
        
        // 发送登录请求
        async function sendLoginRequest() {
            try {
                const response = await fetch('scan_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'qid': currentQid,
                        'user': '<?php echo $username; ?>',
                        'source': 'mobilechat.php'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 显示登录成功提示
                    showSuccessModal();
                } else {
                    alert(result.message || '登录失败');
                }
            } catch (error) {
                console.error('发送登录请求失败:', error);
                alert('登录失败，请稍后重试');
            }
        }
        
        // 显示登录成功提示
        function showSuccessModal() {
            const modal = document.getElementById('success-modal');
            modal.style.display = 'flex';
        }
        
        // 关闭登录成功提示
        function closeSuccessModal() {
            const modal = document.getElementById('success-modal');
            modal.style.display = 'none';
        }
        
        // 手动触发扫码结果（用于测试）
        function testScanResult() {
            const testUrl = window.location.origin + '/chat/scan_login.php?qid=test123';
            handleScanResult(testUrl);
        }
        
        // 添加jsQR库（实际项目中应在HTML头部引入）
        // 这里我们动态添加jsQR库
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        script.onload = () => {
            console.log('jsQR库加载完成');
            // 重新定义startScanning函数，使用jsQR库
            startScanning = function(video) {
                function scanFrame() {
                    if (video.readyState === video.HAVE_ENOUGH_DATA) {
                        const canvas = document.createElement('canvas');
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const code = jsQR(imageData.data, imageData.width, imageData.height);
                        
                        if (code) {
                            handleScanResult(code.data);
                        } else {
                            requestAnimationFrame(scanFrame);
                        }
                    } else {
                        requestAnimationFrame(scanFrame);
                    }
                }
                scanFrame();
            };
        };
        document.head.appendChild(script);
        
        // 自定义音频播放器类
        class CustomAudioPlayer {
            constructor(audioUrl) {
                this.audioUrl = audioUrl;
                this.isPlaying = false;
                this.audio = null;
                this.container = null;
            }
            
            // 创建音频播放器
            createPlayer() {
                // 创建容器
                this.container = document.createElement('div');
                this.container.className = 'custom-audio-player';
                
                // 创建播放按钮
                const playBtn = document.createElement('button');
                playBtn.className = 'audio-play-btn';
                playBtn.innerHTML = '▶';
                playBtn.title = '播放';
                
                // 创建进度条容器
                const progressContainer = document.createElement('div');
                progressContainer.className = 'audio-progress-container';
                
                // 创建进度条
                const progressBar = document.createElement('div');
                progressBar.className = 'audio-progress-bar';
                
                // 创建进度
                const progress = document.createElement('div');
                progress.className = 'audio-progress';
                progress.style.width = '0%';
                
                // 创建时间显示
                const timeDisplay = document.createElement('span');
                timeDisplay.className = 'audio-time';
                timeDisplay.textContent = '0:00';
                
                // 创建时长显示
                const durationDisplay = document.createElement('span');
                durationDisplay.className = 'audio-duration';
                durationDisplay.textContent = '0:00';
                
                // 创建隐藏的audio元素
                this.audio = document.createElement('audio');
                this.audio.src = this.audioUrl;
                this.audio.preload = 'metadata';
                
                // 组装播放器
                progressBar.appendChild(progress);
                progressContainer.appendChild(progressBar);
                this.container.appendChild(playBtn);
                this.container.appendChild(progressContainer);
                this.container.appendChild(timeDisplay);
                this.container.appendChild(durationDisplay);
                this.container.appendChild(this.audio);
                
                // 添加事件监听
                this.setupEventListeners(playBtn, progressBar, progress, timeDisplay, durationDisplay);
                
                return this.container;
            }
            
            // 设置事件监听
            setupEventListeners(playBtn, progressBar, progress, timeDisplay, durationDisplay) {
                // 播放/暂停按钮点击事件
                playBtn.addEventListener('click', () => {
                    this.togglePlay(playBtn);
                });
                
                // 音频播放事件
                this.audio.addEventListener('play', () => {
                    this.isPlaying = true;
                    playBtn.innerHTML = '⏸';
                    playBtn.className = 'audio-play-btn paused';
                });
                
                // 音频暂停事件
                this.audio.addEventListener('pause', () => {
                    this.isPlaying = false;
                    playBtn.innerHTML = '▶';
                    playBtn.className = 'audio-play-btn';
                });
                
                // 音频结束事件
                this.audio.addEventListener('ended', () => {
                    this.isPlaying = false;
                    playBtn.innerHTML = '▶';
                    playBtn.className = 'audio-play-btn';
                    progress.style.width = '0%';
                    timeDisplay.textContent = '0:00';
                    this.audio.currentTime = 0;
                });
                
                // 音频时间更新事件
                this.audio.addEventListener('timeupdate', () => {
                    this.updateProgress(progress, timeDisplay);
                });
                
                // 音频加载元数据事件
                this.audio.addEventListener('loadedmetadata', () => {
                    durationDisplay.textContent = this.formatTime(this.audio.duration);
                });
                
                // 进度条点击事件
                progressBar.addEventListener('click', (e) => {
                    this.seek(e, progressBar, progress);
                });
            }
            
            // 切换播放/暂停
            togglePlay(playBtn) {
                if (this.isPlaying) {
                    this.audio.pause();
                } else {
                    this.audio.play();
                }
            }
            
            // 更新进度
            updateProgress(progress, timeDisplay) {
                const percent = (this.audio.currentTime / this.audio.duration) * 100;
                progress.style.width = percent + '%';
                timeDisplay.textContent = this.formatTime(this.audio.currentTime);
            }
            
            // 进度条拖动定位
            seek(e, progressBar, progress) {
                const rect = progressBar.getBoundingClientRect();
                const percent = (e.clientX - rect.left) / rect.width;
                this.audio.currentTime = percent * this.audio.duration;
                progress.style.width = percent * 100 + '%';
            }
            
            // 格式化时间
            formatTime(seconds) {
                if (isNaN(seconds)) return '0:00';
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return `${mins}:${secs.toString().padStart(2, '0')}`;
            }
        }
        
        // 转换URL为链接
        function convertUrlsToLinks(text) {
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, '<a href="$1" class="message-link" target="_blank" rel="noopener noreferrer">$1</a>');
        }
        
        // 好友和群聊选择 - 使用事件委托确保所有动态生成的元素都能被正确处理
        document.addEventListener('click', (e) => {
            const friendItem = e.target.closest('.friend-item');
            if (friendItem) {
                const friendId = friendItem.dataset.friendId;
                const groupId = friendItem.dataset.groupId;
                if (friendId) {
                    window.location.href = `mobilechat.php?chat_type=friend&id=${friendId}`;
                } else if (groupId) {
                    window.location.href = `mobilechat.php?chat_type=group&id=${groupId}`;
                }
            }
        });
        
        // 显示好友列表
        function showFriendsList() {
            window.location.href = 'mobilechat.php';
        }
        
        // 切换聊天类型
        function switchChatType(chatType) {
            window.location.href = `mobilechat.php?chat_type=${chatType}`;
        }
        
        // 消息相关函数
        function createMessage(message, isSent) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
            
            const avatarDiv = document.createElement('div');
            avatarDiv.className = 'message-avatar';
            
            // 获取当前用户头像
            const currentUserAvatar = '<?php echo !empty($current_user['avatar']) ? $current_user['avatar'] : ''; ?>';
            
            // 辅助函数：检查是否是默认头像
            function isDefaultAvatar(avatar) {
                return avatar && (avatar.includes('default_avatar.png') || avatar === 'default_avatar.png');
            }
            
            if (isSent) {
                if (currentUserAvatar && !isDefaultAvatar(currentUserAvatar)) {
                    const img = document.createElement('img');
                    img.src = currentUserAvatar;
                    img.alt = '<?php echo $username; ?>';
                    img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
                    avatarDiv.appendChild(img);
                } else {
                    avatarDiv.textContent = '<?php echo substr($username, 0, 2); ?>';
                }
            } else {
                // 接收的消息，使用发送者头像（适用于群聊和好友聊天）
                if (message.avatar && !isDefaultAvatar(message.avatar)) {
                    // 群聊消息，使用发送者的头像
                    const img = document.createElement('img');
                    img.src = message.avatar;
                    img.alt = message.sender_username || '未知用户';
                    img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
                    avatarDiv.appendChild(img);
                } else {
                    // 检查是否是群聊消息
                    const chatType = '<?php echo $chat_type; ?>';
                    if (chatType === 'group') {
                        // 群聊消息，没有头像时显示发送者用户名首字母
                        const senderName = message.sender_username || '未知用户';
                        avatarDiv.textContent = senderName.substring(0, 2);
                    } else {
                        // 好友聊天，使用好友头像或用户名首字母
                        const friendAvatar = '<?php echo $selected_friend && !empty($selected_friend['avatar']) ? $selected_friend['avatar'] : ''; ?>';
                        const friendName = '<?php echo $selected_friend ? $selected_friend['username'] : ''; ?>';
                        
                        if (friendAvatar && !isDefaultAvatar(friendAvatar)) {
                            const img = document.createElement('img');
                            img.src = friendAvatar;
                            img.alt = friendName;
                            img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
                            avatarDiv.appendChild(img);
                        } else {
                            avatarDiv.textContent = friendName ? friendName.substring(0, 2) : '?';
                        }
                    }
                }
            }
            
            const contentDiv = document.createElement('div');
            contentDiv.className = 'message-content';
            
            // 处理文本消息
            if ((message.type === 'text' || !message.type) && message.content) {
                const textDiv = document.createElement('div');
                textDiv.className = 'message-text';
                // 转换URL为链接
                const textWithLinks = convertUrlsToLinks(message.content);
                textDiv.innerHTML = textWithLinks;
                contentDiv.appendChild(textDiv);
            }
            
            // 处理文件消息
            if (message.file_path) {
                // 获取文件扩展名和MIME类型
                const fileName = message.file_name;
                const fileUrl = message.file_path;
                
                // 确保fileName存在且有扩展名
                let fileExtension = '';
                if (fileName && fileName.includes('.')) {
                    fileExtension = fileName.split('.').pop().toLowerCase();
                }
                
                // 禁止显示的文件扩展名
                const forbiddenExtensions = ['php', 'html', 'js', 'htm', 'css', 'xml'];
                
                // 如果是禁止的文件扩展名，不显示该文件
                if (forbiddenExtensions.includes(fileExtension)) {
                    const forbiddenMessage = document.createElement('div');
                    forbiddenMessage.style.cssText = 'color: #999; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 8px;';
                    forbiddenMessage.textContent = '该文件类型不支持显示';
                    contentDiv.appendChild(forbiddenMessage);
                } else {
                    // 图片类型 - 确保所有图片文件都显示为图片
                    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'ico'];
                    if (imageExtensions.includes(fileExtension)) {
                        const imgContainer = document.createElement('div');
                        imgContainer.style.cssText = 'display: inline-block; margin: 5px;';
                        
                        const img = document.createElement('img');
                        img.src = fileUrl;
                        img.alt = fileName;
                        img.style.cssText = `
                            max-width: 200px;
                            max-height: 200px;
                            cursor: pointer;
                            border-radius: 8px;
                            transition: transform 0.2s;
                            object-fit: cover;
                        `;
                        
                        // 添加图片加载失败处理
                        img.onerror = () => {
                            img.remove();
                            const errorMessage = document.createElement('div');
                            errorMessage.style.cssText = 'color: #999; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 8px;';
                            errorMessage.textContent = '文件已被清理，每7天清理一次uploads目录';
                            contentDiv.appendChild(errorMessage);
                        };
                        
                        imgContainer.appendChild(img);
                        contentDiv.appendChild(imgContainer);
                    } 
                    // 音频类型 - 确保所有音频文件都显示为自定义音频播放器
                    else if (['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'wma', 'aiff', 'opus', 'webm'].includes(fileExtension)) {
                        const audioContainer = document.createElement('div');
                        audioContainer.style.cssText = 'margin: 5px 0;';
                        
                        // 创建自定义音频播放器
                        const audioPlayer = new CustomAudioPlayer(fileUrl);
                        const playerElement = audioPlayer.createPlayer();
                        
                        // 添加音频加载失败处理
                        const audioElement = playerElement.querySelector('audio');
                        audioElement.onerror = () => {
                            audioContainer.innerHTML = '';
                            const errorMessage = document.createElement('div');
                            errorMessage.style.cssText = 'color: #999; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 8px;';
                            errorMessage.textContent = '文件已被清理，每7天清理一次uploads目录';
                            audioContainer.appendChild(errorMessage);
                        };
                        
                        audioContainer.appendChild(playerElement);
                        contentDiv.appendChild(audioContainer);
                    } 
                    // 其他文件类型
                    else {
                        const fileLinkContainer = document.createElement('div');
                        
                        const fileLink = document.createElement('a');
                        fileLink.href = fileUrl;
                        fileLink.download = fileName;
                        fileLink.style.cssText = `
                            display: inline-block;
                            padding: 8px 12px;
                            background: #f0f0f0;
                            color: #333;
                            text-decoration: none;
                            border-radius: 4px;
                            margin: 5px 0;
                            transition: background-color 0.2s;
                        `;
                        fileLink.onmouseover = () => {
                            fileLink.style.background = '#e0e0e0';
                        };
                        fileLink.onmouseout = () => {
                            fileLink.style.background = '#f0f0f0';
                        };
                        
                        // 添加点击事件处理，检查文件是否存在
                        fileLink.onclick = async (e) => {
                            e.preventDefault();
                            
                            try {
                                // 发送HEAD请求检查文件是否存在
                                const response = await fetch(fileUrl, { method: 'HEAD' });
                                if (response.ok) {
                                    // 文件存在，执行下载
                                    window.location.href = fileUrl;
                                } else {
                                    // 文件不存在，显示错误信息
                                    fileLinkContainer.innerHTML = '';
                                    const errorMessage = document.createElement('div');
                                    errorMessage.style.cssText = 'color: #999; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 8px;';
                                    errorMessage.textContent = '文件已被清理，每7天清理一次uploads目录';
                                    fileLinkContainer.appendChild(errorMessage);
                                }
                            } catch (error) {
                                // 请求失败，显示错误信息
                                fileLinkContainer.innerHTML = '';
                                const errorMessage = document.createElement('div');
                                errorMessage.style.cssText = 'color: #999; font-size: 14px; padding: 10px; background: #f8f9fa; border-radius: 8px;';
                                errorMessage.textContent = '文件已被清理，每7天清理一次uploads目录';
                                fileLinkContainer.appendChild(errorMessage);
                            }
                        };
                        
                        const fileIcon = document.createElement('span');
                        fileIcon.textContent = '📎 ';
                        
                        const fileNameSpan = document.createElement('span');
                        fileNameSpan.textContent = fileName;
                        
                        fileLink.appendChild(fileIcon);
                        fileLink.appendChild(fileNameSpan);
                        fileLinkContainer.appendChild(fileLink);
                        contentDiv.appendChild(fileLinkContainer);
                    }
                }
            }
            
            const timeDiv = document.createElement('div');
            timeDiv.className = 'message-time';
            timeDiv.textContent = new Date(message.created_at).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
            contentDiv.appendChild(timeDiv);
            
            // 添加消息操作菜单（仅发送者可见）
            if (isSent && message.id && typeof message.created_at !== 'undefined') {
                const now = new Date();
                const messageTime = new Date(message.created_at);
                const diffMinutes = Math.floor((now - messageTime) / (1000 * 60));
                
                // 只有2分钟内的消息可以撤回
                if (diffMinutes < 2) {
                    const messageMenu = document.createElement('div');
                    messageMenu.className = 'message-menu';
                    messageMenu.style.cssText = `
                        position: relative;
                        display: inline-block;
                    `;
                    
                    const menuButton = document.createElement('button');
                    menuButton.className = 'message-menu-btn';
                    menuButton.textContent = '...';
                    menuButton.style.cssText = `
                        background: none;
                        border: none;
                        color: #666;
                        font-size: 16px;
                        cursor: pointer;
                        padding: 2px 5px;
                        border-radius: 3px;
                        transition: background-color 0.2s;
                        margin-left: 5px;
                    `;
                    
                    menuButton.onmouseover = () => {
                        menuButton.style.backgroundColor = '#f0f0f0';
                    };
                    
                    menuButton.onmouseout = () => {
                        menuButton.style.backgroundColor = 'transparent';
                    };
                    
                    const menuContent = document.createElement('div');
                    menuContent.className = 'message-menu-content';
                    menuContent.style.cssText = `
                        display: none;
                        position: absolute;
                        right: 0;
                        top: 100%;
                        background: white;
                        border: 1px solid #e0e0e0;
                        border-radius: 4px;
                        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                        z-index: 1000;
                        min-width: 80px;
                    `;
                    
                    // 撤回消息按钮
                    const recallButton = document.createElement('button');
                    recallButton.textContent = '撤回';
                    recallButton.style.cssText = `
                        display: block;
                        width: 100%;
                        padding: 8px 12px;
                        background: none;
                        border: none;
                        text-align: left;
                        cursor: pointer;
                        font-size: 14px;
                        color: #333;
                        transition: background-color 0.2s;
                    `;
                    
                    recallButton.onmouseover = () => {
                        recallButton.style.backgroundColor = '#f0f0f0';
                    };
                    
                    recallButton.onmouseout = () => {
                        recallButton.style.backgroundColor = 'transparent';
                    };
                    
                    recallButton.onclick = async () => {
                        if (confirm('确定要撤回这条消息吗？')) {
                            const chat_type = document.querySelector('input[name="chat_type"]').value;
                            const result = await fetch('recall_message.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `chat_type=${chat_type}&message_id=${message.id}`
                            });
                            
                            const resultData = await result.json();
                            if (resultData.success) {
                                // 替换消息为撤回提示
                                const recallMessageDiv = document.createElement('div');
                                recallMessageDiv.className = 'recall-message';
                                recallMessageDiv.style.cssText = `
                                    color: #999;
                                    font-size: 12px;
                                    margin-top: 5px;
                                    text-align: ${isSent ? 'right' : 'left'};
                                `;
                                recallMessageDiv.textContent = `${new Date().toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' })}: ${isSent ? '您' : message.sender_username}撤回了一条消息`;
                                
                                // 清空消息内容
                                contentDiv.innerHTML = '';
                                contentDiv.appendChild(recallMessageDiv);
                                
                                // 添加重新编辑按钮（仅发送者可见）
                                if (isSent) {
                                    const editButton = document.createElement('button');
                                    editButton.textContent = '重新编辑';
                                    editButton.style.cssText = `
                                        background: none;
                                        border: none;
                                        color: #667eea;
                                        cursor: pointer;
                                        font-size: 12px;
                                        margin-top: 5px;
                                        padding: 2px 5px;
                                        border-radius: 3px;
                                        transition: background-color 0.2s;
                                    `;
                                    
                                    editButton.onmouseover = () => {
                                        editButton.style.backgroundColor = '#f0f0f0';
                                    };
                                    
                                    editButton.onmouseout = () => {
                                        editButton.style.backgroundColor = 'transparent';
                                    };
                                    
                                    editButton.onclick = () => {
                                        // 恢复消息内容到输入框
                                        const messageInput = document.getElementById('message-input');
                                        if (message.content) {
                                            messageInput.value = message.content;
                                        }
                                        // 聚焦输入框
                                        messageInput.focus();
                                        // 滚动到底部
                                        messageInput.scrollTop = messageInput.scrollHeight;
                                    };
                                    
                                    contentDiv.appendChild(editButton);
                                }
                            } else {
                                alert(resultData.message);
                            }
                        }
                    };
                    
                    menuContent.appendChild(recallButton);
                    
                    messageMenu.appendChild(menuButton);
                    messageMenu.appendChild(menuContent);
                    
                    // 显示/隐藏菜单
                    menuButton.onclick = (e) => {
                        e.stopPropagation();
                        menuContent.style.display = menuContent.style.display === 'block' ? 'none' : 'block';
                    };
                    
                    // 点击其他地方关闭菜单
                    document.addEventListener('click', () => {
                        menuContent.style.display = 'none';
                    });
                    
                    contentDiv.appendChild(messageMenu);
                }
            }
            
            messageDiv.appendChild(avatarDiv);
            messageDiv.appendChild(contentDiv);
            
            return messageDiv;
        }
        
        // 群聊@功能自动补全
        let groupMembers = [];
        let mentionDropdown = document.getElementById('mention-dropdown');
        let messageInput = document.getElementById('message-input');
        let isMentioning = false;
        let currentMentionIndex = -1;
        
        // 获取群聊成员列表
        async function fetchGroupMembers() {
            <?php if ($chat_type === 'group'): ?>
                try {
                    const response = await fetch(`get_group_members.php?group_id=<?php echo $selected_id; ?>`);
                    const data = await response.json();
                    if (data.success) {
                        groupMembers = data.members;
                    }
                } catch (error) {
                    console.error('获取群成员失败:', error);
                }
            <?php endif; ?>
        }
        
        // 初始化群成员数据
        fetchGroupMembers();
        
        // 显示@下拉列表
        function showMentionDropdown() {
            mentionDropdown.style.display = 'block';
        }
        
        // 隐藏@下拉列表
        function hideMentionDropdown() {
            mentionDropdown.style.display = 'none';
            isMentioning = false;
            currentMentionIndex = -1;
        }
        
        // 更新@下拉列表内容
        function updateMentionDropdown(filter = '') {
            if (!groupMembers.length) return;
            
            let filteredMembers = groupMembers;
            if (filter) {
                filteredMembers = groupMembers.filter(member => 
                    member.username.toLowerCase().includes(filter.toLowerCase())
                );
            }
            
            // 显示全部成员，不再限制数量
            // filteredMembers = filteredMembers.slice(0, 5);
            
            mentionDropdown.innerHTML = '';
            
            filteredMembers.forEach((member, index) => {
                const memberItem = document.createElement('div');
                memberItem.className = 'mention-item';
                memberItem.innerHTML = `
                    <div style="display: flex; align-items: center; padding: 10px; cursor: pointer; transition: background-color 0.2s;">
                        <div style="width: 30px; height: 30px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px; margin-right: 10px;">
                            ${member.username.charAt(0)}
                        </div>
                        <div>
                            <div style="font-weight: 500; font-size: 14px;">${member.username}</div>
                            ${member.is_owner ? '<div style="font-size: 12px; color: #ff4757;">群主</div>' : member.is_admin ? '<div style="font-size: 12px; color: #ffa502;">管理员</div>' : ''}
                        </div>
                    </div>
                `;
                
                // 添加悬停效果
                memberItem.addEventListener('touchstart', () => {
                    memberItem.style.backgroundColor = '#f0f0f0';
                });
                
                memberItem.addEventListener('touchend', () => {
                    memberItem.style.backgroundColor = 'transparent';
                });
                
                // 添加点击事件
                memberItem.addEventListener('click', () => {
                    insertMention(member.username);
                    hideMentionDropdown();
                });
                
                mentionDropdown.appendChild(memberItem);
            });
            
            if (filteredMembers.length > 0) {
                showMentionDropdown();
            } else {
                hideMentionDropdown();
            }
        }
        
        // 插入@用户名到输入框
        function insertMention(username) {
            const cursorPos = messageInput.selectionStart;
            const textBeforeCursor = messageInput.value.substring(0, cursorPos);
            const textAfterCursor = messageInput.value.substring(cursorPos);
            
            // 找到@符号的位置
            const atIndex = textBeforeCursor.lastIndexOf('@');
            if (atIndex !== -1) {
                // 替换@及之后的内容为@username
                const newText = textBeforeCursor.substring(0, atIndex) + '@' + username + ' ' + textAfterCursor;
                messageInput.value = newText;
                
                // 设置光标位置到@username之后
                const newCursorPos = atIndex + username.length + 2; // @ + username + 空格
                messageInput.focus();
                messageInput.setSelectionRange(newCursorPos, newCursorPos);
            }
        }
        
        // 消息输入框输入事件 - 处理@功能
        messageInput?.addEventListener('input', (e) => {
            if (!messageInput) return;
            
            const cursorPos = messageInput.selectionStart;
            const textBeforeCursor = messageInput.value.substring(0, cursorPos);
            
            // 检查最后一个@符号的位置
            const lastAtIndex = textBeforeCursor.lastIndexOf('@');
            
            // 检查@符号后面是否有空格或其他字符
            const textAfterAt = textBeforeCursor.substring(lastAtIndex + 1);
            const hasSpaceAfterAt = textAfterAt.includes(' ');
            
            if (lastAtIndex !== -1 && !hasSpaceAfterAt) {
                // 用户正在输入@
                isMentioning = true;
                const filter = textAfterAt;
                updateMentionDropdown(filter);
            } else {
                // 用户没有在输入@或者@后面有空格
                hideMentionDropdown();
            }
        });
        
        // 消息输入框键盘事件 - Enter发送，Shift+Enter换行
        messageInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                hideMentionDropdown();
                document.getElementById('message-form')?.dispatchEvent(new Event('submit'));
            } else if (e.key === 'Escape') {
                // 按ESC键隐藏下拉列表
                hideMentionDropdown();
            } else if (e.key === 'ArrowUp') {
                // 按上箭头选择上一个成员
                e.preventDefault();
                if (isMentioning && mentionDropdown?.children.length > 0) {
                    currentMentionIndex = Math.max(0, currentMentionIndex - 1);
                    highlightMentionItem(currentMentionIndex);
                }
            } else if (e.key === 'ArrowDown') {
                // 按下箭头选择下一个成员
                e.preventDefault();
                if (isMentioning && mentionDropdown?.children.length > 0) {
                    currentMentionIndex = Math.min(mentionDropdown.children.length - 1, currentMentionIndex + 1);
                    highlightMentionItem(currentMentionIndex);
                }
            } else if (e.key === 'Tab' || e.key === 'Enter') {
                // 按Tab或Enter键选择当前高亮的成员
                if (isMentioning && currentMentionIndex >= 0 && mentionDropdown?.children.length > 0) {
                    e.preventDefault();
                    const selectedMember = groupMembers[currentMentionIndex];
                    insertMention(selectedMember.username);
                    hideMentionDropdown();
                }
            }
        });
        
        // 高亮@列表中的当前选中项
        function highlightMentionItem(index) {
            if (!mentionDropdown) return;
            
            // 移除所有高亮
            Array.from(mentionDropdown.children).forEach(item => {
                item.style.backgroundColor = 'transparent';
            });
            
            // 添加当前项高亮
            if (index >= 0 && index < mentionDropdown.children.length) {
                mentionDropdown.children[index].style.backgroundColor = '#e0e0e0';
                // 滚动到可视区域
                mentionDropdown.children[index].scrollIntoView({ block: 'nearest' });
            }
        }
        
        // 点击页面其他地方隐藏@下拉列表
        document.addEventListener('click', (e) => {
            if (messageInput && mentionDropdown && !messageInput.contains(e.target) && !mentionDropdown.contains(e.target)) {
                hideMentionDropdown();
            }
        });
        
        // 添加发送状态锁和消息队列，确保消息按顺序发送
        let isSending = false;
        const messageQueue = [];
        
        // 发送消息队列中的下一条消息
        async function processMessageQueue() {
            if (isSending || messageQueue.length === 0) {
                return;
            }
            
            // 设置发送状态为true
            isSending = true;
            
            // 从队列中取出第一条消息
            const queueItem = messageQueue.shift();
            const { formData, messageText, file, tempMessage, messageInput, messagesContainer, originalForm } = queueItem;
            
            try {
                // 发送消息请求
                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 清空输入
                    messageInput.value = '';
                    document.getElementById('file-input').value = '';
                    
                    // 替换临时消息为真实消息
                    tempMessage.remove();
                    const isSent = result.message.sender_id == <?php echo $user_id; ?>;
                    const newMessage = createMessage(result.message, isSent);
                    messagesContainer.appendChild(newMessage);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    
                    // 更新 lastMessageId，避免重复获取消息
                    if (result.message.id > lastMessageId) {
                        lastMessageId = result.message.id;
                    }
                } else {
                    // 显示错误并移除临时消息
                    tempMessage.remove();
                    alert(result.message);
                }
            } catch (error) {
                console.error('发送消息失败:', error);
                // 移除临时消息并显示错误
                tempMessage.remove();
                alert('发送消息失败，请稍后重试');
            } finally {
                // 设置发送状态为false
                isSending = false;
                
                // 处理队列中的下一条消息
                processMessageQueue();
            }
        }
        
        // 截图功能
        async function takeScreenshot() {
            try {
                // 请求屏幕捕获
                const stream = await navigator.mediaDevices.getDisplayMedia({
                    video: { cursor: 'always' },
                    audio: false
                });
                
                // 创建视频元素来显示流
                const video = document.createElement('video');
                video.srcObject = stream;
                
                // 使用Promise确保视频元数据加载完成
                await new Promise((resolve) => {
                    video.onloadedmetadata = resolve;
                });
                
                // 播放视频
                await video.play();
                
                // 创建Canvas元素
                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                
                // 绘制视频帧到Canvas
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                
                // 停止流
                stream.getTracks().forEach(track => track.stop());
                
                // 将Canvas转换为Blob，使用Promise处理
                const blob = await new Promise((resolve) => {
                    canvas.toBlob(resolve, 'image/png');
                });
                
                if (blob) {
                    // 创建文件对象
                    const screenshotFile = new File([blob], `screenshot_${Date.now()}.png`, {
                        type: 'image/png'
                    });
                    
                    // 创建DataTransfer对象
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(screenshotFile);
                    
                    // 将文件添加到file-input中
                    const fileInput = document.getElementById('file-input');
                    if (fileInput) {
                        fileInput.files = dataTransfer.files;
                        
                        // 触发change事件，生成预览以及提交
                        const changeEvent = new Event('change', { bubbles: true });
                        fileInput.dispatchEvent(changeEvent);
                    } else {
                        console.error('未找到file-input元素');
                        alert('截图失败：未找到文件输入元素');
                    }
                } else {
                    console.error('Canvas转换为Blob失败');
                    alert('截图失败：无法处理截图数据');
                }
            } catch (error) {
                console.error('截图失败:', error);
                // 根据错误类型提供更具体的提示
                if (error.name === 'NotAllowedError') {
                    alert('截图失败：您拒绝了屏幕捕获请求');
                } else if (error.name === 'NotFoundError') {
                    alert('截图失败：未找到可捕获的屏幕');
                } else if (error.name === 'NotReadableError') {
                    alert('截图失败：无法访问屏幕内容');
                } else {
                    alert(`截图失败：${error.message || '请重试'}`);
                }
            }
        }
        
        // 添加Ctrl+Alt+D快捷键监听
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.altKey && e.key === 'd') {
                e.preventDefault();
                takeScreenshot();
            }
        });
        
        // 发送消息
        document.getElementById('message-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // 防止重复提交：如果正在发送消息或队列中有消息，直接返回
            if (isSending || messageQueue.length > 0) {
                return;
            }
            
            const formData = new FormData(e.target);
            const messageInput = document.getElementById('message-input');
            const messagesContainer = document.getElementById('messages-container');
            
            const messageText = messageInput.value.trim();
            const file = document.getElementById('file-input').files[0];
            
            if (!messageText && !file) {
                return;
            }
            
            // 验证消息内容，禁止HTML标签
            if (messageText && /<[^>]*>/.test(messageText)) {
                alert('消息中不能包含HTML标签');
                return;
            }
            
            // 文件大小验证（从配置中获取）
            const maxFileSize = <?php echo getConfig('upload_files_max', 150); ?> * 1024 * 1024;
            if (file && file.size > maxFileSize) {
                alert('文件大小不能超过' + <?php echo getConfig('upload_files_max', 150); ?> + 'MB');
                return;
            }
            
            // 创建临时消息对象
            const tempMessageData = {
                content: messageText,
                file_path: file ? URL.createObjectURL(file) : null,
                file_name: file ? file.name : null,
                created_at: new Date().toISOString()
            };
            
            // 创建临时消息元素
            const tempMessage = createMessage(tempMessageData, true);
            tempMessage.style.opacity = '0.7'; // 临时消息半透明显示
            messagesContainer.appendChild(tempMessage);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // 将消息添加到队列
            messageQueue.push({
                formData,
                messageText,
                file,
                tempMessage,
                messageInput,
                messagesContainer,
                originalForm: e.target
            });
            
            // 开始处理消息队列
            processMessageQueue();
        });
        
        // 封禁检查和处理
        function checkBanStatus() {
            fetch('check_ban_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.banned) {
                        showBanNotification(data.reason, data.expires_at);
                    }
                })
                .catch(error => {
                    console.error('检查封禁状态失败:', error);
                });
        }
        
        // 显示封禁通知
        function showBanNotification(reason, expires_at) {
            const modal = document.getElementById('ban-notification-modal');
            const reasonEl = document.getElementById('ban-reason');
            const countdownEl = document.getElementById('ban-countdown');
            
            reasonEl.textContent = `原因：${reason}，预计解封时间：${expires_at}`;
            modal.style.display = 'flex';
            
            // 倒计时退出
            let countdown = 10;
            countdownEl.textContent = countdown;
            
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownEl.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'logout.php';
                }
            }, 1000);
        }
        
        // 页面加载完成后立即检查一次封禁状态和协议同意状态
        document.addEventListener('DOMContentLoaded', () => {
            // 初始封禁检查
            <?php if ($ban_info): ?>
                showBanNotification('<?php echo $ban_info['reason']; ?>', '<?php echo $ban_info['expires_at']; ?>');
            <?php endif; ?>
            
            // 检查用户是否同意协议
            <?php if (!$agreed_to_terms): ?>
                showTermsAgreementModal();
            <?php endif; ?>
            
            // 每30秒检查一次封禁状态
            setInterval(checkBanStatus, 30000);
        });
        
        // 显示协议同意弹窗
        function showTermsAgreementModal() {
            const modal = document.getElementById('terms-agreement-modal');
            modal.style.display = 'flex';
        }
        
        // 同意协议
        document.getElementById('agree-terms-btn')?.addEventListener('click', async () => {
            try {
                // 发送请求更新协议同意状态
                const response = await fetch('update_terms_agreement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'agreed=true'
                });
                
                const result = await response.json();
                if (result.success) {
                    // 隐藏弹窗
                    const modal = document.getElementById('terms-agreement-modal');
                    modal.style.display = 'none';
                } else {
                    alert('更新协议同意状态失败，请刷新页面重试');
                }
            } catch (error) {
                console.error('同意协议失败:', error);
                alert('同意协议失败，请刷新页面重试');
            }
        });
        
        // 不同意协议并注销账号
        document.getElementById('disagree-terms-btn')?.addEventListener('click', async () => {
            if (confirm('确定要注销账号吗？此操作不可恢复。')) {
                try {
                    // 发送请求注销账号
                    const response = await fetch('delete_account.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        }
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        // 注销成功，跳转到登录页面
                        window.location.href = 'login.php?message=' + encodeURIComponent('账号已注销');
                    } else {
                        alert('注销账号失败，请刷新页面重试');
                    }
                } catch (error) {
                    console.error('注销账号失败:', error);
                    alert('注销账号失败，请刷新页面重试');
                }
            }
        });
        
        // 文件选择事件
        document.getElementById('file-input')?.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('message-form').dispatchEvent(new Event('submit'));
            }
        });
        
        // 封禁检查和处理
        function checkBanStatus() {
            fetch('check_ban_status.php')
                .then(response => response.json())
                .then(data => {
                    if (data.banned) {
                        showBanNotification(data.reason, data.expires_at);
                    }
                })
                .catch(error => {
                    console.error('检查封禁状态失败:', error);
                });
        }
        
        // 显示封禁通知
        function showBanNotification(reason, expires_at) {
            const modal = document.getElementById('ban-notification-modal');
            const reasonEl = document.getElementById('ban-reason');
            const countdownEl = document.getElementById('ban-countdown');
            
            reasonEl.textContent = `原因：${reason}，预计解封时间：${expires_at}`;
            modal.style.display = 'flex';
            
            // 倒计时退出
            let countdown = 10;
            countdownEl.textContent = countdown;
            
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownEl.textContent = countdown;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    window.location.href = 'logout.php';
                }
            }, 1000);
        }
        
        // 页面加载完成后立即检查一次封禁状态
        document.addEventListener('DOMContentLoaded', () => {
            // 初始封禁检查
            <?php if ($ban_info): ?>
                showBanNotification('<?php echo $ban_info['reason']; ?>', '<?php echo $ban_info['expires_at']; ?>');
            <?php endif; ?>
            
            // 每30秒检查一次封禁状态
            setInterval(checkBanStatus, 30000);
        });
        
        // 实时更新消息
        let lastMessageId = <?php echo end($chat_history)['id'] ?? 0; ?>;
        
        function fetchNewMessages() {
            // 动态获取当前聊天类型和选中的ID
            const chatType = document.querySelector('input[name="chat_type"]')?.value;
            const selectedId = document.querySelector('input[name="id"]')?.value;
            
            if (chatType && selectedId) {
                let url = '';
                
                if (chatType === 'friend') {
                    url = `get_new_messages.php?friend_id=${selectedId}&last_message_id=${lastMessageId}`;
                } else {
                    url = `get_new_group_messages.php?group_id=${selectedId}&last_message_id=${lastMessageId}`;
                }
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.messages.length > 0) {
                            const messagesContainer = document.getElementById('messages-container');
                            let hasNewMessages = false;
                            
                            data.messages.forEach(msg => {
                    // 检查消息是否已经存在，避免重复添加
                    const existingMessages = messagesContainer.querySelectorAll('.message');
                    let messageExists = false;
                    
                    for (const existingMsg of existingMessages) {
                        // 获取现有消息的时间和内容，用于比较
                        const existingTime = existingMsg.querySelector('.message-time')?.textContent;
                        const existingContent = existingMsg.querySelector('.message-text')?.textContent;
                        
                        // 如果是自己发送的消息，比较消息内容和时间
                        if (msg.sender_id == <?php echo $user_id; ?>) {
                            const newTime = new Date(msg.created_at).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
                            if (existingContent === msg.content && existingTime === newTime) {
                                messageExists = true;
                                break;
                            }
                        }
                    }
                    
                    // 如果消息不存在，添加到容器中
                    if (!messageExists) {
                        // 添加所有新消息，包括自己发送的和其他成员发送的
                        const isSent = msg.sender_id == <?php echo $user_id; ?>;
                        const newMessage = createMessage(msg, isSent);
                        messagesContainer.appendChild(newMessage);
                        hasNewMessages = true;
                    }
                    
                    // 更新lastMessageId为最新消息ID
                    if (msg.id > lastMessageId) {
                        lastMessageId = msg.id;
                    }
                });
                            
                            if (hasNewMessages) {
                                // 滚动到底部
                                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                            }
                        }
                    })
                    .catch(error => console.error('获取新消息失败:', error));
                
                // 定期检查群聊禁言状态
                if (chatType === 'group') {
                    loadChatMuteStatus();
                }
            }
        }
        
        // 每3秒获取一次新消息
        setInterval(fetchNewMessages, 3000);
        
        // 更新群聊禁言状态
        async function updateChatMuteStatus(isMuted) {
            const muteNotice = document.getElementById('group-mute-notice');
            const inputContainer = document.querySelector('.input-area');
            
            if (isMuted) {
                // 显示禁言提示
                muteNotice.style.display = 'block';
                // 禁用输入区域
                inputContainer.style.display = 'none';
            } else {
                // 隐藏禁言提示
                muteNotice.style.display = 'none';
                // 启用输入区域
                inputContainer.style.display = 'block';
            }
        }
        
        // 加载群聊禁言状态
        async function loadChatMuteStatus() {
            const chatType = document.querySelector('input[name="chat_type"]')?.value;
            const chatId = document.querySelector('input[name="id"]')?.value;
            
            if (chatType === 'group' && chatId) {
                try {
                    const response = await fetch(`get_group_mute_status.php?group_id=${chatId}`);
                    const data = await response.json();
                    if (data.success) {
                        updateChatMuteStatus(data.is_muted);
                    }
                } catch (error) {
                    console.error('加载群聊禁言状态失败:', error);
                }
            }
        }
        
        // 聊天菜单功能
        function toggleChatMenu() {
            const menu = document.getElementById('chat-menu');
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
        
        // 点击其他地方关闭聊天菜单
        document.addEventListener('click', (e) => {
            const chatMenu = document.getElementById('chat-menu');
            const chatMenuBtn = document.querySelector('.chat-menu-btn');
            if (chatMenu && chatMenuBtn && chatMenu.style.display === 'block' && 
                !chatMenu.contains(e.target) && !chatMenuBtn.contains(e.target)) {
                chatMenu.style.display = 'none';
            }
        });
        
        // 删除好友
        function deleteFriend(friendId) {
            if (confirm('确定要删除这个好友吗？删除后将无法恢复。')) {
                fetch(`delete_friend.php?friend_id=${friendId}`, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('好友已成功删除');
                        window.location.href = 'mobilechat.php';
                    } else {
                        alert('删除好友失败：' + data.message);
                    }
                });
            }
        }
        
        // 查看群成员
        function showGroupMembers(groupId) {
            // 创建群成员弹窗
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            
            const modalContent = document.createElement('div');
            modalContent.style.cssText = `
                background: white;
                border-radius: 12px;
                width: 90%;
                max-width: 500px;
                max-height: 80vh;
                overflow: auto;
            `;
            
            // 弹窗标题
            const modalHeader = document.createElement('div');
            modalHeader.style.cssText = `
                padding: 15px;
                border-bottom: 1px solid #e0e0e0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 12px 12px 0 0;
            `;
            
            const modalTitle = document.createElement('h3');
            modalTitle.style.cssText = `
                margin: 0;
                font-size: 16px;
                font-weight: 600;
            `;
            modalTitle.textContent = '群成员';
            
            const closeBtn = document.createElement('button');
            closeBtn.style.cssText = `
                background: none;
                border: none;
                font-size: 24px;
                color: white;
                cursor: pointer;
                padding: 0;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                transition: background-color 0.2s;
            `;
            closeBtn.textContent = '×';
            closeBtn.onclick = () => modal.remove();
            closeBtn.onmouseover = () => closeBtn.style.backgroundColor = 'rgba(255, 255, 255, 0.2)';
            closeBtn.onmouseout = () => closeBtn.style.backgroundColor = 'transparent';
            
            modalHeader.appendChild(modalTitle);
            modalHeader.appendChild(closeBtn);
            modalContent.appendChild(modalHeader);
            
            // 加载群成员
            const membersList = document.createElement('div');
            membersList.style.cssText = `
                padding: 15px;
            `;
            
            const loadingText = document.createElement('p');
            loadingText.textContent = '加载群成员中...';
            loadingText.style.cssText = `
                text-align: center;
                color: #666;
                padding: 20px;
            `;
            membersList.appendChild(loadingText);
            
            // 点击其他地方关闭所有菜单
            document.addEventListener('click', (e) => {
                const allMenus = document.querySelectorAll('.member-menu');
                allMenus.forEach(menu => menu.style.display = 'none');
            });
            
            // 获取群成员
            fetch(`get_group_members.php?group_id=${groupId}`)
                .then(response => response.json())
                .then(data => {
                    membersList.innerHTML = '';
                    
                    if (data.success) {
                        data.members.forEach(member => {
                            const memberDiv = document.createElement('div');
                            memberDiv.style.cssText = `
                                display: flex;
                                align-items: center;
                                padding: 12px;
                                border-bottom: 1px solid #f0f0f0;
                                position: relative;
                            `;
                            
                            const memberAvatar = document.createElement('div');
                            memberAvatar.style.cssText = `
                                width: 40px;
                                height: 40px;
                                border-radius: 50%;
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                color: white;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-weight: 600;
                                font-size: 16px;
                                margin-right: 12px;
                            `;
                            memberAvatar.textContent = member.username.substring(0, 2);
                            
                            const memberInfo = document.createElement('div');
                            memberInfo.style.cssText = `
                                flex: 1;
                            `;
                            
                            const memberName = document.createElement('div');
                            memberName.style.cssText = `
                                font-weight: 600;
                                color: #333;
                                margin-bottom: 2px;
                            `;
                            memberName.textContent = member.username;
                            
                            const memberRole = document.createElement('div');
                            memberRole.style.cssText = `
                                font-size: 12px;
                                color: #666;
                            `;
                            memberRole.textContent = member.is_owner ? '群主' : (member.is_admin ? '管理员' : '成员');
                            
                            memberInfo.appendChild(memberName);
                            memberInfo.appendChild(memberRole);
                            
                            // 成员操作菜单
                            const menuButton = document.createElement('button');
                            menuButton.style.cssText = `
                                background: none;
                                border: none;
                                font-size: 18px;
                                color: #666;
                                cursor: pointer;
                                padding: 5px;
                                border-radius: 50%;
                                transition: background-color 0.2s;
                                z-index: 10001;
                            `;
                            menuButton.textContent = '⋮';
                            menuButton.onclick = (e) => {
                                e.stopPropagation();
                                // 关闭其他菜单
                                const allMenus = document.querySelectorAll('.member-menu');
                                allMenus.forEach(menu => menu.style.display = 'none');
                                // 显示当前菜单
                                const menu = menuButton.nextElementSibling;
                                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                            };
                            
                            // 菜单容器
                            const memberMenu = document.createElement('div');
                            memberMenu.className = 'member-menu';
                            memberMenu.style.cssText = `
                                position: absolute;
                                top: 50%;
                                right: 40px;
                                transform: translateY(-50%);
                                background: white;
                                border: 1px solid #e0e0e0;
                                border-radius: 8px;
                                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
                                z-index: 10002;
                                display: none;
                                min-width: 120px;
                            `;
                            
                            // 发送好友申请按钮
                            const addFriendBtn = document.createElement('button');
                            addFriendBtn.style.cssText = `
                                display: block;
                                width: 100%;
                                padding: 10px 15px;
                                background: none;
                                border: none;
                                text-align: left;
                                font-size: 14px;
                                color: #333;
                                cursor: pointer;
                                border-radius: 8px;
                                transition: background-color 0.2s;
                            `;
                            addFriendBtn.textContent = '发送好友申请';
                            addFriendBtn.onclick = (e) => {
                                e.stopPropagation();
                                sendFriendRequest(member.id, member.username);
                                // 关闭菜单
                                memberMenu.style.display = 'none';
                            };
                            addFriendBtn.onmouseover = () => addFriendBtn.style.backgroundColor = '#f0f0f0';
                            addFriendBtn.onmouseout = () => addFriendBtn.style.backgroundColor = 'transparent';
                            
                            // 添加按钮到菜单
                            memberMenu.appendChild(addFriendBtn);
                            
                            // 组装成员项
                            memberDiv.appendChild(memberAvatar);
                            memberDiv.appendChild(memberInfo);
                            memberDiv.appendChild(menuButton);
                            memberDiv.appendChild(memberMenu);
                            membersList.appendChild(memberDiv);
                        });
                    } else {
                        const errorText = document.createElement('p');
                        errorText.textContent = '加载群成员失败';
                        errorText.style.cssText = `
                            text-align: center;
                            color: #ff4757;
                            padding: 20px;
                        `;
                        membersList.appendChild(errorText);
                    }
                })
                .catch(error => {
                    membersList.innerHTML = '';
                    const errorText = document.createElement('p');
                    errorText.textContent = '网络错误，加载群成员失败';
                    errorText.style.cssText = `
                        text-align: center;
                        color: #ff4757;
                        padding: 20px;
                    `;
                    membersList.appendChild(errorText);
                });
            
            modalContent.appendChild(membersList);
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
        }
        
        // 发送好友申请
        function sendFriendRequest(userId, username) {
            if (confirm(`确定要向 ${username} 发送好友申请吗？`)) {
                fetch('send_friend_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `friend_id=${userId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('好友申请已发送');
                    } else {
                        alert('发送失败：' + data.message);
                    }
                })
                .catch(error => {
                    console.error('发送好友申请失败:', error);
                    alert('网络错误，请稍后重试');
                });
            }
        }
        
        // 退出群聊
        function leaveGroup(groupId) {
            if (confirm('确定要退出这个群聊吗？退出后将无法恢复。')) {
                fetch(`leave_group.php?group_id=${groupId}`, { method: 'POST' })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('已成功退出群聊');
                        window.location.href = 'mobilechat.php';
                    } else {
                        alert('退出群聊失败：' + data.message);
                    }
                });
            }
        }
    </script>
    <!-- 封禁提示弹窗 -->
    <div id="ban-notification-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 5000; flex-direction: column; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; text-align: center;">
            <h2 style="color: #d32f2f; margin-bottom: 20px; font-size: 24px;">账号已被封禁</h2>
            <p style="color: #666; margin-bottom: 15px; font-size: 16px;">您的账号已被封禁，即将退出登录</p>
            <p id="ban-reason" style="color: #333; margin-bottom: 20px; font-weight: 500;"></p>
            <p id="ban-countdown" style="color: #d32f2f; font-size: 36px; font-weight: bold; margin-bottom: 20px;">10</p>
            <p style="color: #999; font-size: 14px;">如有疑问请联系管理员</p>
        </div>
    </div>
    </div>
    <!-- 图片查看器功能 -->
    <script>
        // 直接在页面加载完成后绑定事件监听器，不依赖DOMContentLoaded
        window.onload = function() {
            console.log('页面加载完成，开始绑定图片查看器事件');
            
            // 图片查看器功能
            const imageViewer = document.getElementById('imageViewer');
            const imageViewerContent = document.getElementById('imageViewerContent');
            const imageViewerClose = document.getElementById('imageViewerClose');
            
            console.log('获取到的元素:', {
                imageViewer: !!imageViewer,
                imageViewerContent: !!imageViewerContent,
                imageViewerClose: !!imageViewerClose
            });
            
            // 双指缩放相关变量
            let initialDistance = null;
            let currentScale = 1;
            let lastScale = 1;
            
            // 拖拽相关变量
            let isDragging = false;
            let startX = 0;
            let startY = 0;
            let translateX = 0;
            let translateY = 0;
            let lastTranslateX = 0;
            let lastTranslateY = 0;
            
            // 点击图片放大
            document.addEventListener('click', function(e) {
                if (e.target.tagName === 'IMG' && e.target.closest('.message-content')) {
                    e.preventDefault();
                    const imgSrc = e.target.src;
                    imageViewerContent.src = imgSrc;
                    // 重置缩放和拖拽状态
                    currentScale = 1;
                    lastScale = 1;
                    translateX = 0;
                    translateY = 0;
                    lastTranslateX = 0;
                    lastTranslateY = 0;
                    imageViewerContent.style.transform = 'translate(-50%, -50%) scale(1)';
                    imageViewer.classList.add('active');
                    console.log('图片查看器已打开');
                }
            });
            
            // 关闭查看器的函数
            function closeImageViewer() {
                console.log('开始关闭图片查看器');
                if (imageViewer) {
                    imageViewer.classList.remove('active');
                    console.log('移除了active类');
                }
                if (imageViewerContent) {
                    imageViewerContent.src = '';
                    console.log('清空了图片src');
                }
                // 重置缩放和拖拽状态
                currentScale = 1;
                lastScale = 1;
                translateX = 0;
                translateY = 0;
                lastTranslateX = 0;
                lastTranslateY = 0;
                if (imageViewerContent) {
                    imageViewerContent.style.transform = 'translate(-50%, -50%) scale(1)';
                    console.log('重置了图片变换');
                }
                console.log('图片查看器已关闭');
            }
            
            // 直接为关闭按钮添加点击事件，不依赖DOMContentLoaded
            const closeBtn = document.getElementById('imageViewerClose');
            if (closeBtn) {
                console.log('找到了关闭按钮，绑定点击事件');
                closeBtn.onclick = function() {
                    console.log('关闭按钮被点击');
                    closeImageViewer();
                };
            }
            
            // 点击图片关闭查看器
            if (imageViewerContent) {
                imageViewerContent.addEventListener('click', function(e) {
                    console.log('图片被点击，尝试关闭查看器');
                    closeImageViewer();
                });
            }
            
            // 点击查看器背景或非图片区域关闭
            if (imageViewer) {
                imageViewer.addEventListener('click', function(e) {
                    console.log('查看器被点击，尝试关闭查看器');
                    // 如果点击的是查看器本身（背景），关闭查看器
                    // 图片和关闭按钮有自己的事件处理，不需要在这里处理
                    if (e.target === imageViewer) {
                        closeImageViewer();
                    }
                });
            }
            
            // 键盘ESC键关闭
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && imageViewer && imageViewer.classList.contains('active')) {
                    console.log('ESC键被按下，尝试关闭查看器');
                    closeImageViewer();
                }
            });
            
            // 触摸开始事件 - 记录初始位置和变换
            if (imageViewer) {
                imageViewer.addEventListener('touchstart', function(e) {
                    // 只在双指触摸时才阻止默认行为，单指触摸允许点击事件
                    if (e.touches.length === 2) {
                        e.preventDefault();
                    }
                    
                    if (e.touches.length === 1) {
                        // 单指触摸 - 准备拖拽
                        isDragging = true;
                        startX = e.touches[0].clientX;
                        startY = e.touches[0].clientY;
                        lastTranslateX = translateX;
                        lastTranslateY = translateY;
                    } else if (e.touches.length === 2) {
                        // 双指触摸 - 准备缩放
                        isDragging = false;
                        const touch1 = e.touches[0];
                        const touch2 = e.touches[1];
                        // 计算两指初始距离
                        initialDistance = Math.sqrt(
                            Math.pow(touch2.clientX - touch1.clientX, 2) +
                            Math.pow(touch2.clientY - touch1.clientY, 2)
                        );
                        lastScale = currentScale;
                        lastTranslateX = translateX;
                        lastTranslateY = translateY;
                    }
                }, { passive: false });
                
                // 触摸移动事件 - 计算缩放和拖拽
                imageViewer.addEventListener('touchmove', function(e) {
                    // 只在真正进行拖拽或缩放时才阻止默认行为
                    if ((e.touches.length === 1 && isDragging) || e.touches.length === 2) {
                        e.preventDefault();
                    }
                    
                    if (e.touches.length === 1 && isDragging) {
                        // 单指触摸 - 拖拽
                        const currentX = e.touches[0].clientX;
                        const currentY = e.touches[0].clientY;
                        
                        // 计算拖拽距离
                        const deltaX = currentX - startX;
                        const deltaY = currentY - startY;
                        
                        // 更新拖拽位置
                        translateX = lastTranslateX + deltaX;
                        translateY = lastTranslateY + deltaY;
                        
                        // 应用变换
                        imageViewerContent.style.transform = `translate(${translateX}px, ${translateY}px) translate(-50%, -50%) scale(${currentScale})`;
                    } else if (e.touches.length === 2) {
                        // 双指触摸 - 缩放
                        isDragging = false;
                        const touch1 = e.touches[0];
                        const touch2 = e.touches[1];
                        
                        // 计算两指当前距离
                        const currentDistance = Math.sqrt(
                            Math.pow(touch2.clientX - touch1.clientX, 2) +
                            Math.pow(touch2.clientY - touch1.clientY, 2)
                        );
                        
                        if (initialDistance) {
                            // 计算缩放比例
                            const scale = (currentDistance / initialDistance) * lastScale;
                            // 限制缩放范围（0.5 - 3倍）
                            currentScale = Math.min(Math.max(0.5, scale), 3);
                            
                            // 计算两指中心点
                            const centerX = (touch1.clientX + touch2.clientX) / 2;
                            const centerY = (touch1.clientY + touch2.clientY) / 2;
                            
                            // 计算相对于图片中心点的偏移
                            const imgRect = imageViewerContent.getBoundingClientRect();
                            const imgCenterX = imgRect.left + imgRect.width / 2;
                            const imgCenterY = imgRect.top + imgRect.height / 2;
                            
                            // 计算缩放时的位移补偿
                            const offsetX = (centerX - imgCenterX) * (currentScale / lastScale - 1);
                            const offsetY = (centerY - imgCenterY) * (currentScale / lastScale - 1);
                            
                            // 更新拖拽位置
                            translateX = lastTranslateX + offsetX;
                            translateY = lastTranslateY + offsetY;
                            
                            // 应用变换
                            imageViewerContent.style.transform = `translate(${translateX}px, ${translateY}px) translate(-50%, -50%) scale(${currentScale})`;
                        }
                    }
                }, { passive: false });
                
                // 触摸结束事件 - 重置状态
                imageViewer.addEventListener('touchend', function(e) {
                    // 移除preventDefault()，允许点击事件触发
                    // e.preventDefault();
                    
                    if (e.touches.length === 0) {
                        // 所有手指离开屏幕
                        isDragging = false;
                        initialDistance = null;
                        
                        // 限制拖拽范围，确保图片不会拖出太多
                        const imgRect = imageViewerContent.getBoundingClientRect();
                        const viewerRect = imageViewer.getBoundingClientRect();
                        
                        // 计算图片相对于视口的尺寸
                        const imgWidth = imgRect.width;
                        const imgHeight = imgRect.height;
                        
                        // 计算最大允许拖拽距离
                        const maxDragX = (imgWidth - viewerRect.width) / 2;
                        const maxDragY = (imgHeight - viewerRect.height) / 2;
                        
                        // 限制拖拽范围
                        translateX = Math.min(Math.max(-maxDragX, translateX), maxDragX);
                        translateY = Math.min(Math.max(-maxDragY, translateY), maxDragY);
                        
                        // 应用最终变换
                        imageViewerContent.style.transform = `translate(${translateX}px, ${translateY}px) translate(-50%, -50%) scale(${currentScale})`;
                    }
                }, { passive: false });
            }
            
            console.log('图片查看器事件绑定完成');
        };
    </script>
</body>
</html>