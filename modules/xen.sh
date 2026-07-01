#!/bin/bash
# Driver for SUSE Xen domains managed by libvirt (xen driver), disks as
# qcow2/raw image files. Same snapshot/blockcommit primitives as KVM,
# only the connection URI differs.
DRIVER_CONNECT_URI="xen:///"

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/libvirt-engine.sh"
