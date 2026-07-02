<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $stmt = db()->prepare('INSERT INTO schedules (domain_id, cron_expr) VALUES (:d, :c)');
    $stmt->execute(['d' => (int) $_POST['domain_id'], 'c' => trim((string) $_POST['cron_expr'])]);
    regenerate_cron();
    header('Location: /schedules.php');
    exit;
}

if (isset($_GET['toggle'])) {
    db()->prepare('UPDATE schedules SET enabled = NOT enabled WHERE id = :id')->execute(['id' => (int) $_GET['toggle']]);
    regenerate_cron();
    header('Location: /schedules.php');
    exit;
}

if (isset($_GET['delete'])) {
    db()->prepare('DELETE FROM schedules WHERE id = :id')->execute(['id' => (int) $_GET['delete']]);
    regenerate_cron();
    header('Location: /schedules.php');
    exit;
}

$schedules = db()->query(
    'SELECT s.id, s.cron_expr, s.enabled, d.name AS domain_name FROM schedules s JOIN domains d ON d.id = s.domain_id ORDER BY d.name'
)->fetchAll();
$domains = db()->query('SELECT id, name FROM domains ORDER BY name')->fetchAll();

render_header('Schedules');
?>
<table>
<tr><th>Domain</th><th>Cron</th><th>Enabled</th><th></th></tr>
<?php foreach ($schedules as $s): ?>
<tr>
<td><?= htmlspecialchars($s['domain_name']) ?></td>
<td><?= htmlspecialchars($s['cron_expr']) ?></td>
<td><?= $s['enabled'] ? 'yes' : 'no' ?></td>
<td>
    <a href="/schedules.php?toggle=<?= (int) $s['id'] ?>">toggle</a>
    | <a href="/schedules.php?delete=<?= (int) $s['id'] ?>" onclick="return confirm('Delete schedule?')">delete</a>
</td>
</tr>
<?php endforeach; ?>
</table>

<h2>Add schedule</h2>
<?php if (empty($domains)): ?>
<p>Add a domain first on the <a href="/domains.php">Domains</a> page.</p>
<?php else: ?>
<form method="post">
<input type="hidden" name="action" value="create">
<label>Domain
    <select name="domain_id" required>
        <?php foreach ($domains as $d): ?>
        <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
</label>
<label>Cron expression <input type="text" name="cron_expr" required placeholder="0 2 * * *"></label>
<button type="submit">Add schedule</button>
</form>
<?php endif; ?>
<?php render_footer(); ?>
