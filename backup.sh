#!/bin/bash
# Backup a libvirt VM (KVM or Xen) with Borg.
#
# Usage: backup.sh --domain NAME --driver kvm|xen [--config FILE]
set -euo pipefail
IFS=$'\n\t'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib/common.sh"

CONFIG="/etc/borgbackup/borgbackup.conf"
DOMAIN=""
DRIVER=""

while [ $# -gt 0 ]; do
    case "$1" in
        --domain) DOMAIN="$2"; shift 2 ;;
        --driver) DRIVER="$2"; shift 2 ;;
        --config) CONFIG="$2"; shift 2 ;;
        *) die "unknown argument: $1" ;;
    esac
done

[ -n "$DOMAIN" ] || die "--domain is required"
[ -n "$DRIVER" ] || die "--driver is required (kvm|xen)"

load_config "$CONFIG"
load_driver "$DRIVER"

commit_and_exit() {
    driver_snapshot_commit "$DOMAIN"
}
trap commit_and_exit EXIT

log "Snapshotting disks for domain ${DOMAIN} (driver: ${DRIVER})"
disks=()
while read -r target source; do
    [ -n "$source" ] || continue
    disks+=("$source")
done < <(driver_snapshot_create "$DOMAIN")

[ "${#disks[@]}" -gt 0 ] || die "no disk snapshots produced for ${DOMAIN}"

borg_backup_disks "$DOMAIN" "$DRIVER" "${disks[@]}"

# commit_and_exit runs via the EXIT trap, then prune.
driver_snapshot_commit "$DOMAIN"
trap - EXIT

borg_prune_domain "$DOMAIN"

log "Backup of ${DOMAIN} complete"
