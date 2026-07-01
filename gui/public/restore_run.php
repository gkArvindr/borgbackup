<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('POST required');
}

$domainId = (int) $_POST['domain_id'];
$archive = trim((string) $_POST['archive']);
$targetDir = trim((string) $_POST['target_dir']);

$jobId = run_restore($domainId, $archive, $targetDir, (int) $user['id']);
header('Location: /job.php?id=' . $jobId);
exit;
