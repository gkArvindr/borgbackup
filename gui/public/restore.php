<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$domainId = (int) ($_GET['domain'] ?? 0);
$stmt = db()->prepare('SELECT id, name FROM domains WHERE id = :id');
$stmt->execute(['id' => $domainId]);
$domain = $stmt->fetch();
if (!$domain) {
    http_response_code(404);
    die('domain not found');
}

$archives = list_archives($domainId);

render_header('Restore - ' . $domain['name']);
?>
<?php if (empty($archives)): ?>
<p>No archives found for this domain yet.</p>
<?php else: ?>
<form method="post" action="/restore_run.php">
<input type="hidden" name="domain_id" value="<?= (int) $domain['id'] ?>">
<label>Archive
    <select name="archive" required>
        <?php foreach ($archives as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option>
        <?php endforeach; ?>
    </select>
</label>
<label>Restore to directory
    <input type="text" name="target_dir" required placeholder="/restore/<?= htmlspecialchars($domain['name']) ?>">
</label>
<button type="submit" onclick="return confirm('Start restore now?')">Restore</button>
</form>
<?php endif; ?>
<?php render_footer(); ?>
