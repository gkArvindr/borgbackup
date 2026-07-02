<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $driver = $_POST['driver'] ?? '';
    if (!in_array($driver, ['kvm', 'xen'], true)) {
        http_response_code(400);
        die('invalid driver');
    }
    $stmt = db()->prepare('INSERT INTO domains (name, driver, repo_id) VALUES (:n, :d, :r)');
    $stmt->execute([
        'n' => trim((string) $_POST['name']),
        'd' => $driver,
        'r' => (int) $_POST['repo_id'],
    ]);
    header('Location: /domains.php');
    exit;
}

if (isset($_GET['backup'])) {
    run_backup((int) $_GET['backup'], (int) $user['id']);
    header('Location: /index.php');
    exit;
}

$domains = db()->query(
    'SELECT d.id, d.name, d.driver, r.name AS repo_name FROM domains d JOIN repos r ON r.id = d.repo_id ORDER BY d.name'
)->fetchAll();
$repos = db()->query('SELECT id, name FROM repos ORDER BY name')->fetchAll();

render_header('Domains');
?>
<table>
<tr><th>Name</th><th>Driver</th><th>Repo</th><th></th></tr>
<?php foreach ($domains as $d): ?>
<tr>
<td><?= htmlspecialchars($d['name']) ?></td>
<td><?= htmlspecialchars($d['driver']) ?></td>
<td><?= htmlspecialchars($d['repo_name']) ?></td>
<td>
    <a href="/domains.php?backup=<?= (int) $d['id'] ?>" onclick="return confirm('Start backup now?')">Backup now</a>
    | <a href="/restore.php?domain=<?= (int) $d['id'] ?>">Restore</a>
</td>
</tr>
<?php endforeach; ?>
</table>

<h2>Add domain</h2>
<?php if (empty($repos)): ?>
<p>Add a repo first on the <a href="/repos.php">Repos &amp; Credentials</a> page.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="create">
<label>Domain name (libvirt name) <input type="text" name="name" required></label>
<label>Driver
    <select name="driver">
        <option value="kvm">kvm</option>
        <option value="xen">xen</option>
    </select>
</label>
<label>Repo
    <select name="repo_id" required>
        <?php foreach ($repos as $r): ?>
        <option value="<?= (int) $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
        <?php endforeach; ?>
    </select>
</label>
<button type="submit">Add domain</button>
</form>
<?php endif; ?>
<?php render_footer(); ?>
