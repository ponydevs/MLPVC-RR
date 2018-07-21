#!/usr/bin/env bash
# GDPR
#
# How to install:
# $ sudo cp $THISFILE /etc/cron.daily/mlpvc-rr
# $ sudo chmod +x /etc/cron.daily/mlpvc-rr
# $ sudo editor /etc/cron.daily/mlpvc-rr

# Change path (no trailing slash)
SCRIPTS_DIR="/path/to/scripts"

if [ ! -d "$SCRIPTS_DIR" ]; then
	>&2 echo "$SCRIPTS_DIR is not a folder"
	exit 1
fi

/usr/bin/php -f "${SCRIPTS_DIR}/clear_old_logged_ips.php"
