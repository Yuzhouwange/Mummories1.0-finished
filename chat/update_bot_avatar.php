<?php
require_once __DIR__ . '/db.php';
$stmt = $conn->prepare("UPDATE users SET avatar = ?, status = 'online' WHERE email = ?");
$stmt->execute([
    'https://ui-avatars.com/api/?name=Bot&background=6366f1&color=fff&bold=true&size=128',
    'bot@mummories.local'
]);
echo "Bot avatar updated, rows=" . $stmt->rowCount() . "\n";
