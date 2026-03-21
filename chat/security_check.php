<?php
// Security check file
// 检查系统是否已安装 (API 专用)
if (file_exists(__DIR__ . '/lock')) {
    // 如果是 API 请求 (通过 Accept 头或文件扩展名判断)
    $is_api = false;
    
    // 简单判断：如果请求的文件是 .php 且不是 install.php
    $current_file = basename($_SERVER['PHP_SELF']);
    if ($current_file !== 'install.php') {
        // 检查 Accept 头
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $is_api = true;
        }
        
        // 检查常见 API 文件名模式
        if (strpos($current_file, 'get_') === 0 || strpos($current_file, 'send_') === 0 || strpos($current_file, '_process.php') !== false || strpos($current_file, 'api.php') !== false) {
            $is_api = true;
        }
    }

    if ($is_api) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'System not installed (lock file exists)', 'code' => 503]);
        exit;
    }
}
?>