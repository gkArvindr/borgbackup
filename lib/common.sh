#!/bin/bash
# Shared helpers for backup.sh / restore.sh / scheduler.sh.
# Not meant to be executed directly.

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >&2
}

die() {
    log "ERROR: $*"
    exit 1
}

load_config() {
    local conf="${1:-/etc/borgbackup/borgbackup.conf}"
    [ -f "$conf" ] || die "config file not found: $conf"
    # shellcheck disable=SC1090
    source "$conf"

    : "${BORG_REPO:?BORG_REPO not set in $conf}"
    : "${BORG_PASSPHRASE_FILE:?BORG_PASSPHRASE_FILE not set in $conf}"
    [ -f "$BORG_PASSPHRASE_FILE" ] || die "passphrase file not found: $BORG_PASSPHRASE_FILE"

    KEEP_WITHIN="${KEEP_WITHIN:-1m}"
    SNAPSHOT_DIR="${SNAPSHOT_DIR:-/img}"
    WORK_DIR="${WORK_DIR:-/var/lib/borgbackup}"
    mkdir -p "$WORK_DIR"

    export BORG_PASSPHRASE
    BORG_PASSPHRASE="$(cat "$BORG_PASSPHRASE_FILE")"
    export BORG_REPO
}

# Load the driver module matching the given name (kvm|xen) and verify it
# implements the required interface functions.
load_driver() {
    local driver="$1"
    local lib_dir
    lib_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    local driver_file="$lib_dir/../modules/${driver}.sh"
    [ -f "$driver_file" ] || die "unknown driver '$driver' (no $driver_file)"
    # shellcheck disable=SC1090
    source "$driver_file"

    for fn in driver_connect_uri driver_get_disks driver_snapshot_create driver_snapshot_commit; do
        declare -F "$fn" >/dev/null || die "driver '$driver' is missing required function $fn"
    done
}

borg_backup_disks() {
    local domain="$1"; shift
    local archive_suffix="$1"; shift
    local -a disks=("$@")

    local archive="${domain}-${archive_suffix}-$(date +%Y-%m-%d)"
    local exclude_file="$WORK_DIR/${domain}-exclusions"
    : > "$exclude_file"

    local disk
    for disk in "${disks[@]}"; do
        find "$(dirname "$disk")" ! -type d ! -wholename "$disk" \
            | awk '{print "sh:" $0;}' >> "$exclude_file"
    done

    log "Creating backup archive ${archive}"
    sudo -E borg create --progress -v --stats --compression lz4 \
        "${BORG_REPO}::${archive}" \
        "${disks[@]}" --exclude-from "$exclude_file" --read-special

    rm -f "$exclude_file"
}

borg_prune_domain() {
    local domain="$1"
    log "Pruning old backups for ${domain} (keep-within ${KEEP_WITHIN})"
    borg prune -v --list "$BORG_REPO" --prefix "${domain}-" --keep-within "$KEEP_WITHIN"
}

borg_list_archives() {
    local domain="$1"
    borg list "$BORG_REPO" --prefix "${domain}-" --short
}

borg_restore_archive() {
    local archive="$1"
    local target_dir="$2"
    local -a extra_args=("${@:3}")

    mkdir -p "$target_dir"
    log "Restoring ${archive} into ${target_dir}"
    (cd "$target_dir" && sudo -E borg extract --list "${BORG_REPO}::${archive}" "${extra_args[@]}")
}
