#!/bin/bash
echo "##### post-receive hook #####"
read oldrev newrev refname
echo "Push triggered update to revision $newrev ($refname)"

CMD_CD="cd $(readlink -nf "$PWD/..")"
CMD_FETCH="git fetch"
CMD_COMPOSER="sudo -u www-data composer install --no-dev 2>&1"
CMD_MIGRATE="sudo -u www-data vendor/bin/phinx migrate"
CMD_YARN="sudo -u www-data yarn install --production"
CMD_REDIS_CLEAR="sudo -u www-data php -f includes/scripts/clear_redis_keys.php commit_id commit_time"

echo "$ $CMD_CD"
eval ${CMD_CD}
echo "$ $CMD_FETCH"
eval ${CMD_FETCH}
echo "$ $CMD_COMPOSER"
eval ${CMD_COMPOSER}
echo "$ $CMD_MIGRATE"
eval ${CMD_MIGRATE}
echo "$ $CMD_YARN"
eval ${CMD_YARN}
echo "$ $CMD_REDIS_CLEAR"
eval ${CMD_REDIS_CLEAR}

echo "##### end post-receive hook #####"
