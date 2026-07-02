<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT j.*, d.name AS domain_name FROM backup_jobs j JOIN domains d ON d.id = j.domain_id WHERE j.id = :id'
);
$stmt->execute(['id' => $id]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    die('job not found');
}

render_header('Job #' . $job['id'] . ' - ' . $job['domain_name']);
?>
<p>Type: <?= htmlspecialchars($job['job_type']) ?> | Status: <?= htmlspecialchars($job['status']) ?></p>
<pre class="log"><?= htmlspecialchars($job['log'] ?? '(still running)') ?></pre>
<?php render_footer(); ?>
