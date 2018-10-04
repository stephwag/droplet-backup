# droplet-backup.php
Script for automating backup of DigitalOcean droplets.

This is adapted from https://github.com/icc/snapshot-backup to create snapshots of droplets instead of volumes, and to delete snapshots if it is older than `$threshold`.
