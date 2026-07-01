<?php
declare(strict_types=1);

function render_header(string $title): void
{
    $user = current_user();
    ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?> - BorgBackup</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<nav>
    <a href="/index.php">Dashboard</a>
    <a href="/domains.php">Domains</a>
    <a href="/repos.php">Repos &amp; Credentials</a>
    <a href="/schedules.php">Schedules</a>
    <?php if ($user): ?>
        <span class="user"><?= htmlspecialchars($user['username']) ?> | <a href="/logout.php">Logout</a></span>
    <?php endif; ?>
</nav>
<main>
<h1><?= htmlspecialchars($title) ?></h1>
<?php
}

function render_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}
