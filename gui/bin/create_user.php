<?php
declare(strict_types=1);
// Usage: php bin/create_user.php <username> <password>
require __DIR__ . '/../includes/bootstrap.php';

$username = $argv[1] ?? null;
$password = $argv[2] ?? null;
if (!$username || !$password) {
    fwrite(STDERR, "usage: php create_user.php <username> <password>\n");
    exit(1);
}

$stmt = db()->prepare('INSERT INTO users (username, password_hash) VALUES (:u, :p)');
$stmt->execute(['u' => $username, 'p' => password_hash($password, PASSWORD_DEFAULT)]);
echo "Created user {$username}\n";
