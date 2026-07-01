#!/bin/bash
# Restore a Borg archive created by backup.sh.
#
# Usage:
#   restore.sh --list --domain NAME [--config FILE]
#   restore.sh --archive ARCHIVE_NAME --target DIR [--config FILE] [-- extra borg-extract args]
set -euo pipefail
IFS=$'\n\t'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck disable=SC1091
source "$SCRIPT_DIR/lib/common.sh"

CONFIG="/etc/borgbackup/borgbackup.conf"
DOMAIN=""
ARCHIVE=""
TARGET=""
LIST_ONLY=0

while [ $# -gt 0 ]; do
    case "$1" in
        --list) LIST_ONLY=1; shift ;;
        --domain) DOMAIN="$2"; shift 2 ;;
        --archive) ARCHIVE="$2"; shift 2 ;;
        --target) TARGET="$2"; shift 2 ;;
        --config) CONFIG="$2"; shift 2 ;;
        --) shift; break ;;
        *) die "unknown argument: $1" ;;
    esac
done

load_config "$CONFIG"

if [ "$LIST_ONLY" -eq 1 ]; then
    [ -n "$DOMAIN" ] || die "--domain is required with --list"
    borg_list_archives "$DOMAIN"
    exit 0
fi

[ -n "$ARCHIVE" ] || die "--archive is required (or use --list --domain NAME to see options)"
[ -n "$TARGET" ] || die "--target directory is required"

borg_restore_archive "$ARCHIVE" "$TARGET" "$@"

log "Restore of ${ARCHIVE} into ${TARGET} complete"
