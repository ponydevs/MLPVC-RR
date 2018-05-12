#!/usr/bin/env bash
# GDPR
# sudo ln -s $THISFILE /etc/cron.daily/mlpvc-rr
# sudo chmod +x /etc/cron.daily/mlpvc-rr

SETUP_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

/usr/bin/php -f "${SETUP_DIR}/../includes/scripts/clear_old_logged_ips.php"
