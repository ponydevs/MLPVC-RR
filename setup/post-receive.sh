#!/usr/bin/env bash
echo "##### post-receive hook #####"
read oldrev newrev refname
echo "Push triggered update to revision $newrev ($refname)"

CMD_CD="cd $(readlink -nf "$PWD/..")"
CMD_FETCH="env -i git fetch"
CMD_COMPOSER="sudo -u www-data composer install --no-dev 2>&1 && sudo chmod -R ug+rw vendor/"
CMD_MIGRATE="sudo -u www-data vendor/bin/phinx migrate"
CMD_NPM="sudo -u www-data npm install --production --no-save"
CMD_REDIS_CLEAR="sudo -u www-data php -f includes/scripts/clear_redis_keys.php commit_id commit_time"

echo "$ $CMD_CD"
eval ${CMD_CD}
echo "$ $CMD_FETCH"
eval ${CMD_FETCH}
echo "$ $CMD_COMPOSER"
eval ${CMD_COMPOSER}
echo "$ $CMD_MIGRATE"
eval ${CMD_MIGRATE}
echo "$ $CMD_NPM"
eval ${CMD_NPM}
echo "$ $CMD_REDIS_CLEAR"
eval ${CMD_REDIS_CLEAR}

echo "##### end post-receive hook #####"
