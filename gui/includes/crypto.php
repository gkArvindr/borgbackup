<?php
declare(strict_types=1);

// AES-256-GCM at-rest encryption for repo passphrases. The key lives only
// in config.php (or an env var wired into it), never in the database.

function encryption_key(): string
{
    static $key = null;
    if ($key === null) {
        $b64 = $GLOBALS['config']['encryption_key_base64'];
        $decoded = base64_decode($b64, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('encryption_key_base64 must decode to 32 bytes (openssl rand -base64 32)');
        }
        $key = $decoded;
    }
    return $key;
}

function encrypt_secret(string $plaintext): string
{
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('encryption failed');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function decrypt_secret(string $encoded): string
{
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        throw new RuntimeException('invalid encrypted value');
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plaintext === false) {
        throw new RuntimeException('decryption failed (wrong key or corrupted data)');
    }
    return $plaintext;
}
