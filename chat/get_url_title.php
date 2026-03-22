<?php
require_once 'security_check.php';
require_once 'config.php';

// 检查是否登�?
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_GET['url'])) {
    echo json_encode(['title' => null, 'embeddable' => true]);
    exit;
}

$url = $_GET['url'];

// 简单的 URL 验证
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['title' => null, 'embeddable' => true]);
    exit;
}

// 初始�?CURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
// 获取 Header
curl_setopt($ch, CURLOPT_HEADER, 1);
// 获取�?32KB
curl_setopt($ch, CURLOPT_RANGE, '0-32768'); 

$response = curl_exec($ch);
$error = curl_error($ch);

$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header_text = substr($response, 0, $header_size);
$body = substr($response, $header_size);

curl_close($ch);

$title = null;
$embeddable = true;

// 检�?Header 中的 X-Frame-Options �?Content-Security-Policy
if ($header_text) {
    // 检�?X-Frame-Options
    if (preg_match('/x-frame-options:\s*(DENY|SAMEORIGIN)/i', $header_text)) {
        $embeddable = false;
    }
    
    // 检�?CSP
    if (preg_match('/content-security-policy:.*frame-ancestors\s+([^\r\n]+)/i', $header_text, $matches)) {
        $ancestors = $matches[1];
        if (stripos($ancestors, "'none'") !== false || stripos($ancestors, "'self'") !== false) {
             $embeddable = false;
        }
    }
}

// 获取标题
if ($body) {
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
        $title = trim($matches[1]);
        
        $charset = 'UTF-8';
        if (preg_match('/<meta[^>]+charset=["\']?([a-zA-Z0-9\-]+)["\']?/i', $body, $match_charset)) {
            $charset = $match_charset[1];
        }
        
        if (strtoupper($charset) !== 'UTF-8') {
             $tmp_title = @mb_convert_encoding($title, 'UTF-8', $charset);
             if ($tmp_title) $title = $tmp_title;
        } else {
             $encoding = mb_detect_encoding($title, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ASCII'], true);
             if ($encoding && $encoding !== 'UTF-8') {
                 $title = mb_convert_encoding($title, 'UTF-8', $encoding);
             }
        }
        
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

echo json_encode([
    'title' => $title,
    'embeddable' => $embeddable
]);
?>