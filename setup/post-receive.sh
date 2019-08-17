#!/usr/bin/env bash
echo "##### post-receive hook #####"
read oldrev newrev refname
echo "Push triggered update to revision $newrev ($refname)"

RUN_FOR_REF="refs/heads/master"
if [[ "$refname" ==  "$RUN_FOR_REF" ]]; then
    GIT="env -i git"
    CMD_CD="cd $(readlink -nf "$PWD/..")"
    CMD_FETCH="$GIT fetch"
    CMD_COMPOSER="sudo chmod -R ug+rw vendor/ && sudo -u www-data composer install --no-dev 2>&1"
    CMD_MIGRATE="sudo -u www-data vendor/bin/phinx migrate"
    CMD_NPM="sudo -u www-data npm install --production --no-save"
    CMD_BUILD="sudo -u www-data npm run build"
    CMD_REDIS_CLEAR="sudo -u www-data php -f scripts/clear_redis_keys.php commit_info"
    CMD_API_DOCS="sudo -u www-data npm run api:schema"

    echo "$ $CMD_CD"
    eval ${CMD_CD}
    echo "$ $CMD_FETCH"
    eval ${CMD_FETCH}
    echo "$ $CMD_COMPOSER"
    eval ${CMD_COMPOSER}
    echo "$ $CMD_MIGRATE"
    eval ${CMD_MIGRATE}

    if $GIT diff --name-only $oldrev $newrev | grep "^package-lock.json"; then
        echo "$ $CMD_NPM"
        eval $CMD_NPM
    else
        echo "# Skipping npm install, lockfile not modified"
    fi

    if $GIT diff --name-only $oldrev $newrev | grep "^assets/"; then
        echo "$ $CMD_BUILD"
        eval $CMD_BUILD
    else
        echo "# Skipping asset rebuild, no changes in assets folder"
    fi

    if $GIT diff --name-only $oldrev $newrev | grep "^app/Controllers/API/"; then
        echo "$ $CMD_API_DOCS"
        eval $CMD_API_DOCS
    else
        echo "# Skipping API schema generation, no changes in API Controllers folder"
    fi

    echo "$ $CMD_REDIS_CLEAR"
    eval ${CMD_REDIS_CLEAR}
else
    echo "Ref does not match $RUN_FOR_REF, exiting."
fi

echo "##### end post-receive hook #####"
