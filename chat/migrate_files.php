<?php
/**
 * 文件迁移脚本：为现有上传文件添加.upload后缀
 * 用于确保所有服务器上的文件都符合新版格式
 */

// 启用错误报告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

echo "=== 文件迁移脚本启动 ===\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n";

// 引入配置文件
require_once 'config.php';
require_once 'db.php';

// 获取上传目录
$uploadDir = UPLOAD_DIR;
echo "上传目录: {$uploadDir}\n";

// 连接数据库
require_once 'db.php';
if (!$conn) {
    die("数据库连接失败\n");
} else {
    echo "数据库连接成功\n";
}

// 准备SQL查询
$stmt = $conn->prepare("SELECT * FROM files");
$updateStmt = $conn->prepare("UPDATE files SET stored_name = ?, file_path = ? WHERE id = ?");

// 执行查询
$stmt->execute();
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalFiles = count($files);
$processedFiles = 0;
$migratedFiles = 0;
$errors = 0;

if ($totalFiles === 0) {
    echo "没有找到需要处理的文件\n";
    exit;
}

echo "共找到 {$totalFiles} 个文件记录\n";

// 遍历所有文件记录
foreach ($files as $file) {
    $processedFiles++;
    $fileId = $file['id'];
    $originalName = $file['original_name'];
    $storedName = $file['stored_name'];
    $filePath = $file['file_path'];
    
    // 检查文件名是否已经包含.upload后缀
    if (str_ends_with($storedName, '.upload')) {
        // 已包含后缀，跳过
        echo "[{$processedFiles}/{$totalFiles}] 跳过: {$storedName} (已包含.upload后缀)\n";
        continue;
    }
    
    // 构建新的文件名和路径
    $newStoredName = $storedName . '.upload';
    $newFilePath = dirname($filePath) . '/' . $newStoredName;
    
    // 检查物理文件是否存在
    if (!file_exists($filePath)) {
        echo "[{$processedFiles}/{$totalFiles}] 错误: 物理文件不存在 - {$filePath}\n";
        $errors++;
        continue;
    }
    
    // 重命名物理文件
    if (rename($filePath, $newFilePath)) {
        // 更新数据库记录
        if ($updateStmt->execute([$newStoredName, $newFilePath, $fileId])) {
            echo "[{$processedFiles}/{$totalFiles}] 成功: {$storedName} -> {$newStoredName}\n";
            $migratedFiles++;
        } else {
            echo "[{$processedFiles}/{$totalFiles}] 错误: 数据库更新失败 - {$storedName}\n";
            // 回滚文件重命名
            rename($newFilePath, $filePath);
            $errors++;
        }
    } else {
        echo "[{$processedFiles}/{$totalFiles}] 错误: 文件重命名失败 - {$storedName}\n";
        $errors++;
    }
}

// 扫描上传目录中的孤立文件（数据库中没有记录的文件）
echo "\n=== 扫描孤立文件 ===\n";
$dir = new DirectoryIterator($uploadDir);
$orphanFiles = [];

foreach ($dir as $fileInfo) {
    if ($fileInfo->isDot()) continue;
    if ($fileInfo->isDir()) continue;
    
    $fileName = $fileInfo->getFilename();
    
    // 检查文件是否在数据库中有记录
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM files WHERE stored_name = ?");
    $checkStmt->execute([$fileName]);
    $count = $checkStmt->fetchColumn();
    
    if ($count === 0) {
        // 检查是否已经有.upload后缀
        if (!str_ends_with($fileName, '.upload')) {
            $orphanFiles[] = $fileName;
        }
    }
}

if (count($orphanFiles) > 0) {
    echo "找到 " . count($orphanFiles) . " 个孤立文件，已添加.upload后缀\n";
    
    foreach ($orphanFiles as $orphanFile) {
        $oldPath = $uploadDir . $orphanFile;
        $newPath = $uploadDir . $orphanFile . '.upload';
        
        if (rename($oldPath, $newPath)) {
            echo "  ✓ {$orphanFile} -> {$orphanFile}.upload\n";
            $migratedFiles++;
        } else {
            echo "  ✗ 重命名失败: {$orphanFile}\n";
            $errors++;
        }
    }
} else {
    echo "没有找到孤立文件\n";
}

echo "\n=== 迁移完成 ===\n";
echo "总文件数: {$totalFiles}\n";
echo "处理文件数: {$processedFiles}\n";
echo "成功迁移: {$migratedFiles}\n";
echo "迁移失败: {$errors}\n";
echo "结束时间: " . date('Y-m-d H:i:s') . "\n";
echo "=== 脚本结束 ===\n";

// 关闭数据库连接
$conn = null;
