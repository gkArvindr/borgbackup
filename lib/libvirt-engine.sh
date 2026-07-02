#!/bin/bash
# Shared disk-snapshot engine for any libvirt-managed hypervisor
# (KVM/qemu, Xen via libvirt). A driver module just needs to set
# DRIVER_CONNECT_URI before sourcing this file, and it gets working
# driver_* implementations for free.
#
# Requires: DRIVER_CONNECT_URI to be set by the caller.

: "${DRIVER_CONNECT_URI:?DRIVER_CONNECT_URI must be set before sourcing libvirt-engine.sh}"

_virsh() {
    sudo virsh -c "$DRIVER_CONNECT_URI" "$@"
}

driver_connect_uri() {
    echo "$DRIVER_CONNECT_URI"
}

# Prints "target source" lines for every disk attached to the domain,
# e.g. "hda /home/admin/vm1.qcow2".
driver_get_disks() {
    local domain="$1"
    _virsh domblklist "$domain" --details 2>/dev/null \
        | awk '$2 == "disk" {print $3, $4}'
}

# Creates an external qcow2 snapshot for every disk of $domain so the
# base images can be safely read by borg while the VM keeps running.
# Prints "target snapshot_path" pairs for the caller to back up.
driver_snapshot_create() {
    local domain="$1"

    local diskspecs=()
    local target source snap_path
    while read -r target source; do
        [ -n "$target" ] || continue
        snap_path="${SNAPSHOT_DIR}/${domain}-${target}-tempsnap.qcow2"
        if [ "$source" == "$snap_path" ]; then
            log "Snapshot for ${domain}/${target} already exists from a previous run, reusing"
            continue
        fi
        diskspecs+=(--diskspec "${target},file=${snap_path}")
    done < <(driver_get_disks "$domain")

    [ "${#diskspecs[@]}" -gt 0 ] || die "no disks found for domain ${domain}"

    _virsh snapshot-create-as --domain "$domain" tempsnap \
        "Temporary snapshot used while backing up ${domain}" \
        --disk-only "${diskspecs[@]}" --atomic

    driver_get_disks "$domain"
}

# Merges the temporary snapshot(s) back into the base image(s) and
# removes the snapshot metadata/files. Safe to call even if no
# snapshot is active.
driver_snapshot_commit() {
    local domain="$1"

    local target source
    while read -r target source; do
        [ -n "$target" ] || continue
        if [[ "$source" == "${SNAPSHOT_DIR}/${domain}-${target}-tempsnap.qcow2" ]]; then
            log "Committing snapshot for ${domain}/${target}"
            _virsh blockcommit "$domain" "$target" --active --pivot --verbose
            rm -f "$source"
        fi
    done < <(driver_get_disks "$domain")

    _virsh snapshot-delete "$domain" tempsnap --metadata >/dev/null 2>&1 || true
}
