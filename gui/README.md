# BorgBackup GUI

A plain-PHP front end for `backup.sh` / `restore.sh` / `scheduler.sh` (see the
repo root), backed by Postgres. Lets a user manage borg repos + encrypted
passphrases, register libvirt domains (KVM or Xen), trigger manual backups
and restores, and manage cron-based schedules.

## Setup

1. Create the database and load the schema:
   ```
   createdb borgbackup
   psql borgbackup < db/schema.sql
   ```
2. Copy the config and fill it in:
   ```
   cp config/config.php.example config/config.php
   openssl rand -base64 32   # paste into encryption_key_base64
   ```
3. Point `scripts_dir` at the directory containing `backup.sh`, `restore.sh`,
   `scheduler.sh` from the repo root (they must be runnable as the web
   server user via sudo, same as before).
4. Create the first login:
   ```
   php bin/create_user.php admin 'a-strong-password'
   ```
5. Serve `public/` with your web server of choice (or `php -S 0.0.0.0:8080 -t public`
   for local testing).

## How secrets are handled

Repo passphrases are encrypted at rest with AES-256-GCM using a key that
lives only in `config.php` (not the database). For manual backup/restore
runs, the passphrase is decrypted into a 0600 temp file for the duration of
the run and shredded immediately after. For scheduled runs, a persistent
per-domain config + passphrase file is written under `domains_conf_dir`
(0600) so cron can invoke `backup.sh` without the web process being
involved.
