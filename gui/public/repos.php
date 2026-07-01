<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $db = db();
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO credentials (name, encrypted_passphrase, created_by) VALUES (:n, :p, :u) RETURNING id'
        );
        $stmt->execute([
            'n' => trim((string) $_POST['repo_name']) . ' passphrase',
            'p' => encrypt_secret((string) $_POST['passphrase']),
            'u' => $user['id'],
        ]);
        $credentialId = (int) $stmt->fetchColumn();

        $stmt = $db->prepare(
            'INSERT INTO repos (name, repo_path, keep_within, credential_id) VALUES (:n, :p, :k, :c)'
        );
        $stmt->execute([
            'n' => trim((string) $_POST['repo_name']),
            'p' => trim((string) $_POST['repo_path']),
            'k' => trim((string) $_POST['keep_within']) ?: '1m',
            'c' => $credentialId,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    header('Location: /repos.php');
    exit;
}

$repos = db()->query('SELECT id, name, repo_path, keep_within FROM repos ORDER BY name')->fetchAll();

render_header('Repos & Credentials');
?>
<table>
<tr><th>Name</th><th>Path</th><th>Keep within</th></tr>
<?php foreach ($repos as $r): ?>
<tr>
<td><?= htmlspecialchars($r['name']) ?></td>
<td><?= htmlspecialchars($r['repo_path']) ?></td>
<td><?= htmlspecialchars($r['keep_within']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>Add repo</h2>
<p>The passphrase is encrypted (AES-256-GCM) before it is stored and is only ever decrypted in memory for the duration of a backup/restore/schedule run.</p>
<form method="post">
<input type="hidden" name="action" value="create">
<label>Name <input type="text" name="repo_name" required></label>
<label>Borg repo path <input type="text" name="repo_path" required placeholder="/qnap/rcm-backup or ssh://user@host/repo"></label>
<label>Keep within <input type="text" name="keep_within" value="1m"></label>
<label>Passphrase <input type="password" name="passphrase" required></label>
<button type="submit">Add repo</button>
</form>
<?php render_footer(); ?>
