<?php
require_once __DIR__ . '/includes/config_helper.php';
header('Content-Type: application/json');

session_start();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $key = isset($input['key']) ? trim($input['key']) : '';
    $adminKey = getEnvVar('ADMIN_KEY', 'zzzz9999');
    if ($key === $adminKey) {
        $_SESSION['is_admin'] = true;
        echo json_encode(['success' => true, 'msg' => '登录成功']);
    } else {
        echo json_encode(['success' => false, 'msg' => '密钥错误']);
    }
    exit;
}

if ($method === 'GET') {
    $logged_in = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    echo json_encode(['logged_in' => $logged_in]);
    exit;
}

echo json_encode(['success' => false, 'msg' => '不支持的请求']);
