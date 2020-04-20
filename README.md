# borgbackup
BorgBackup, or Borg, is a de-duplicating open source program that also offers compression and encryption. The main goal of Borg is to provide efficient and secure storage of backed up data. In this article, the author shows how Borg can securely back up a VM, and has added some of his own script modifications to make it more efficient.

I have been trying to back up VMs with various backup tools. This turned out to be expensive for the volume of data that seems to be growing larger every day. It was becoming important for me to back up data and at the same time reduce the cost of storage, which is calculated per GB. If the data is to be stored as per HIPPA regulations, the costs get even higher. Backing up VM files was a challenge, particularly when done live or in real-time.

As a consultant, I had to deliver a cost-effective tool to back up data and store it in an encrypted format.
The client was advised to use KVM on CentOS instead of VMware and Windows. This first option proved successful and the performance was better than the VMware-Windows combination. It was necessary to use a backup tool to store the data in a safe place even when at rest, as it contained PMI data. I scoured Google for all the possible tools prior to deciding on one — and the options were either beyond budget or did not deliver what was needed, i.e., space, efficient storage, speed, data encryption compression, robustness, being time tested, reliable, and easy to use.

I eventually landed at the Borg site (https://borgbackup.readthedocs.io), but I was sceptical about it as no one had mentioned it, nor had I heard of it earlier. But I read through the site, found it very interesting, and decided to try out Borg.

It turned out to perfectly match my needs, as it had:

Space efficient storage
Speed
Data encryption
Compression
Off-site backups
Backups mountable as file systems
And it was free and open source software.

Borg is rather easy to install on multiple platforms and offers single-file binaries that do not require anything to be installed — you can just run them on the following platforms:

Linux
Mac OS X
FreeBSD
OpenBSD and NetBSD (no xattrs/ACLs support or binaries yet)
Cygwin (experimental, no binaries yet)
Linux sub-system of Windows 10 (experimental)
Installation
Borg x86/x64 AMD/Intel compatible binaries (generated with PyInstaller) are available on the release pages for Linux: glibc >= 2.13 (okay for most supported Linux releases). Older glibc releases are untested and may not work.

To install the binary, just drop it into a directory in your PATH, make Borg readable and executable for its users and then you can run it, as follows:

sudo cp borg-linux64 /usr/local/bin/borg
sudo chown root:root /usr/local/bin/borg
sudo chmod 755 /usr/local/bin/borg
Dependencies
To install Borg from a source package (including PIP), I had to install the following dependencies first:

Python 3 >= 3.4.0, plus development headers. Even though Python 3 is not the default Python version on most systems, it is usually available as an optional install.
OpenSSL >= 1.0.0, plus development headers.
libacl and libattr (as libacl depends on libattr), plus development headers.
It has bundled code of the following packages, but Borg, by default (see setup.py if you want to change that) prefers a shared library if it can be found on the system (lib + dev headers) at build time:

liblz4 >= 1.7.0 (r129)
libzstd >= 1.3.0
libb2
Some Python dependencies and Pip automatically installed themselves for me.
I followed the steps indicated at the Borg official site:
https://borgbackup.readthedocs.io/en/stable/quickstart.html.

A step-by-step example is given below:

1. #!/bin/bash
2. set -euo pipefail
3. IFS=$’\n\t’
4. #Written By Arvind GK
5. #Part 2 : To backup Second VM Disk
6. This File name backup-vm-1st-disk.sh
7. DOMAIN=”$1”
8. UUID=$(sudo virsh dumpxml $DOMAIN | grep uuid | cut -d”>” -f2 | cut -d”<” -f1)
9.
10. DISK_LOCATION=$(sudo virsh domblklist “$DOMAIN” | grep hda | tr -s “ “ | cut -d” “ -f2-)
11.
12. DISK_LOCATION1=$(sudo virsh domblklist “$DOMAIN” | grep hdb| tr -s “ “ | cut -d” “ -f2-)
13.
14. # Export Borg encryption key , You can also modify this to using public and private key. Here I have user simple passphrase as example.
15. export BORG_PASSPHRASE=”1234”
16.
17. # Export the rate-limited remote shell
18. #export BORG_RSH=”/usr/local/bin/pv-wrapper ssh”
19.
20. Create the snapshot to a temp file on the Linux drive
21.
22. sudo virsh snapshot-create-as --domain $DOMAIN \
23.
24. tempsnap “Temporary snapshot used while backing up $DOMAIN” \ --disk-only --diskspec $DISK_LOCATION,file=”/img/$DOMAIN-tempsnap.qcow2” \ --atomic
25.
26. Commit the changes that took place in the Windows drive while
27. Sync the virsh metadata with reality by deleting the metadata
28. external image right now but that is not implemented yet)
29.
30. sudo virsh snapshot-delete “$DOMAIN” tempsnap --metadata
31.
32. (they have already been merged back into the original image) sudo rm -f “/img/$DOMAIN-tempsnap.qcow2” sudo rm -f “$DISK_LOCATION1”
33.
34. fi
35. }
36.
37. Stupid hack: make a list of all the files we *don’t* want to save so we can exclude them from the backup
38.
39. find $(dirname “$DISK_LOCATION”) ! -type d ! -wholename “$DISK_LOCATION” | awk ‘{print “sh:” $0;}’ > iso-exclusions
40. the --read-special flag tells borg to follow the symlink & read the block device directly
41.
42. Remove the temp file for the exclusions rm iso-exclusions || /bin/true
43. if it already succeeded and this ensures all guest disk access is on
44. Prune the backups on the server that are older than a certain date echo “Pruning old backups”
45. borg prune -v --list /backup --prefix “$DOMAIN-” --keep-within=1m & wait || /bin/true
46. echo “Done”
I modified this script by adding Borg to compress, encrypt and backup.
I used BLAKE2b for encrypting, as it had no legal implications on licensing and further, no history of anyone breaking the encryption.

Initialising the directory
Using Borg with the local repository, repokey encryption and BLAKE2b is often faster, since Borg 1.1 (to know more about why it is faster, refer to the document at https://blake2.net/blake2.pdf )

$ borg init --encryption=repokey-blake2 /path/to/repo
In the above example I used key encryption ‘repokey-blake2’ to encrypt and the repository path ‘/path/to/repo’ to store the backedup files; you can choose any path that has enough space to backup your files.
Here is an example of how to take a backup of the VM:

/bin/sh /qnap/backup-scripts/rcm-backup-vm-1st-disk.sh Windows2012-VM1
An output sample is given below:

[root@se_vm2 ~]# /backup/backup-scripts/rcm-backup-vm-1st-disk.sh Window2012-VM1 /home/admin/VM-Storage/vm1-c-100gb.qcow2
/home/admin/VM-Storage/vm1-d-150gb.qcow2
Domain snapshot tempsnap created
/home/admin/VM-Storage/vm1-d-150gb.tempsnap
Saving backup as Window2012-VM1-2018-07-11
Archive Window2012-VM1-c-2018-07-11 already exists
 
Block commit: [100 %]
Successfully pivoted
Block commit: [100 %]
Successfully pivoted
Domain snapshot tempsnap deleted
Pruning old backups
Keeping archive: Window2012-VM1-c-2018-07-11 Wed, 2018-07-11 20:43:10
[1068528431bd4a2c 444654f9fba25d51da35 123aee0c71bc062975f76dcf5770]
Done
Target Source
------------------------------------------------
hda /home/admin/VM-Storage/vm1-c-100gb.qcow2
hdb /home/admin/VM-Storage/vm1-d-150gb.qcow2
When you see the logs, you will notice that Borg deletes old backed up files after the specified days.
To prune old backups, the code is as follows:

borg prune -v --list /backup --prefix “$DOMAIN-” --keep-within=1m & wait || /bin/true
The logs will look something like what follows:

Keeping archive: Window2012-VM1-d-2018-07-12 Thu, 2018-07-12 12:40:33
[3601dbf1a489da61 dc05d61d2387c2693 6922e97100c896d0c5f2 42b0fb59352]
Keeping archive: Window2012-VM1-c-2018-07-12 Thu, 2018-07-12 04:00:03
[005c1d93647328f09 37a73363857bd1f151deb942d4c7159a8 d815885620e01f]
Keeping archive: Window2012-VM1-d-2018-07-11 Wed, 2018-07-11 21:40:27
[947c1c62b6163a12 63bab9c6bff1a75ff5b2b86007a521 e1683653e77d285fb3]
Keeping archive: Window2012-VM1-c-2018-07-11 Wed, 2018-07-11 20:43:10
[1068528431bd4a 2c444654f9fba25d51da35123aee0c71bc 062975f76dcf5770]
Done
 
Extracting file from archive
 
# Extract entire archive
$ borg extract /path/to/repo::my-files
 
# Extract entire archive and list files while processing
$ borg extract --list /path/to/repo::my-files
 
# Verify whether an archive could be successfully extracted, but do not write files to disk
$ borg extract --dry-run /path/to/repo::my-files
 
# Extract the "src" directory
$ borg extract /path/to/repo::my-files home/USERNAME/src
 
# Extract the "src" directory but exclude object files
$ borg extract /path/to/repo::my-files home/USERNAME/src --exclude '*.o'
 
# Restore a raw device (must not be active/in use/mounted at that time)
$ borg extract --stdout /path/to/repo::my-sdx | dd of=/dev/sdx bs=10M
Note: For extraction, you will need a passphrase, as you will be asked for it.