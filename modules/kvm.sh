#!/bin/bash
# Driver for KVM/qemu domains managed by libvirt.
DRIVER_CONNECT_URI="qemu:///system"

# shellcheck disable=SC1091
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../lib/libvirt-engine.sh"
