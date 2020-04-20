#!/bin/bash
set -euo pipefail
IFS=$'\n\t'
#Written By Arvind GK
#Part 2 : To backup Second VM Disk
    • Disk One Backup File name backup-vm-2nd-disk.s
    • This File name backup-vm-1st-
disk.sh DOMAIN="$1"
UUID=$(sudo virsh dumpxml $DOMAIN | grep uuid | cut -d">" -f2 | cut -d"<" -f1)

DISK_LOCATION=$(sudo virsh domblklist "$DOMAIN" | grep hda | tr -s " " | cut -d" " -f2-)

DISK_LOCATION1=$(sudo virsh domblklist "$DOMAIN" | grep hdb| tr -s " " | cut -d" " -f2-)

# Export Borg encryption key
export BORG_PASSPHRASE="1234"

# Export the rate-limited remote shell
#export BORG_RSH="/usr/local/bin/pv-wrapper ssh"

    • Check if the disk has already been snapshotted from a previous failed run if [ "$DISK_LOCATION" != "/img/$DOMAIN-tempsnap.qcow2" ]; then
        ◦ Create the snapshot to a temp file on the Linux drive

sudo virsh snapshot-create-as --domain $DOMAIN \

tempsnap "Temporary snapshot used while backing up $DOMAIN" \ --disk-only --diskspec $DISK_LOCATION,file="/img/$DOMAIN-tempsnap.qcow2" \ --atomic

    • --disk-only --diskspec hda,file="/mnt/img/$DOMAIN-tempsnap.qcow2" --quiesce --atomic else

DISK_LOCATION=$(sudo qemu-img info "$DISK_LOCATION" | grep "backing file:" | cut -d":" -

f2 | sed -e 's/^[ \t]*//') fi

DISK_LOCATION1=$(sudo virsh domblklist "$DOMAIN" | grep hdb | tr -s " " | cut -d" " -f2-) echo $DISK_LOCATION1
function commitdisk {

DISK_LOCATION=$(sudo virsh domblklist "$DOMAIN" | grep hda | tr -s " " | cut -d" " -f2-) if [ "$DISK_LOCATION" == "/img/$DOMAIN-tempsnap.qcow2" ];
then
        ◦ Commit the changes that took place in the Windows drive while
        ◦ the backup was running back into the original image (merge)

sudo virsh blockcommit "$DOMAIN" $DISK_LOCATION --active --pivot --verbose

sudo virsh blockcommit "$DOMAIN" $DISK_LOCATION1 --active --pivot --verbose




    • Sync the virsh metadata with reality by deleting the metadata
    • for the temporary snapshot (it should be able to delete the
    • external image right now but that is not implemented yet)

sudo virsh snapshot-delete "$DOMAIN" tempsnap --metadata

    • Remove the copy-on-write file created for temporary changes

    • (they have already been merged back into the original image) sudo rm -f "/img/$DOMAIN-tempsnap.qcow2" sudo rm -f "$DISK_LOCATION1"

fi
}

    • Force commitdisk to run even if the script exits abnormally trap commitdisk EXIT

    • Stupid hack: make a list of all the files we *don't* want to save so we can exclude them from the backup

find $(dirname "$DISK_LOCATION") ! -type d ! -wholename "$DISK_LOCATION" | awk '{print "sh:" $0;}' > iso-exclusions
    • Do the backup
echo "Saving backup as $DOMAIN-$(date +%Y-%m-%d)"

    • the --read-special flag tells borg to follow the symlink & read the block device directly

    • (see https://borgbackup.readthedocs.io/en/stable/usage.html#read-special)

sudo -E borg create --progress -v --stats --compression lz4 \ "/qnap/rcm-backup::$DOMAIN-c-$(date +%Y-%m-%d)" \

$(dirname "$DISK_LOCATION") --exclude-from iso-exclusions --read-special & wait || /bin/true

    • Remove the temp file for the exclusions rm iso-exclusions || /bin/true
    • Even though this should be exit-trapped, it won't try to merge again
    • if it already succeeded and this ensures all guest disk access is on
    • the faster NVMe SSD (with the full image)
commitdisk

    • Prune the backups on the server that are older than a certain date echo "Pruning old backups"

borg prune -v --list /qnap/rcm-backup --prefix "$DOMAIN-" --keep-within=1m & wait || /bin/true

/bin/sh /qnap/backup-scripts/rcm-backup-vm-2nd-disk.sh $DOMAIN echo "Done"