<?php
/**
 * Mummories 社区功能 API
 * 文章、说说、评论、点赞、分类、标签、在线状态、操作日志、管理统计
 */

// ===== 文章 =====
function handleArticles(mysqli $db, string $method, ?int $id, array $body, bool $isAdmin, ?int $userId, string $ip, array $segs): void {
    if ($method === 'GET') {
        if ($id) {
            // 获取单篇文章
            $r = $db->query("SELECT a.*, u.username, u.display_name, u.avatar, c.name AS category_name
                FROM articles a
                LEFT JOIN users u ON a.user_id=u.id
                LEFT JOIN categories c ON a.category_id=c.id
                WHERE a.id=$id");
            $art = $r ? $r->fetch_assoc() : null;
            if (!$art) err('文章不存在', 404);
            if ($art['status'] !== 'published' && !$isAdmin) err('文章不存在', 404);
            // 增加浏览量
            $db->query("UPDATE articles SET view_count=view_count+1 WHERE id=$id");
            $art['view_count'] = (int)$art['view_count'] + 1;
            $art['id'] = (int)$art['id'];
            $art['like_count'] = (int)$art['like_count'];
            $art['comment_count'] = (int)$art['comment_count'];
            $art['is_top'] = (int)$art['is_top'];
            $art['avatar'] = !empty($art['avatar']) ? '/avatars/' . $art['avatar'] : '';
            ok($art);
        }
        // 文章列表
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = min(50, max(1, (int)($_GET['size'] ?? 10)));
        $status = $_GET['status'] ?? '';
        $catId = (int)($_GET['category'] ?? 0);
        $tag = trim($_GET['tag'] ?? '');
        $search = trim($_GET['search'] ?? '');
        $offset = ($page - 1) * $size;

        $where = [];
        if (!$isAdmin) { $where[] = "a.status='published'"; }
        elseif ($status) { $where[] = "a.status='" . esc($db, $status) . "'"; }
        if ($catId) $where[] = "a.category_id=$catId";
        if ($tag) $where[] = "a.tags LIKE '%" . esc($db, $tag) . "%'";
        if ($search) { $s = esc($db, $search); $where[] = "(a.title LIKE '%$s%' OR a.content LIKE '%$s%')"; }

        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $cnt = $db->query("SELECT COUNT(*) c FROM articles a $whereStr");
        $total = $cnt ? (int)$cnt->fetch_assoc()['c'] : 0;

        $r = $db->query("SELECT a.id,a.title,a.cover,a.category_id,a.tags,a.status,a.is_top,a.view_count,a.like_count,a.comment_count,a.created_at,a.updated_at,
            u.username,u.display_name,u.avatar, c.name AS category_name
            FROM articles a LEFT JOIN users u ON a.user_id=u.id LEFT JOIN categories c ON a.category_id=c.id
            $whereStr ORDER BY a.is_top DESC, a.created_at DESC LIMIT $offset,$size");
        $list = [];
        while ($row = $r->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['like_count'] = (int)$row['like_count'];
            $row['comment_count'] = (int)$row['comment_count'];
            $row['view_count'] = (int)$row['view_count'];
            $row['is_top'] = (int)$row['is_top'];
            $row['avatar'] = !empty($row['avatar']) ? '/avatars/' . $row['avatar'] : '';
            // 截取摘要
            if (isset($row['content'])) {
                $row['summary'] = mb_substr(strip_tags($row['content']), 0, 200);
                unset($row['content']);
            }
            $list[] = $row;
        }
        ok(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }
    elseif ($method === 'POST') {
        if (!$isAdmin && !$userId) err('请先登录', 401);
        $title = trim($body['title'] ?? '');
        $content = $body['content'] ?? '';
        $cover = trim($body['cover'] ?? '');
        $catId = (int)($body['category_id'] ?? 0);
        $tags = trim($body['tags'] ?? '');
        $status = in_array($body['status'] ?? '', ['published','draft','private']) ? $body['status'] : 'draft';
        $isTop = (int)($body['is_top'] ?? 0);
        if (!$title) err('标题不能为空');
        if (mb_strlen($title) > 255) err('标题最多255个字符');

        $uid = $userId ?: 0;
        $t = esc($db, $title); $c = esc($db, $content); $cv = esc($db, $cover);
        $tg = esc($db, $tags); $st = esc($db, $status);
        $db->query("INSERT INTO articles (user_id,title,content,cover,category_id,tags,status,is_top)
            VALUES ($uid,'$t','$c','$cv',$catId,'$tg','$st',$isTop)");
        $artId = (int)$db->insert_id;
        logOp($db, $userId, '发布文章', "文章ID:$artId 标题:$title", $ip);
        ok(['id' => $artId], '文章已创建');
    }
    elseif ($method === 'PUT') {
        if (!$id) err('缺少文章ID');
        if (!$isAdmin && !$userId) err('请先登录', 401);
        // 非管理员只能编辑自己的
        if (!$isAdmin) {
            $r = $db->query("SELECT user_id FROM articles WHERE id=$id");
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || (int)$row['user_id'] !== $userId) err('无权编辑此文章', 403);
        }
        $sets = [];
        foreach (['title','content','cover','tags','status'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "`$f`='" . esc($db, (string)$body[$f]) . "'";
            }
        }
        if (array_key_exists('category_id', $body)) $sets[] = "category_id=" . (int)$body['category_id'];
        if (array_key_exists('is_top', $body)) $sets[] = "is_top=" . ((int)$body['is_top'] ? 1 : 0);
        if (empty($sets)) err('没有需要更新的内容');
        $db->query("UPDATE articles SET " . implode(',', $sets) . " WHERE id=$id");
        logOp($db, $userId, '编辑文章', "文章ID:$id", $ip);
        ok(null, '已更新');
    }
    elseif ($method === 'DELETE') {
        if (!$id) err('缺少文章ID');
        if (!$isAdmin) {
            if (!$userId) err('请先登录', 401);
            $r = $db->query("SELECT user_id FROM articles WHERE id=$id");
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || (int)$row['user_id'] !== $userId) err('无权删除此文章', 403);
        }
        $db->query("DELETE FROM comments WHERE target_type='article' AND target_id=$id");
        $db->query("DELETE FROM likes WHERE target_type='article' AND target_id=$id");
        $db->query("DELETE FROM articles WHERE id=$id");
        logOp($db, $userId, '删除文章', "文章ID:$id", $ip);
        ok(null, '已删除');
    }
    else err('Method not allowed', 405);
}

// ===== 说说 =====
function handleMoments(mysqli $db, string $method, ?int $id, array $body, bool $isAdmin, ?int $userId, string $ip): void {
    if ($method === 'GET') {
        if ($id) {
            $r = $db->query("SELECT m.*, u.username, u.display_name, u.avatar FROM moments m LEFT JOIN users u ON m.user_id=u.id WHERE m.id=$id");
            $m = $r ? $r->fetch_assoc() : null;
            if (!$m) err('说说不存在', 404);
            if ($m['status'] !== 'public' && !$isAdmin) err('说说不存在', 404);
            $m['id'] = (int)$m['id'];
            $m['like_count'] = (int)$m['like_count'];
            $m['comment_count'] = (int)$m['comment_count'];
            $m['is_top'] = (int)$m['is_top'];
            $m['avatar'] = !empty($m['avatar']) ? '/avatars/' . $m['avatar'] : '';
            if ($m['images']) $m['images'] = array_filter(explode(',', $m['images']));
            else $m['images'] = [];
            ok($m);
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = min(50, max(1, (int)($_GET['size'] ?? 10)));
        $offset = ($page - 1) * $size;

        $where = $isAdmin ? '' : "WHERE m.status='public'";
        $cnt = $db->query("SELECT COUNT(*) c FROM moments m $where");
        $total = $cnt ? (int)$cnt->fetch_assoc()['c'] : 0;

        $r = $db->query("SELECT m.*, u.username, u.display_name, u.avatar FROM moments m LEFT JOIN users u ON m.user_id=u.id $where ORDER BY m.is_top DESC, m.created_at DESC LIMIT $offset,$size");
        $list = [];
        while ($row = $r->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['like_count'] = (int)$row['like_count'];
            $row['comment_count'] = (int)$row['comment_count'];
            $row['is_top'] = (int)$row['is_top'];
            $row['avatar'] = !empty($row['avatar']) ? '/avatars/' . $row['avatar'] : '';
            $row['images'] = $row['images'] ? array_filter(explode(',', $row['images'])) : [];
            $list[] = $row;
        }
        ok(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }
    elseif ($method === 'POST') {
        if (!$isAdmin && !$userId) err('请先登录', 401);
        $content = trim($body['content'] ?? '');
        if (!$content) err('内容不能为空');
        $images = trim($body['images'] ?? '');
        $status = in_array($body['status'] ?? '', ['public','private']) ? $body['status'] : 'public';
        $isTop = (int)($body['is_top'] ?? 0);
        $uid = $userId ?: 0;
        $c = esc($db, $content); $img = esc($db, $images); $st = esc($db, $status);
        $db->query("INSERT INTO moments (user_id,content,images,status,is_top) VALUES ($uid,'$c','$img','$st',$isTop)");
        logOp($db, $userId, '发布说说', "说说ID:" . $db->insert_id, $ip);
        ok(['id' => (int)$db->insert_id], '说说已发布');
    }
    elseif ($method === 'PUT') {
        if (!$id) err('缺少ID');
        if (!$isAdmin && !$userId) err('请先登录', 401);
        if (!$isAdmin) {
            $r = $db->query("SELECT user_id FROM moments WHERE id=$id");
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || (int)$row['user_id'] !== $userId) err('无权编辑', 403);
        }
        $sets = [];
        foreach (['content','images','status'] as $f) {
            if (array_key_exists($f, $body)) $sets[] = "`$f`='" . esc($db, (string)$body[$f]) . "'";
        }
        if (array_key_exists('is_top', $body)) $sets[] = "is_top=" . ((int)$body['is_top'] ? 1 : 0);
        if (empty($sets)) err('没有需要更新的内容');
        $db->query("UPDATE moments SET " . implode(',', $sets) . " WHERE id=$id");
        ok(null, '已更新');
    }
    elseif ($method === 'DELETE') {
        if (!$id) err('缺少ID');
        if (!$isAdmin) {
            if (!$userId) err('请先登录', 401);
            $r = $db->query("SELECT user_id FROM moments WHERE id=$id");
            $row = $r ? $r->fetch_assoc() : null;
            if (!$row || (int)$row['user_id'] !== $userId) err('无权删除此说说', 403);
        }
        $db->query("DELETE FROM comments WHERE target_type='moment' AND target_id=$id");
        $db->query("DELETE FROM likes WHERE target_type='moment' AND target_id=$id");
        $db->query("DELETE FROM moments WHERE id=$id");
        logOp($db, $userId, '删除说说', "说说ID:$id", $ip);
        ok(null, '已删除');
    }
    else err('Method not allowed', 405);
}

// ===== 评论 =====
function handleComments(mysqli $db, string $method, ?int $id, array $body, ?int $userId, string $ip, bool $isAdmin): void {
    if ($method === 'GET') {
        $targetType = $_GET['target_type'] ?? '';
        $targetId = (int)($_GET['target_id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = min(100, max(1, (int)($_GET['size'] ?? 20)));
        $offset = ($page - 1) * $size;

        if ($isAdmin && !$targetType) {
            // 管理员查看所有评论
            $statusFilter = isset($_GET['status']) ? "WHERE c.status='" . esc($db, $_GET['status']) . "'" : '';
            $cnt = $db->query("SELECT COUNT(*) n FROM comments c $statusFilter");
            $total = $cnt ? (int)$cnt->fetch_assoc()['n'] : 0;
            $r = $db->query("SELECT c.*, u.username, u.display_name FROM comments c LEFT JOIN users u ON c.user_id=u.id $statusFilter ORDER BY c.created_at DESC LIMIT $offset,$size");
            $list = [];
            while ($row = $r->fetch_assoc()) { $row['id'] = (int)$row['id']; $list[] = $row; }
            ok(['list' => $list, 'total' => $total, 'page' => $page]);
            return;
        }

        if (!$targetType || !$targetId) err('缺少target_type和target_id');
        // 获取评论树
        $tt = esc($db, $targetType);
        $r = $db->query("SELECT c.*, u.username, u.display_name, u.avatar AS user_avatar FROM comments c LEFT JOIN users u ON c.user_id=u.id WHERE c.target_type='$tt' AND c.target_id=$targetId AND c.status='approved' ORDER BY c.created_at ASC");
        $all = [];
        while ($row = $r->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['parent_id'] = $row['parent_id'] ? (int)$row['parent_id'] : null;
            $row['reply_to_id'] = $row['reply_to_id'] ? (int)$row['reply_to_id'] : null;
            $row['user_avatar'] = !empty($row['user_avatar']) ? '/avatars/' . $row['user_avatar'] : '';
            $all[] = $row;
        }
        // 构建树结构
        $roots = []; $childMap = [];
        foreach ($all as &$c) { $c['children'] = []; $childMap[$c['id']] = &$c; }
        unset($c);
        foreach ($all as &$c) {
            if ($c['parent_id'] && isset($childMap[$c['parent_id']])) {
                $childMap[$c['parent_id']]['children'][] = &$c;
            } else {
                $roots[] = &$c;
            }
        }
        ok($roots);
    }
    elseif ($method === 'POST') {
        $targetType = $body['target_type'] ?? '';
        $targetId = (int)($body['target_id'] ?? 0);
        if (!in_array($targetType, ['article', 'moment'])) err('无效的target_type');
        if (!$targetId) err('缺少target_id');
        $content = trim($body['content'] ?? '');
        if (!$content) err('评论内容不能为空');
        if (mb_strlen($content) > 1000) err('评论最多1000字');
        $parentId = (int)($body['parent_id'] ?? 0);
        $replyTo = (int)($body['reply_to_id'] ?? 0);

        $nickname = '匿名';
        $avatar = '';
        if ($userId) {
            $r = $db->query("SELECT username,display_name,avatar FROM users WHERE id=$userId");
            $u = $r ? $r->fetch_assoc() : null;
            if ($u) { $nickname = $u['display_name'] ?: $u['username']; $avatar = $u['avatar'] ?: ''; }
        } else {
            $nickname = trim($body['nickname'] ?? '匿名') ?: '匿名';
        }
        $tt = esc($db, $targetType);
        $c = esc($db, $content);
        $nn = esc($db, $nickname);
        $av = esc($db, $avatar);
        $eip = esc($db, $ip);
        $uid = $userId ?: 0;
        $db->query("INSERT INTO comments (target_type,target_id,user_id,nickname,avatar,content,parent_id,reply_to_id,ip_address)
            VALUES ('$tt',$targetId,$uid,'$nn','$av','$c'," . ($parentId ?: 'NULL') . "," . ($replyTo ?: 'NULL') . ",'$eip')");
        // 更新评论计数
        $table = $targetType === 'article' ? 'articles' : 'moments';
        $db->query("UPDATE $table SET comment_count=comment_count+1 WHERE id=$targetId");
        ok(['id' => (int)$db->insert_id], '评论已发布');
    }
    elseif ($method === 'PUT') {
        if (!$isAdmin) err('仅管理员可操作', 403);
        if (!$id) err('缺少评论ID');
        $status = $body['status'] ?? '';
        if (!in_array($status, ['approved','pending','rejected'])) err('无效的状态');
        $st = esc($db, $status);
        $db->query("UPDATE comments SET status='$st' WHERE id=$id");
        ok(null, '已更新');
    }
    elseif ($method === 'DELETE') {
        if (!$id) err('缺少评论ID');
        if (!$isAdmin) err('仅管理员可删除', 403);
        // 获取 target 信息以更新计数
        $r = $db->query("SELECT target_type,target_id FROM comments WHERE id=$id");
        $cm = $r ? $r->fetch_assoc() : null;
        $db->query("DELETE FROM comments WHERE id=$id OR parent_id=$id");
        if ($cm) {
            $table = $cm['target_type'] === 'article' ? 'articles' : 'moments';
            $tid = (int)$cm['target_id'];
            $cnt = $db->query("SELECT COUNT(*) n FROM comments WHERE target_type='" . esc($db, $cm['target_type']) . "' AND target_id=$tid AND status='approved'");
            $n = $cnt ? (int)$cnt->fetch_assoc()['n'] : 0;
            $db->query("UPDATE $table SET comment_count=$n WHERE id=$tid");
        }
        ok(null, '已删除');
    }
    else err('Method not allowed', 405);
}

// ===== 点赞 =====
function handleLikes(mysqli $db, string $method, array $body, ?int $userId, string $ip): void {
    if ($method === 'POST') {
        $targetType = $body['target_type'] ?? '';
        $targetId = (int)($body['target_id'] ?? 0);
        if (!in_array($targetType, ['article','moment','comment'])) err('无效的target_type');
        if (!$targetId) err('缺少target_id');
        $uid = $userId ?: 0;
        if (!$uid) err('请先登录后点赞', 401);
        $tt = esc($db, $targetType);
        $eip = esc($db, $ip);
        // 检查是否已点赞
        $r = $db->query("SELECT id FROM likes WHERE target_type='$tt' AND target_id=$targetId AND user_id=$uid");
        if ($r && $r->num_rows > 0) {
            // 取消点赞
            $db->query("DELETE FROM likes WHERE target_type='$tt' AND target_id=$targetId AND user_id=$uid");
            $table = $targetType === 'article' ? 'articles' : ($targetType === 'moment' ? 'moments' : '');
            if ($table) $db->query("UPDATE $table SET like_count=GREATEST(0,like_count-1) WHERE id=$targetId");
            ok(['liked' => false], '已取消点赞');
        } else {
            $db->query("INSERT INTO likes (target_type,target_id,user_id,ip_address) VALUES ('$tt',$targetId,$uid,'$eip')");
            $table = $targetType === 'article' ? 'articles' : ($targetType === 'moment' ? 'moments' : '');
            if ($table) $db->query("UPDATE $table SET like_count=like_count+1 WHERE id=$targetId");
            ok(['liked' => true], '已点赞');
        }
    }
    elseif ($method === 'GET') {
        // 检查当前用户是否点赞了某个对象
        $targetType = $_GET['target_type'] ?? '';
        $targetId = (int)($_GET['target_id'] ?? 0);
        $uid = $userId ?: 0;
        if (!$uid) { ok(['liked' => false]); return; }
        $tt = esc($db, $targetType);
        $r = $db->query("SELECT id FROM likes WHERE target_type='$tt' AND target_id=$targetId AND user_id=$uid");
        ok(['liked' => ($r && $r->num_rows > 0)]);
    }
    else err('Method not allowed', 405);
}

// ===== 分类 =====
function handleCategories(mysqli $db, string $method, ?int $id, array $body, bool $isAdmin): void {
    if ($method === 'GET') {
        $r = $db->query("SELECT c.*, (SELECT COUNT(*) FROM articles WHERE category_id=c.id) AS article_count FROM categories c ORDER BY c.sort_order, c.id");
        $list = [];
        while ($row = $r->fetch_assoc()) { $row['id'] = (int)$row['id']; $row['article_count'] = (int)$row['article_count']; $list[] = $row; }
        ok($list);
    }
    elseif ($method === 'POST') {
        if (!$isAdmin) err('仅管理员可操作', 403);
        $name = trim($body['name'] ?? '');
        if (!$name) err('分类名称不能为空');
        $desc = trim($body['description'] ?? '');
        $sort = (int)($body['sort_order'] ?? 0);
        $n = esc($db, $name); $d = esc($db, $desc);
        $db->query("INSERT INTO categories (name,description,sort_order) VALUES ('$n','$d',$sort)");
        ok(['id' => (int)$db->insert_id], '分类已创建');
    }
    elseif ($method === 'PUT') {
        if (!$isAdmin) err('仅管理员可操作', 403);
        if (!$id) err('缺少ID');
        $sets = [];
        foreach (['name','description'] as $f) {
            if (array_key_exists($f, $body)) $sets[] = "`$f`='" . esc($db, (string)$body[$f]) . "'";
        }
        if (array_key_exists('sort_order', $body)) $sets[] = "sort_order=" . (int)$body['sort_order'];
        if (empty($sets)) err('没有需要更新的内容');
        $db->query("UPDATE categories SET " . implode(',', $sets) . " WHERE id=$id");
        ok(null, '已更新');
    }
    elseif ($method === 'DELETE') {
        if (!$isAdmin) err('仅管理员可操作', 403);
        if (!$id) err('缺少ID');
        $db->query("UPDATE articles SET category_id=NULL WHERE category_id=$id");
        $db->query("DELETE FROM categories WHERE id=$id");
        ok(null, '已删除');
    }
    else err('Method not allowed', 405);
}

// ===== 标签 =====
function handleTags(mysqli $db, string $method, ?int $id, array $body, bool $isAdmin): void {
    if ($method === 'GET') {
        $r = $db->query("SELECT * FROM tags ORDER BY name");
        $list = [];
        while ($row = $r->fetch_assoc()) { $row['id'] = (int)$row['id']; $list[] = $row; }
        ok($list);
    }
    elseif ($method === 'POST') {
        if (!$isAdmin) err('仅管理员可操作', 403);
        $name = trim($body['name'] ?? '');
        if (!$name) err('标签名称不能为空');
        $n = esc($db, $name);
        $db->query("INSERT IGNORE INTO tags (name) VALUES ('$n')");
        ok(['id' => (int)$db->insert_id], '标签已创建');
    }
    elseif ($method === 'DELETE') {
        if (!$isAdmin) err('仅管理员可操作', 403);
        if (!$id) err('缺少ID');
        $db->query("DELETE FROM tags WHERE id=$id");
        ok(null, '已删除');
    }
    else err('Method not allowed', 405);
}

// ===== 用户在线状态 =====
function handleSessions(mysqli $db, string $method, ?int $id, array $body, bool $isAdmin, ?int $userId, string $ip): void {
    if ($method === 'GET') {
        if (!$isAdmin) err('仅管理员可查看', 403);
        // 超过5分钟没活动标记下线
        $db->query("UPDATE user_sessions SET is_online=0, logout_time=last_active WHERE is_online=1 AND last_active < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

        $online = $_GET['online'] ?? '';
        $where = '';
        if ($online === '1') $where = 'WHERE s.is_online=1';
        elseif ($online === '0') $where = 'WHERE s.is_online=0';

        $r = $db->query("SELECT s.*, u.username, u.display_name, u.avatar, u.email
            FROM user_sessions s LEFT JOIN users u ON s.user_id=u.id $where ORDER BY s.is_online DESC, s.last_active DESC LIMIT 200");
        $list = [];
        while ($row = $r->fetch_assoc()) { $row['id'] = (int)$row['id']; $row['is_online'] = (int)$row['is_online']; $list[] = $row; }

        $onlineCount = $db->query("SELECT COUNT(*) n FROM user_sessions WHERE is_online=1");
        $on = $onlineCount ? (int)$onlineCount->fetch_assoc()['n'] : 0;
        $offlineCount = $db->query("SELECT COUNT(DISTINCT user_id) n FROM user_sessions WHERE is_online=0");
        $off = $offlineCount ? (int)$offlineCount->fetch_assoc()['n'] : 0;

        ok(['list' => $list, 'online_count' => $on, 'offline_count' => $off]);
    }
    elseif ($method === 'POST') {
        // 心跳上报 / 登录记录
        if (!$userId) err('请先登录', 401);
        $browser = trim($body['browser'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $os = trim($body['os'] ?? '');
        $sessId = session_id() ?: '';
        $eip = esc($db, $ip);
        $br = esc($db, mb_substr($browser, 0, 200));
        $eos = esc($db, mb_substr($os, 0, 100));
        $sid = esc($db, $sessId);

        // 检查是否已有活跃会话
        $r = $db->query("SELECT id FROM user_sessions WHERE user_id=$userId AND is_online=1 AND session_id='$sid'");
        if ($r && $r->num_rows > 0) {
            $existId = (int)$r->fetch_assoc()['id'];
            $db->query("UPDATE user_sessions SET last_active=NOW(), ip_address='$eip' WHERE id=$existId");
        } else {
            $db->query("INSERT INTO user_sessions (user_id,session_id,ip_address,browser,os,login_time,last_active)
                VALUES ($userId,'$sid','$eip','$br','$eos',NOW(),NOW())");
        }
        ok(null, 'ok');
    }
    elseif ($method === 'DELETE') {
        // 强制下线
        if (!$isAdmin) err('仅管理员可操作', 403);
        if (!$id) err('缺少会话ID');
        $db->query("UPDATE user_sessions SET is_online=0, logout_time=NOW() WHERE id=$id");
        ok(null, '已强制下线');
    }
    else err('Method not allowed', 405);
}

// ===== 操作日志 =====
function handleOpLogs(mysqli $db, string $method, bool $isAdmin): void {
    if (!$isAdmin) err('仅管理员可查看', 403);
    if ($method === 'GET') {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $size = min(100, max(1, (int)($_GET['size'] ?? 20)));
        $offset = ($page - 1) * $size;
        $cnt = $db->query("SELECT COUNT(*) n FROM operation_logs");
        $total = $cnt ? (int)$cnt->fetch_assoc()['n'] : 0;
        $r = $db->query("SELECT * FROM operation_logs ORDER BY created_at DESC LIMIT $offset,$size");
        $list = [];
        while ($row = $r->fetch_assoc()) { $row['id'] = (int)$row['id']; $list[] = $row; }
        ok(['list' => $list, 'total' => $total, 'page' => $page]);
    }
    elseif ($method === 'DELETE') {
        $db->query("DELETE FROM operation_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        ok(null, '已清理30天前的日志');
    }
    else err('Method not allowed', 405);
}

// ===== 管理统计（增强版仪表盘） =====
function handleAdminStats(mysqli $db, bool $isAdmin): void {
    if (!$isAdmin) err('仅管理员可查看', 403);

    // 基础统计
    $users = $db->query("SELECT COUNT(*) n FROM users")->fetch_assoc()['n'];
    $articles = $db->query("SELECT COUNT(*) n FROM articles")->fetch_assoc()['n'];
    $moments = $db->query("SELECT COUNT(*) n FROM moments")->fetch_assoc()['n'];
    $comments = $db->query("SELECT COUNT(*) n FROM comments")->fetch_assoc()['n'];
    $views = $db->query("SELECT COALESCE(SUM(view_count),0) n FROM articles")->fetch_assoc()['n'];

    $onlineSess = $db->query("SELECT COUNT(*) n FROM user_sessions WHERE is_online=1");
    $online = $onlineSess ? (int)$onlineSess->fetch_assoc()['n'] : 0;

    // 7天文章趋势
    $trend = [];
    $r = $db->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM articles WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY d ORDER BY d");
    while ($row = $r->fetch_assoc()) $trend[] = $row;

    // 7天评论趋势
    $commentTrend = [];
    $r = $db->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM comments WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY d ORDER BY d");
    while ($row = $r->fetch_assoc()) $commentTrend[] = $row;

    // 分类分布
    $catDist = [];
    $r = $db->query("SELECT c.name, COUNT(a.id) AS count FROM categories c LEFT JOIN articles a ON a.category_id=c.id GROUP BY c.id ORDER BY count DESC LIMIT 10");
    while ($row = $r->fetch_assoc()) $catDist[] = $row;

    // 最新评论5条
    $recentComments = [];
    $r = $db->query("SELECT c.id,c.target_type,c.target_id,c.nickname,c.content,c.created_at,c.ip_address FROM comments c ORDER BY c.created_at DESC LIMIT 5");
    while ($row = $r->fetch_assoc()) { $row['content'] = mb_substr($row['content'], 0, 100); $recentComments[] = $row; }

    // 最新文章5篇
    $recentArticles = [];
    $r = $db->query("SELECT id,title,status,view_count,like_count,comment_count,created_at FROM articles ORDER BY created_at DESC LIMIT 5");
    while ($row = $r->fetch_assoc()) $recentArticles[] = $row;

    ok([
        'user_count' => (int)$users,
        'article_count' => (int)$articles,
        'moment_count' => (int)$moments,
        'comment_count' => (int)$comments,
        'total_views' => (int)$views,
        'online_count' => $online,
        'article_trend' => $trend,
        'comment_trend' => $commentTrend,
        'category_distribution' => $catDist,
        'recent_comments' => $recentComments,
        'recent_articles' => $recentArticles
    ]);
}

// ===== 操作日志记录辅助函数 =====
function logOp(mysqli $db, ?int $userId, string $action, string $detail, string $ip): void {
    $uid = $userId ?: 0;
    $un = '';
    if ($uid) {
        $r = $db->query("SELECT username FROM users WHERE id=$uid");
        $un = $r && ($row = $r->fetch_assoc()) ? $row['username'] : '';
    }
    $a = esc($db, $action); $d = esc($db, mb_substr($detail, 0, 500)); $eip = esc($db, $ip); $n = esc($db, $un);
    $db->query("INSERT INTO operation_logs (user_id,username,action,detail,ip_address) VALUES ($uid,'$n','$a','$d','$eip')");
}
