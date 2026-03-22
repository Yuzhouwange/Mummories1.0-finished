<?php
// 添加错误报告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "开始更新Service Worker缓存...\n";

// 定义头像目录
$avatar_dir = 'uploads/avatars/';

// 检查头像目录是否存在
if (!is_dir($avatar_dir)) {
    echo "头像目录不存在，创建目录...\n";
    mkdir($avatar_dir, 0755, true);
    echo "目录创建成功\n";
}

// 获取头像目录下的所有图片文件
$image_files = [];
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

$files = scandir($avatar_dir);
if ($files) {
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $file_path = $avatar_dir . $file;
        $file_info = pathinfo($file);
        $extension = strtolower($file_info['extension']);
        
        if (in_array($extension, $allowed_extensions) && is_file($file_path)) {
            // 转换为相对路径，用于Service Worker缓存
            $relative_path = '/' . $file_path;
            $image_files[] = $relative_path;
        }
    }
}

echo "找到 " . count($image_files) . " 个头像文件\n";

// 读取现有的service-worker.js文件
$sw_file = 'service-worker.js';
if (!file_exists($sw_file)) {
    echo "service-worker.js文件不存在\n";
    exit;
}

$sw_content = file_get_contents($sw_file);

// 提取现有的CACHE_ASSETS数组
preg_match('/const CACHE_ASSETS = \[(.*?)\];/s', $sw_content, $matches);
if (!isset($matches[1])) {
    echo "无法找到CACHE_ASSETS数组\n";
    exit;
}

// 提取默认缓存项（排除注释）
$default_assets = [];
$existing_assets = explode(',', $matches[1]);
foreach ($existing_assets as $asset) {
    $asset = trim($asset);
    // 跳过空行和注释
    if ($asset && !preg_match('/^\/\//', $asset)) {
        // 移除引号
        $asset = trim($asset, "'\"");
        $default_assets[] = $asset;
    }
}

echo "默认缓存项：\n";
foreach ($default_assets as $asset) {
    echo "  - {$asset}\n";
}

// 合并默认缓存项和头像文件
$all_assets = array_merge($default_assets, $image_files);
// 去重
$all_assets = array_unique($all_assets);

// 生成新的CACHE_ASSETS数组内容
$new_cache_assets = 'const CACHE_ASSETS = [\n';
foreach ($all_assets as $asset) {
    $new_cache_assets .= "    '{$asset}',\n";
}
// 移除最后一个逗号
$new_cache_assets = rtrim($new_cache_assets, ",\n") . "\n";
$new_cache_assets .= '];';

// 更新service-worker.js文件
$new_sw_content = preg_replace('/const CACHE_ASSETS = \[(.*?)\];/s', $new_cache_assets, $sw_content);

if (file_put_contents($sw_file, $new_sw_content)) {
    echo "\nservice-worker.js文件已更新\n";
    echo "总共缓存了 " . count($all_assets) . " 个资源\n";
} else {
    echo "\n更新service-worker.js文件失败\n";
}

echo "\nService Worker缓存更新完成！\n";
?>