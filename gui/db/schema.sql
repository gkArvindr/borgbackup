CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(64) UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- encrypted_passphrase is base64(iv[12] || gcm_tag[16] || ciphertext), see includes/crypto.php.
-- The AES key never lives in this database; it comes from config.php / an env var.
CREATE TABLE credentials (
    id SERIAL PRIMARY KEY,
    name VARCHAR(128) NOT NULL,
    encrypted_passphrase TEXT NOT NULL,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE repos (
    id SERIAL PRIMARY KEY,
    name VARCHAR(128) UNIQUE NOT NULL,
    repo_path TEXT NOT NULL,
    keep_within VARCHAR(16) NOT NULL DEFAULT '1m',
    credential_id INTEGER NOT NULL REFERENCES credentials(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE domains (
    id SERIAL PRIMARY KEY,
    name VARCHAR(128) UNIQUE NOT NULL,
    driver VARCHAR(16) NOT NULL CHECK (driver IN ('kvm', 'xen')),
    repo_id INTEGER NOT NULL REFERENCES repos(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE backup_jobs (
    id SERIAL PRIMARY KEY,
    domain_id INTEGER NOT NULL REFERENCES domains(id),
    job_type VARCHAR(16) NOT NULL CHECK (job_type IN ('backup', 'restore')),
    status VARCHAR(16) NOT NULL DEFAULT 'running' CHECK (status IN ('running', 'success', 'failed')),
    archive_name VARCHAR(255),
    started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    finished_at TIMESTAMPTZ,
    log TEXT,
    triggered_by INTEGER REFERENCES users(id)
);

CREATE TABLE schedules (
    id SERIAL PRIMARY KEY,
    domain_id INTEGER NOT NULL REFERENCES domains(id),
    cron_expr VARCHAR(64) NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
