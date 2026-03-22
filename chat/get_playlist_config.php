<?php
require_once 'security_check.php';
header('Content-Type: application/json');
require_once 'config.php';

// 读取歌单配置
$config_file = __DIR__ . '/config/song_config.json';
if (!file_exists($config_file)) {
    echo json_encode([]);
    exit;
}

$config = json_decode(file_get_contents($config_file), true);
$playlists = [];

if ($config) {
    foreach ($config as $name => $settings) {
        $playlists[] = [
            'name' => $name,
            'type' => $settings['type']
        ];
    }
}

echo json_encode(['playlists' => $playlists]);
