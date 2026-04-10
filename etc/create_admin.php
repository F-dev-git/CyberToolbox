<?php
require_once __DIR__ . '/auth_db.php';

if (php_sapi_name() === 'cli') {
    $username = $argv[1] ?? null;
    $password = $argv[2] ?? null;
} else {
    $username = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
}

if (!$username || !$password) {
    echo "Usage (CLI): php create_admin.php username password\n";
    if (php_sapi_name() !== 'cli') {
        echo '<form method="post">Username: <input name="username"><br>Password: <input name="password"><br><button type="submit">Create</button></form>';
    }
    exit;
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    echo "User exists\n";
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ins = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
$ins->execute([$username, $hash, 'admin']);
echo "Admin user created: $username\n";

?>
