<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$jobs = db()->query(
    'SELECT j.id, j.job_type, j.status, j.started_at, j.finished_at, d.name AS domain_name
     FROM backup_jobs j JOIN domains d ON d.id = j.domain_id
     ORDER BY j.started_at DESC LIMIT 20'
)->fetchAll();

render_header('Dashboard');
?>
<table>
<tr><th>Domain</th><th>Type</th><th>Status</th><th>Started</th><th>Finished</th><th></th></tr>
<?php foreach ($jobs as $j): ?>
<tr>
<td><?= htmlspecialchars($j['domain_name']) ?></td>
<td><?= htmlspecialchars($j['job_type']) ?></td>
<td class="status-<?= htmlspecialchars($j['status']) ?>"><?= htmlspecialchars($j['status']) ?></td>
<td><?= htmlspecialchars($j['started_at']) ?></td>
<td><?= htmlspecialchars($j['finished_at'] ?? '-') ?></td>
<td><a href="/job.php?id=<?= (int) $j['id'] ?>">log</a></td>
</tr>
<?php endforeach; ?>
</table>
<?php render_footer(); ?>
