<?php
declare(strict_types=1);

// Runs a backend script (backup.sh/restore.sh) and captures its full
// output. Every argument is passed through escapeshellarg individually -
// never build the command line by concatenating raw user input.
function run_script(string $script, array $args): array
{
    $scriptsDir = $GLOBALS['config']['scripts_dir'];
    $cmd = escapeshellarg($scriptsDir . '/' . $script);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg((string) $arg);
    }

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['exit_code' => -1, 'output' => 'failed to start process'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return ['exit_code' => $exitCode, 'output' => $stdout . $stderr];
}

// Writes a one-off borgbackup.conf + passphrase file for the lifetime of
// a single manual run and shreds them afterwards. The passphrase is only
// ever decrypted into memory/a 0600 temp file, never persisted in plain
// text longer than the run needs.
function with_domain_config(array $repo, callable $fn)
{
    $dir = sys_get_temp_dir() . '/borgbackup-gui-' . bin2hex(random_bytes(8));
    mkdir($dir, 0700, true);
    $passFile = $dir . '/passphrase';
    $confFile = $dir . '/borgbackup.conf';
    try {
        file_put_contents($passFile, decrypt_secret($repo['encrypted_passphrase']));
        chmod($passFile, 0600);
        $conf = "BORG_REPO=" . escapeshellarg($repo['repo_path']) . "\n"
              . "BORG_PASSPHRASE_FILE=" . escapeshellarg($passFile) . "\n"
              . "KEEP_WITHIN=" . escapeshellarg($repo['keep_within']) . "\n";
        file_put_contents($confFile, $conf);
        chmod($confFile, 0600);
        return $fn($confFile);
    } finally {
        @unlink($passFile);
        @unlink($confFile);
        @rmdir($dir);
    }
}

function fetch_domain_with_repo(int $domainId): array
{
    $stmt = db()->prepare(
        'SELECT d.id, d.name, d.driver, r.repo_path, r.keep_within, c.encrypted_passphrase
         FROM domains d
         JOIN repos r ON r.id = d.repo_id
         JOIN credentials c ON c.id = r.credential_id
         WHERE d.id = :id'
    );
    $stmt->execute(['id' => $domainId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException("domain {$domainId} not found");
    }
    return $row;
}

function start_job(int $domainId, string $type, int $userId): int
{
    $stmt = db()->prepare(
        'INSERT INTO backup_jobs (domain_id, job_type, status, triggered_by) VALUES (:d, :t, :s, :u) RETURNING id'
    );
    $stmt->execute(['d' => $domainId, 't' => $type, 's' => 'running', 'u' => $userId]);
    return (int) $stmt->fetchColumn();
}

function finish_job(int $jobId, bool $success, string $log, ?string $archiveName = null): void
{
    $stmt = db()->prepare(
        'UPDATE backup_jobs SET status = :s, log = :l, archive_name = :a, finished_at = now() WHERE id = :id'
    );
    $stmt->execute([
        's' => $success ? 'success' : 'failed',
        'l' => $log,
        'a' => $archiveName,
        'id' => $jobId,
    ]);
}

function run_backup(int $domainId, int $userId): int
{
    $domain = fetch_domain_with_repo($domainId);
    $jobId = start_job($domainId, 'backup', $userId);
    with_domain_config($domain, function (string $confFile) use ($domain, $jobId) {
        $result = run_script('backup.sh', [
            '--domain', $domain['name'], '--driver', $domain['driver'], '--config', $confFile,
        ]);
        finish_job($jobId, $result['exit_code'] === 0, $result['output']);
    });
    return $jobId;
}

function list_archives(int $domainId): array
{
    $domain = fetch_domain_with_repo($domainId);
    return with_domain_config($domain, function (string $confFile) use ($domain) {
        $result = run_script('restore.sh', ['--list', '--domain', $domain['name'], '--config', $confFile]);
        if ($result['exit_code'] !== 0) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode("\n", $result['output']))));
    });
}

function run_restore(int $domainId, string $archive, string $targetDir, int $userId): int
{
    $domain = fetch_domain_with_repo($domainId);
    $jobId = start_job($domainId, 'restore', $userId);
    with_domain_config($domain, function (string $confFile) use ($archive, $targetDir, $jobId) {
        $result = run_script('restore.sh', ['--archive', $archive, '--target', $targetDir, '--config', $confFile]);
        finish_job($jobId, $result['exit_code'] === 0, $result['output'], $archive);
    });
    return $jobId;
}
