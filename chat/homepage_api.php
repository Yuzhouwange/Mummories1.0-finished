<?php
/**
 * Mummories Homepage API v1.0
 * 路由: /api/v1/*
 *
 * 资源:
 *   GET    /api/v1/profile           - 获取个人资料
 *   PUT    /api/v1/profile           - 更新个人资料 (Admin)
 *   GET    /api/v1/projects          - 项目列表
 *   POST   /api/v1/projects          - 新增项目 (Admin)
 *   PUT    /api/v1/projects/{id}     - 更新项目 (Admin)
 *   DELETE /api/v1/projects/{id}     - 删除项目 (Admin)
 *   POST   /api/v1/contact           - 提交留言
 *   GET    /api/v1/bg                - 代理背景图（服务端缓存）
 *   GET    /api/v1/stats             - 站点统计
 */

ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ===== 响应头 =====
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ===== 加载 .env =====
function loadEnv(string $file): array {
    $env = [];
    if (!file_exists($file)) return $env;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $env[$k] = $v;
    }
    return $env;
}
$env = loadEnv(__DIR__ . '/.env');

// ===== 数据库连接 =====
$db = new mysqli(
    $env['DB_HOST']     ?? 'db',
    $env['DB_USER']     ?? 'root',
    $env['DB_PASSWORD'] ?? '',
    $env['DB_NAME']     ?? 'chat'
);
if ($db->connect_error) {
    http_response_code(503);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}
$db->set_charset('utf8mb4');

// ===== 工具函数 =====
function respond(array $data, int $code = 200): void {
    ob_clean();
    http_response_code($code);
    // 不使用 JSON_UNESCAPED_UNICODE：让中文以 \uXXXX 形式输出，与响应字符集无关
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function ok($data = null, string $msg = 'ok'): void {
    respond(['success' => true, 'message' => $msg, 'data' => $data]);
}
function err(string $msg, int $code = 400): void {
    respond(['success' => false, 'error' => $msg], $code);
}
function esc(mysqli $db, $val): string {
    return $db->real_escape_string((string)$val);
}

// ===== 解析路由 =====
$uri     = $_SERVER['REQUEST_URI'] ?? '/';
$path    = parse_url($uri, PHP_URL_PATH);
$path    = preg_replace('#^/api/v1/?#', '', $path);
$segs    = array_values(array_filter(explode('/', trim($path, '/'))));
$resource = $segs[0] ?? '';
$idParam  = isset($segs[1]) ? (int)$segs[1] : null;
$method   = $_SERVER['REQUEST_METHOD'];
$rawBody  = file_get_contents('php://input');
$body     = json_decode($rawBody, true) ?? [];

// ===== Session 管理 =====
session_name('HP_ADMIN_SID');
session_start();
$isSessionAdmin = !empty($_SESSION['hp_admin']);
$blogUserId = $_SESSION['blog_user_id'] ?? null;
$blogUser   = null;
if ($blogUserId) {
    $r = $db->query("SELECT id,username,email,avatar,bio,display_name FROM users WHERE id=" . (int)$blogUserId . " AND is_deleted=0");
    $blogUser = $r ? $r->fetch_assoc() : null;
    if (!$blogUser) { unset($_SESSION['blog_user_id']); $blogUserId = null; }
}

// ===== Admin 鉴权 (X-API-Key header 或 session) =====
$apiKey   = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['_key'] ?? '');
$adminKey = $env['HOMEPAGE_API_KEY'] ?? 'mummories-admin-2026';
$isAdmin  = hash_equals($adminKey, (string)$apiKey) || $isSessionAdmin;

// ===== IP 地址 =====
$clientIp = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? ($_SERVER['HTTP_CF_CONNECTING_IP']
    ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')))[0]);

// ===== 限流 (DB-based, 每分钟) =====
function rateLimit(mysqli $db, string $ip, string $res, int $max = 10): void {
    $ip  = esc($db, $ip);
    $res = esc($db, $res);
    $db->query("DELETE FROM homepage_rate_limit WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $r = $db->query("SELECT COUNT(*) cnt FROM homepage_rate_limit WHERE ip='$ip' AND resource='$res'");
    if ($r && $r->fetch_assoc()['cnt'] >= $max) {
        err('Too many requests, please wait a moment.', 429);
    }
    $db->query("INSERT INTO homepage_rate_limit (ip, resource) VALUES ('$ip','$res')");
}

// ===== 路由分发 =====
switch ($resource) {
    case 'auth':     handleAuth($method, $body, $adminKey);                            break;
    case 'profile':  handleProfile($db, $method, $body, $isAdmin, $clientIp);          break;
    case 'avatar':   handleAvatar($db, $method, $isAdmin);                             break;
    case 'projects': handleProjects($db, $method, $idParam, $body, $isAdmin);          break;
    case 'pages':    handlePages($db, $method, $idParam, $segs, $body, $isAdmin);      break;
    case 'contact':  handleContact($db, $method, $body, $clientIp, $isAdmin, $idParam);break;
    case 'bg':       handleBackground($clientIp);                                      break;
    case 'stats':    handleStats($db);                                                 break;
    case 'monitor':  handleMonitor($db, $isAdmin);                                     break;
    case 'blog':     handleBlog($db, $method, $body, $clientIp, $segs);                 break;
    case 'user':     handleUser($db, $method, $body, $blogUserId, $blogUser, $segs);     break;
    case 'shortcuts': handleShortcuts($db, $method, $body, $blogUserId, $idParam);       break;
    case 'privacy':  handlePrivacy($db, $method, $body, $blogUserId);                    break;
    case 'public':   handlePublicProfile($db, $segs);                                    break;
    case '':
        ok(['name' => 'Mummories Homepage API', 'version' => '1.1', 'status' => 'ok']);
        break;
    default:
        err('Resource not found', 404);
}

// ===== 处理函数 =====

function handleProfile(mysqli $db, string $method, array $body, bool $isAdmin, string $ip): void {
    if ($method === 'GET') {
        rateLimit($db, $ip, 'profile_get', 60);
        $result = $db->query("SELECT `key`, `value` FROM homepage_profile ORDER BY `key`");
        $profile = [];
        while ($row = $result->fetch_assoc()) {
            // skills 字段转数组返回
            if ($row['key'] === 'skills') {
                $profile['skills'] = array_map('trim', explode(',', $row['value']));
            } else {
                $profile[$row['key']] = $row['value'];
            }
        }
        ok($profile);
    } elseif ($method === 'PUT' || $method === 'POST') {
        if (!$isAdmin) err('Unauthorized', 401);
        $allowed = ['name', 'bio', 'email', 'wechat', 'skills', 'avatar'];
        foreach ($body as $k => $v) {
            if (!in_array($k, $allowed)) continue;
            $kEsc = esc($db, $k);
            $vEsc = esc($db, is_array($v) ? implode(',', $v) : $v);
            $db->query("INSERT INTO homepage_profile (`key`,`value`) VALUES ('$kEsc','$vEsc')
                        ON DUPLICATE KEY UPDATE `value`='$vEsc', updated_at=NOW()");
        }
        ok(null, 'Profile updated');
    } else {
        err('Method not allowed', 405);
    }
}

function handleProjects(mysqli $db, string $method, ?int $id, array $body, bool $isAdmin): void {
    if ($method === 'GET') {
        $where = $isAdmin ? '' : 'WHERE visible=1';
        $result = $db->query("SELECT * FROM homepage_projects $where ORDER BY sort_order ASC, id DESC");
        $projects = [];
        while ($row = $result->fetch_assoc()) {
            $row['id']         = (int)$row['id'];
            $row['sort_order'] = (int)$row['sort_order'];
            $row['visible']    = (bool)$row['visible'];
            if (!empty($row['tags'])) {
                $row['tags'] = array_map('trim', explode(',', $row['tags']));
            } else {
                $row['tags'] = [];
            }
            $projects[] = $row;
        }
        ok($projects);
    } elseif ($method === 'POST') {
        if (!$isAdmin) err('Unauthorized', 401);
        $title = trim($body['title'] ?? '');
        if (empty($title)) err('title is required');
        $desc  = esc($db, $body['description'] ?? '');
        $url   = esc($db, $body['url']         ?? '#');
        $icon  = esc($db, $body['icon']        ?? 'default');
        $tags  = esc($db, is_array($body['tags'] ?? null)
                   ? implode(',', $body['tags']) : ($body['tags'] ?? ''));
        $sort  = (int)($body['sort_order'] ?? 0);
        $title = esc($db, $title);
        $db->query("INSERT INTO homepage_projects (title,description,url,icon,tags,sort_order,visible)
                    VALUES ('$title','$desc','$url','$icon','$tags',$sort,1)");
        ok(['id' => $db->insert_id], 'Project created');
    } elseif ($method === 'PUT') {
        if (!$isAdmin) err('Unauthorized', 401);
        if (!$id) err('id required');
        $sets = [];
        $fieldMap = ['title' => 'str', 'description' => 'str', 'url' => 'str',
                     'icon' => 'str', 'tags' => 'str', 'sort_order' => 'int', 'visible' => 'int'];
        foreach ($fieldMap as $field => $type) {
            if (!array_key_exists($field, $body)) continue;
            if ($type === 'int') {
                $sets[] = "`$field`=" . (int)$body[$field];
            } else {
                $val = is_array($body[$field]) ? implode(',', $body[$field]) : $body[$field];
                $val = esc($db, $val);
                $sets[] = "`$field`='$val'";
            }
        }
        if (empty($sets)) err('Nothing to update');
        $db->query("UPDATE homepage_projects SET " . implode(',', $sets) . ",updated_at=NOW() WHERE id=$id");
        if ($db->affected_rows === 0) err('Project not found', 404);
        ok(null, 'Project updated');
    } elseif ($method === 'DELETE') {
        if (!$isAdmin) err('Unauthorized', 401);
        if (!$id) err('id required');
        $db->query("DELETE FROM homepage_projects WHERE id=$id");
        if ($db->affected_rows === 0) err('Project not found', 404);
        ok(null, 'Project deleted');
    } else {
        err('Method not allowed', 405);
    }
}

function handleContact(mysqli $db, string $method, array $body, string $ip, bool $isAdmin, ?int $id): void {
    if ($method === 'GET') {
        if ($isAdmin) {
            // 管理员可查看所有留言
            $result = $db->query("SELECT * FROM homepage_contacts ORDER BY created_at DESC LIMIT 200");
            $msgs = [];
            while ($row = $result->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['is_read'] = (bool)$row['is_read'];
                $msgs[] = $row;
            }
            ok($msgs);
        } else {
            $r = $db->query("SELECT COUNT(*) cnt FROM homepage_contacts");
            ok(['total' => (int)$r->fetch_assoc()['cnt']]);
        }
        return;
    }
    if ($method === 'DELETE') {
        if (!$isAdmin) err('Unauthorized', 401);
        if (!$id) err('id required');
        $db->query("DELETE FROM homepage_contacts WHERE id=$id");
        ok(null, 'Deleted');
        return;
    }
    if ($method === 'PUT' && $id && $isAdmin) {
        $db->query("UPDATE homepage_contacts SET is_read=1 WHERE id=$id");
        ok(null, 'Marked as read');
        return;
    }
    if ($method !== 'POST') err('Method not allowed', 405);
    rateLimit($db, $ip, 'contact', 3);

    $name    = trim($body['name']    ?? '');
    $email   = trim($body['email']   ?? '');
    $message = trim($body['message'] ?? '');

    if (empty($name) || strlen($name) > 50)            err('称呼不能为空且不超过50字');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))    err('请输入有效的邮箱地址');
    if (empty($message) || strlen($message) < 2)       err('留言内容不能为空');
    if (strlen($message) > 2000)                       err('留言内容不超过2000字');

    $nameEsc    = esc($db, htmlspecialchars($name,    ENT_QUOTES, 'UTF-8'));
    $emailEsc   = esc($db, $email);
    $messageEsc = esc($db, htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $ipEsc      = esc($db, $ip);

    $db->query("INSERT INTO homepage_contacts (name,email,message,ip)
                VALUES ('$nameEsc','$emailEsc','$messageEsc','$ipEsc')");
    ok(null, '留言已发送，我会尽快回复！');
}

function handleBackground(string $ip): void {
    $cacheDir = sys_get_temp_dir() . '/hp_bg_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

    // 每小时轮换一次缓存文件
    $cacheKey  = date('Y-m-d-H');
    $cacheFile = $cacheDir . '/bg_' . $cacheKey . '.dat';
    $metaFile  = $cacheFile . '.meta';

    $forceRefresh = isset($_GET['refresh']);

    if (!$forceRefresh && file_exists($cacheFile) && filesize($cacheFile) > 10240) {
        $mime = file_exists($metaFile) ? trim(file_get_contents($metaFile)) : 'image/jpeg';
        ob_clean();
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=3600');
        header('X-Cache: HIT');
        readfile($cacheFile);
        exit;
    }

    // 候选 API（随机选一个）
    $apis = [
        'https://t.mwm.moe/pc/',
        'https://www.loliapi.com/acg/pc/',
        'https://api.yimian.xyz/img?type=moe&size=1920x1080',
    ];
    shuffle($apis);

    $imgData = null;
    $mimeType = 'image/jpeg';
    foreach ($apis as $apiUrl) {
        $ctx = stream_context_create(['http' => [
            'timeout'          => 8,
            'follow_location'  => 1,
            'max_redirects'    => 5,
            'method'           => 'GET',
            'header'           => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
        ]]);
        $data = @file_get_contents($apiUrl, false, $ctx);
        if (!$data || strlen($data) < 10240) continue;

        // 验证 MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->buffer($data);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) continue;

        $imgData  = $data;
        $mimeType = $mime;
        break;
    }

    if (!$imgData) {
        err('Background image temporarily unavailable', 503);
    }

    // 写缓存
    file_put_contents($cacheFile, $imgData);
    file_put_contents($metaFile, $mimeType);

    ob_clean();
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: public, max-age=3600');
    header('X-Cache: MISS');
    echo $imgData;
    exit;
}

function handleStats(mysqli $db): void {
    $contacts = $db->query("SELECT COUNT(*) cnt FROM homepage_contacts")->fetch_assoc()['cnt'];
    $projects = $db->query("SELECT COUNT(*) cnt FROM homepage_projects WHERE visible=1")->fetch_assoc()['cnt'];
    $pages    = $db->query("SELECT COUNT(*) cnt FROM homepage_pages WHERE visible=1");
    $pagesCnt = $pages ? (int)$pages->fetch_assoc()['cnt'] : 0;
    ok([
        'contacts' => (int)$contacts,
        'projects' => (int)$projects,
        'pages'    => $pagesCnt,
        'version'  => '1.1',
        'name'     => 'Mummories',
    ]);
}

// ===== 管理员登录 / 登出 =====
function handleAuth(string $method, array $body, string $adminKey): void {
    if ($method === 'GET') {
        ok(['logged_in' => !empty($_SESSION['hp_admin'])]);
        return;
    }
    if ($method === 'DELETE') {
        $_SESSION = [];
        session_destroy();
        ok(null, 'Logged out');
        return;
    }
    if ($method !== 'POST') err('Method not allowed', 405);

    $key = trim($body['key'] ?? '');
    if (empty($key)) err('key is required');
    if (!hash_equals($adminKey, $key)) err('Invalid key', 401);

    $_SESSION['hp_admin'] = true;
    $_SESSION['hp_login_time'] = time();
    ok(['logged_in' => true], 'Login success');
}

// ===== 头像上传 =====
function handleAvatar(mysqli $db, string $method, bool $isAdmin): void {
    if ($method === 'GET') {
        $r = $db->query("SELECT value FROM homepage_profile WHERE `key`='avatar'");
        $row = $r ? $r->fetch_assoc() : null;
        $avatar = $row ? $row['value'] : '';

        if ($avatar && file_exists($avatar)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($avatar);
            ob_clean();
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=86400');
            readfile($avatar);
            exit;
        }
        err('No avatar set', 404);
    }

    if ($method !== 'POST') err('Method not allowed', 405);
    if (!$isAdmin) err('Unauthorized', 401);

    if (empty($_FILES['avatar'])) err('No file uploaded');
    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) err('Upload error: ' . $file['error']);
    if ($file['size'] > 5 * 1024 * 1024) err('File too large (max 5MB)');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
        err('Only jpeg/png/webp/gif are allowed');
    }

    $ext = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/webp' => '.webp', 'image/gif' => '.gif'];
    $dir = '/var/www/html/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $filename = $dir . '/avatar_' . time() . ($ext[$mime] ?? '.jpg');

    if (!move_uploaded_file($file['tmp_name'], $filename)) {
        err('Failed to save file');
    }

    $fnEsc = esc($db, $filename);
    $db->query("INSERT INTO homepage_profile (`key`,value) VALUES ('avatar','$fnEsc')
                ON DUPLICATE KEY UPDATE value='$fnEsc', updated_at=NOW()");
    ok(['path' => '/api/v1/avatar'], 'Avatar uploaded');
}

// ===== 自定义页面 CRUD =====
function handlePages(mysqli $db, string $method, ?int $id, array $segs, array $body, bool $isAdmin): void {
    // GET /api/v1/pages           -> 列表
    // GET /api/v1/pages/3         -> 详情 (by id)
    // GET /api/v1/pages/slug/xxx  -> 详情 (by slug)
    // POST /api/v1/pages          -> 新建
    // PUT /api/v1/pages/3         -> 更新
    // DELETE /api/v1/pages/3      -> 删除
    if ($method === 'GET') {
        // slug 路由: /api/v1/pages/slug/xxx
        if (isset($segs[1]) && $segs[1] === 'slug' && isset($segs[2])) {
            $slugEsc = esc($db, $segs[2]);
            $r = $db->query("SELECT * FROM homepage_pages WHERE slug='$slugEsc' AND visible=1");
            $page = $r ? $r->fetch_assoc() : null;
            if (!$page) err('Page not found', 404);
            $page['id'] = (int)$page['id'];
            $page['sort_order'] = (int)$page['sort_order'];
            $page['visible'] = (bool)$page['visible'];
            ok($page);
            return;
        }
        if ($id) {
            $r = $db->query("SELECT * FROM homepage_pages WHERE id=$id");
            $page = $r ? $r->fetch_assoc() : null;
            if (!$page) err('Page not found', 404);
            $page['id'] = (int)$page['id'];
            ok($page);
            return;
        }
        $where = $isAdmin ? '1' : 'visible=1';
        $result = $db->query("SELECT id,title,slug,icon,sort_order,visible,created_at,updated_at FROM homepage_pages WHERE $where ORDER BY sort_order ASC, id DESC");
        $pages = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['sort_order'] = (int)$row['sort_order'];
            $row['visible'] = (bool)$row['visible'];
            $pages[] = $row;
        }
        ok($pages);
    } elseif ($method === 'POST') {
        if (!$isAdmin) err('Unauthorized', 401);
        $title = trim($body['title'] ?? '');
        if (empty($title)) err('title is required');
        $slug = trim($body['slug'] ?? '');
        if (empty($slug)) $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $title)));
        if (empty($slug)) $slug = 'page-' . time();
        $icon    = esc($db, $body['icon']    ?? 'file');
        $content = esc($db, $body['content'] ?? '');
        $sort    = (int)($body['sort_order'] ?? 0);
        $titleE  = esc($db, $title);
        $slugE   = esc($db, $slug);
        $db->query("INSERT INTO homepage_pages (title,slug,icon,content,sort_order,visible) VALUES ('$titleE','$slugE','$icon','$content',$sort,1)");
        if ($db->errno) err('Slug already exists or DB error', 409);
        ok(['id' => $db->insert_id, 'slug' => $slug], 'Page created');
    } elseif ($method === 'PUT') {
        if (!$isAdmin) err('Unauthorized', 401);
        if (!$id) err('id required');
        $sets = [];
        $fields = ['title' => 'str', 'slug' => 'str', 'icon' => 'str', 'content' => 'str', 'sort_order' => 'int', 'visible' => 'int'];
        foreach ($fields as $f => $t) {
            if (!array_key_exists($f, $body)) continue;
            if ($t === 'int') { $sets[] = "`$f`=" . (int)$body[$f]; }
            else { $sets[] = "`$f`='" . esc($db, $body[$f]) . "'"; }
        }
        if (empty($sets)) err('Nothing to update');
        $db->query("UPDATE homepage_pages SET " . implode(',', $sets) . ",updated_at=NOW() WHERE id=$id");
        ok(null, 'Page updated');
    } elseif ($method === 'DELETE') {
        if (!$isAdmin) err('Unauthorized', 401);
        if (!$id) err('id required');
        $db->query("DELETE FROM homepage_pages WHERE id=$id");
        ok(null, 'Page deleted');
    } else {
        err('Method not allowed', 405);
    }
}

// ===== 后台监控 =====
function handleMonitor(mysqli $db, bool $isAdmin): void {
    if (!$isAdmin) err('Unauthorized', 401);

    // 留言统计
    $totalContacts = (int)$db->query("SELECT COUNT(*) c FROM homepage_contacts")->fetch_assoc()['c'];
    $unread        = (int)$db->query("SELECT COUNT(*) c FROM homepage_contacts WHERE is_read=0")->fetch_assoc()['c'];
    $todayContacts = (int)$db->query("SELECT COUNT(*) c FROM homepage_contacts WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c'];

    // 项目统计
    $totalProjects = (int)$db->query("SELECT COUNT(*) c FROM homepage_projects")->fetch_assoc()['c'];

    // 页面统计
    $totalPages = 0;
    $r = $db->query("SELECT COUNT(*) c FROM homepage_pages");
    if ($r) $totalPages = (int)$r->fetch_assoc()['c'];

    // 最近7天留言趋势
    $trend = [];
    $r = $db->query("SELECT DATE(created_at) d, COUNT(*) c FROM homepage_contacts WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY d ORDER BY d");
    while ($row = $r->fetch_assoc()) { $trend[] = ['date' => $row['d'], 'count' => (int)$row['c']]; }

    // 最近5条留言
    $recent = [];
    $r = $db->query("SELECT id,name,email,message,ip,created_at,is_read FROM homepage_contacts ORDER BY created_at DESC LIMIT 5");
    while ($row = $r->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['is_read'] = (bool)$row['is_read'];
        $recent[] = $row;
    }

    // 限流表大小（反映近期流量）
    $rateCount = (int)$db->query("SELECT COUNT(*) c FROM homepage_rate_limit")->fetch_assoc()['c'];

    ok([
        'contacts' => ['total' => $totalContacts, 'unread' => $unread, 'today' => $todayContacts],
        'projects' => ['total' => $totalProjects],
        'pages'    => ['total' => $totalPages],
        'trend'    => $trend,
        'recent_contacts' => $recent,
        'rate_requests'   => $rateCount,
        'server_time'     => date('Y-m-d H:i:s'),
        'php_version'     => PHP_VERSION,
    ]);
}

// ===== 博客用户注册/登录 =====
function handleBlog(mysqli $db, string $method, array $body, string $ip, array $segs): void {
    $action = $segs[1] ?? '';

    // POST /api/v1/blog/register
    if ($action === 'register' && $method === 'POST') {
        rateLimit($db, $ip, 'blog_register', 5);

        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (strlen($username) < 3 || strlen($username) > 20) err('用户名长度需 3-20 字符');
        if (preg_match('/[<>"\']/', $username)) err('用户名含非法字符');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('邮箱格式不正确');
        if (strlen($password) < 6) err('密码至少 6 位');

        $usernameE = esc($db, $username);
        $emailE    = esc($db, $email);

        // 检查重复
        $r = $db->query("SELECT id FROM users WHERE username='$usernameE' OR email='$emailE'");
        if ($r && $r->num_rows > 0) err('用户名或邮箱已被注册');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $hashE = esc($db, $hash);
        $ipE = esc($db, $ip);

        $db->query("INSERT INTO users (username, email, password, ip_address, agreed_to_terms) VALUES ('$usernameE','$emailE','$hashE','$ipE', 1)");
        if ($db->errno) err('注册失败，请稍后再试');

        $userId = $db->insert_id;

        // 设置 session
        $_SESSION['blog_user_id'] = $userId;

        // 同时写入默认 PHPSESSID 供聊天室使用
        writeChatSession($userId, $username, $email, 'default_avatar.png');

        ok(['id' => $userId, 'username' => $username], '注册成功');
    }

    // POST /api/v1/blog/login
    elseif ($action === 'login' && $method === 'POST') {
        rateLimit($db, $ip, 'blog_login', 15);

        $login = trim($body['login'] ?? '');  // 用户名或邮箱
        $password = $body['password'] ?? '';
        if (empty($login) || empty($password)) err('请填写用户名/邮箱和密码');

        $loginE = esc($db, $login);
        $r = $db->query("SELECT id,username,email,password,avatar,bio,display_name,is_deleted FROM users WHERE username='$loginE' OR email='$loginE' LIMIT 1");
        $user = $r ? $r->fetch_assoc() : null;
        if (!$user) err('用户名或密码错误');
        if ($user['is_deleted']) err('该账户已被注销');
        if (!password_verify($password, $user['password'])) err('用户名或密码错误');

        $_SESSION['blog_user_id'] = (int)$user['id'];

        // 同时写入聊天室 session
        writeChatSession((int)$user['id'], $user['username'], $user['email'], $user['avatar']);

        ok([
            'id'       => (int)$user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'avatar'   => $user['avatar'],
            'bio'      => $user['bio'],
            'display_name' => $user['display_name'],
        ], '登录成功');
    }

    // DELETE /api/v1/blog/logout
    elseif ($action === 'logout') {
        unset($_SESSION['blog_user_id']);
        ok(null, '已退出');
    }

    // GET /api/v1/blog/status
    elseif ($action === 'status' && $method === 'GET') {
        if (!empty($_SESSION['blog_user_id'])) {
            $uid = (int)$_SESSION['blog_user_id'];
            $r = $db->query("SELECT id,username,email,avatar,bio,display_name,skills FROM users WHERE id=$uid AND is_deleted=0");
            $u = $r ? $r->fetch_assoc() : null;
            if ($u) {
                $u['id'] = (int)$u['id'];
                $u['skills'] = $u['skills'] ? json_decode($u['skills'], true) : [];
                ok(['logged_in' => true, 'user' => $u]);
            } else {
                unset($_SESSION['blog_user_id']);
                ok(['logged_in' => false]);
            }
        } else {
            ok(['logged_in' => false]);
        }
    }

    else { err('Not found', 404); }
}

// 写入聊天室所需的默认 PHP session
function writeChatSession(int $userId, string $username, string $email, string $avatar): void {
    // 获取当前 HP session id 备份
    $hpSid = session_id();
    session_write_close();

    // 切换到默认 session（PHPSESSID）
    session_name('PHPSESSID');

    // 如果浏览器已有 PHPSESSID cookie，复用它
    if (!empty($_COOKIE['PHPSESSID'])) {
        session_id($_COOKIE['PHPSESSID']);
    }
    session_start();
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['email']    = $email;
    $_SESSION['avatar']   = $avatar;
    $_SESSION['last_activity'] = time();
    session_write_close();

    // 恢复 HP session
    session_name('HP_ADMIN_SID');
    session_id($hpSid);
    session_start();
}

// ===== 用户个人资料 (已登录博客用户) =====
function handleUser(mysqli $db, string $method, array $body, ?int $blogUserId, ?array $blogUser, array $segs = []): void {
    if (!$blogUserId || !$blogUser) err('请先登录', 401);

    // GET /api/v1/user — 获取当前用户资料
    if ($method === 'GET') {
        $blogUser['id'] = (int)$blogUser['id'];
        // 补充 skills 字段
        $r = $db->query("SELECT skills FROM users WHERE id=" . (int)$blogUserId);
        $row = $r ? $r->fetch_assoc() : null;
        $blogUser['skills'] = $row && $row['skills'] ? json_decode($row['skills'], true) : [];
        ok($blogUser);
    }

    // PUT /api/v1/user — 更新个人资料
    elseif ($method === 'PUT') {
        $sets = [];
        $allowed = ['display_name', 'bio', 'email'];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $body)) continue;
            $val = trim((string)$body[$f]);
            if ($f === 'email' && !empty($val) && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                err('邮箱格式不正确');
            }
            $sets[] = "`$f`='" . esc($db, $val) . "'";
        }
        // skills 字段：JSON 数组
        if (array_key_exists('skills', $body)) {
            $skills = $body['skills'];
            if (!is_array($skills)) $skills = array_map('trim', explode(',', (string)$skills));
            $skills = array_values(array_filter($skills));
            if (count($skills) > 20) err('技能最多20个');
            $sets[] = "skills='" . esc($db, json_encode($skills, JSON_UNESCAPED_UNICODE)) . "'";
        }
        if (empty($sets)) err('没有需要更新的内容');
        $db->query("UPDATE users SET " . implode(',', $sets) . " WHERE id=" . (int)$blogUserId);
        ok(null, '资料已更新');
    }

    // POST /api/v1/user/avatar — 上传头像
    elseif ($method === 'POST') {
        if (empty($_FILES['avatar'])) err('请选择头像文件');
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) err('上传出错');
        if ($file['size'] > 5 * 1024 * 1024) err('文件不能超过 5MB');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) err('仅支持 jpg/png/gif/webp');

        $uploadDir = __DIR__ . '/avatars/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
        $filename = 'blog_' . $blogUserId . '_' . time() . '.' . $ext;
        $dest = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) err('保存文件失败');

        $filenameE = esc($db, $filename);
        $db->query("UPDATE users SET avatar='$filenameE' WHERE id=" . (int)$blogUserId);

        ok(['avatar' => $filename], '头像已更新');
    }

    else { err('Method not allowed', 405); }
}

// ===== 快捷入口 CRUD =====
function handleShortcuts(mysqli $db, string $method, array $body, ?int $blogUserId, ?int $id): void {
    if (!$blogUserId) err('请先登录', 401);

    // GET — 列表
    if ($method === 'GET') {
        $r = $db->query("SELECT id,title,url,icon,sort_order FROM user_shortcuts WHERE user_id=" . (int)$blogUserId . " ORDER BY sort_order,id");
        $list = [];
        while ($row = $r->fetch_assoc()) { $row['id'] = (int)$row['id']; $list[] = $row; }
        ok($list);
    }
    // POST — 新建
    elseif ($method === 'POST') {
        $title = trim($body['title'] ?? '');
        $url   = trim($body['url'] ?? '');
        $icon  = trim($body['icon'] ?? 'link');
        if (!$title || !$url) err('标题和链接不能为空');
        if (mb_strlen($title) > 50) err('标题最多50字符');
        if (strlen($url) > 500) err('链接最多500字符');
        // 限制每人最多 15 个快捷入口
        $r = $db->query("SELECT COUNT(*) cnt FROM user_shortcuts WHERE user_id=" . (int)$blogUserId);
        if ($r->fetch_assoc()['cnt'] >= 15) err('快捷入口最多15个');
        $t = esc($db, $title); $u = esc($db, $url); $i = esc($db, $icon);
        $db->query("INSERT INTO user_shortcuts (user_id,title,url,icon) VALUES (" . (int)$blogUserId . ",'$t','$u','$i')");
        ok(['id' => (int)$db->insert_id], '已添加');
    }
    // PUT — 更新
    elseif ($method === 'PUT') {
        if (!$id) err('缺少id');
        $sets = [];
        foreach (['title', 'url', 'icon', 'sort_order'] as $f) {
            if (!array_key_exists($f, $body)) continue;
            $v = $f === 'sort_order' ? (int)$body[$f] : esc($db, trim((string)$body[$f]));
            $sets[] = $f === 'sort_order' ? "sort_order=$v" : "`$f`='$v'";
        }
        if (empty($sets)) err('没有需要更新的内容');
        $db->query("UPDATE user_shortcuts SET " . implode(',', $sets) . " WHERE id=$id AND user_id=" . (int)$blogUserId);
        ok(null, '已更新');
    }
    // DELETE — 删除
    elseif ($method === 'DELETE') {
        if (!$id) err('缺少id');
        $db->query("DELETE FROM user_shortcuts WHERE id=$id AND user_id=" . (int)$blogUserId);
        ok(null, '已删除');
    }
    else { err('Method not allowed', 405); }
}

// ===== 隐私设置 =====
function handlePrivacy(mysqli $db, string $method, array $body, ?int $blogUserId): void {
    if (!$blogUserId) err('请先登录', 401);

    if ($method === 'GET') {
        $r = $db->query("SELECT * FROM user_privacy WHERE user_id=" . (int)$blogUserId);
        $row = $r ? $r->fetch_assoc() : null;
        if (!$row) {
            $row = ['show_email'=>1,'show_skills'=>1,'show_bio'=>1,'show_contact'=>1,'allow_profile_view'=>1];
        }
        unset($row['user_id'], $row['updated_at']);
        ok($row);
    }
    elseif ($method === 'PUT') {
        $fields = ['show_email','show_skills','show_bio','show_contact','allow_profile_view'];
        $uid = (int)$blogUserId;
        // 先查现有值
        $r = $db->query("SELECT * FROM user_privacy WHERE user_id=$uid");
        $existing = $r ? $r->fetch_assoc() : null;
        $ins = ['user_id' => $uid];
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                $ins[$f] = $body[$f] ? 1 : 0;
            } elseif ($existing) {
                $ins[$f] = (int)$existing[$f];
            } else {
                $ins[$f] = 1;
            }
        }
        $cols = implode(',', array_keys($ins));
        $vals = implode(',', array_values($ins));
        $db->query("REPLACE INTO user_privacy ($cols) VALUES ($vals)");
        ok(null, '隐私设置已更新');
    }
    else { err('Method not allowed', 405); }
}

// ===== 公开资料（他人查看）=====
function handlePublicProfile(mysqli $db, array $segs): void {
    // GET /api/v1/public/{userId}
    $targetId = isset($segs[0]) ? (int)$segs[0] : 0;
    if ($targetId <= 0) err('无效用户ID', 400);

    // 检查隐私设置
    $pr = $db->query("SELECT * FROM user_privacy WHERE user_id=$targetId");
    $privacy = $pr ? $pr->fetch_assoc() : null;
    if (!$privacy) $privacy = ['show_email'=>1,'show_skills'=>1,'show_bio'=>1,'show_contact'=>1,'allow_profile_view'=>1];

    if (!(int)$privacy['allow_profile_view']) {
        err('该用户已关闭主页查看', 403);
    }

    $r = $db->query("SELECT id,username,display_name,avatar,bio,skills FROM users WHERE id=$targetId AND is_deleted=0");
    $u = $r ? $r->fetch_assoc() : null;
    if (!$u) err('用户不存在', 404);

    $profile = [
        'id'           => (int)$u['id'],
        'username'     => $u['username'],
        'display_name' => $u['display_name'],
        'avatar'       => $u['avatar'],
    ];

    if ((int)$privacy['show_bio']) $profile['bio'] = $u['bio'];
    if ((int)$privacy['show_skills']) $profile['skills'] = $u['skills'] ? json_decode($u['skills'], true) : [];

    // 快捷入口
    $sr = $db->query("SELECT id,title,url,icon FROM user_shortcuts WHERE user_id=$targetId ORDER BY sort_order,id");
    $shortcuts = [];
    while ($row = $sr->fetch_assoc()) { $shortcuts[] = $row; }
    $profile['shortcuts'] = $shortcuts;

    ok($profile);
}
