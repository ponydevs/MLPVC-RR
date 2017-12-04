#!/bin/bash
echo "##### post-receive hook #####"
PROJ_DIR="/path/to/proj_dir"
if [ ! -d "$PROJ_DIR" ]; then
    echo "The directory $PROJ_DIR does not exist"
    exit 1
fi
while read oldrev newrev refname
do
    branch=$(git rev-parse --symbolic --abbrev-ref ${refname} 2>/dev/null)
    if [ "master" == "$branch" ]; then
		GIT_DIR="$PROJ_DIR/.git"
        CMD_CD="cd $PROJ_DIR"
		CMD_FETCH="git fetch"
		CMD_CHECKOUT="git -c advice.detachedHead=false checkout $newrev -f"
		CMD_COMPOSER="sudo -u www-data composer install --no-dev 2>&1"
		CMD_MIGRATE="sudo -u www-data vendor/bin/phinx migrate"
		CMD_YARN="sudo -u www-data yarn install"

		echo "$ $CMD_CD"
		eval ${CMD_CD}
		echo "$ $CMD_FETCH"
		eval ${CMD_FETCH}
		echo "$ $CMD_CHECKOUT"
		eval ${CMD_CHECKOUT}
		echo "$ $CMD_COMPOSER"
		eval ${CMD_COMPOSER}
		echo "$ $CMD_MIGRATE"
		eval ${CMD_MIGRATE}
		echo "$ $CMD_YARN"
		eval ${CMD_YARN}
	else
		echo "Push ignored (refname=$refname; branch=$branch)"
    fi
done
echo "##### end post-receive hook #####"
