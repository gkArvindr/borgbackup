<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = (string) ($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (attempt_login($username, $password)) {
        header('Location: /index.php');
        exit;
    }
    $error = 'Invalid username or password';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Login - BorgBackup</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="login">
<h1>BorgBackup Login</h1>
<?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <label>Username <input type="text" name="username" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit">Log in</button>
</form>
</main>
</body>
</html>
