# Contributing to MLPVC-RR

## What you'll need

The site is known to work with the following set of software, assuming correct configurations:

| Category          | Name and version                              |
| ----------------- | --------------------------------------------- |
| Operating System  | Debian 9<br>Linux Mint 18                     |
| Web Server        | nginx 1.10+                                   |
| Database Server   | PostgreSQL 9.6+                               |
| Asset Compliation | Node.js 6.0+                                  |
| Search Server     | ElasticSearch 5.4+                            |
| Runtime & Dependencies | <p>Composer (latest)</p><p>PHP 7.1+ with the following modules installed:</p><ul><li>bcmath</li><li>curl</li><li>gd</li><li>json</li><li>mbsting</li><li>pdo</li><li>pdo_pgsql</li><li>pdo_sqlite</li><li>xml</li></ul> |
| Source Control    | Git<br><small>(as obvious as it may seem, the site shows the commit data in the footer, so the binary is required to be accessible by the application)</small> |
| SSL Certificate   | Self-signed ([get a pair here](https://djdavid98.hu/selfsigned))<br><small>(required to use the site through HTTPS while developing)</small> |

If you can get it to work on some other setup then congratulations, but I cannot guarantee that everything will function as intended. 

## Configuration

This is the way my development enviromnemt is set up, so if you follow these steps I can guarantee with 50% accuracy that your setup will work as well. These values are for **local development only** and some are not meant to be used in production!

### PostrgeSQL

Create a dabase user with a name and password that you can remember for at least as long as it takes you to enter it in the configuration file. While you're at it, you might as well create the database and extensions.YOu can find the commands to do this below, but if you're not familiar with the command line you can use [Adminer](https://www.adminer.org/) to run the SQL commands and import the `setup/create_extensions.pg.sql` file.

```
$ su - postgres
$ psql
psql (9.6.3)
Type "help" for help.

postgres=# CREATE USER "mlpvc-rr" WITH LOGIN PASSWORD '<password>';
postgres=# CREATE DATABASE "mlpvc-rr" WITH OWNER "mlpvc-rr";
postgres=# \c "mlpvc-rr"
postgres=# \i /var/www/MLPVC-RR/setup/create_extensions.pg.sql
postgres=# \q
$ exit
```

### PHP

Make sure `short_open_tag` is enabled in `php.ini`. File uploading should be enabled and a reasonable maximum POST-able/uploadable file size must be set to be able to uplod sprite images in the Color Guide. You'll need to copy the `setup/conf.php` file into the `includes` directory and change the empty values to whatever your environment uses.

The site depends on DeviantArt's API for authentication, which means you'll need OAuth credentails to put in the config file. You can grab a set of those by going to <https://www.deviantart.com/developers/register>

Setting `max_execution_time` to `30` *(seconds)* or below is recommended for development in case an infinite loop breaks loose. You never know. It has to be a reasonably big though, because requests to DeviantArt's API can cause script execution to take longer than usual.

The `fs` and the `vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/`folders must be writable by PHP for the site to function.

### Node.js

Make sure the `build-essential` package is installed, otherwise some required asset compilation tools might fail to build. Run `npm install -g yarn && yarn install` in the project directory to get the dependencies, then run `npm install -g gulp` so you can use the `gulp` command to kick the file watchers into action.

### Web Server (nginx)

The `php7.1-fpm` package is used used by the provided `setup/nginx.conf` configuration file.

Replace `domain.tld` with the domain of your choice, and change `/path/to/*` placeholders approperiately. `/path/to/www` is the `www` directory of this repository, and `/path/to/error.log` should be in the same level as `www` to prevent accessing the logs through the server. 

```
$ cd /var/www/MLPVC-RR
$ dir
composer.json    fs           LICENSE             phinx.php    tests
composer.lock    graphics     mlpvc-rr-error.log  phpunit.xml  vendor
CONTRIBUTING.md  Gulpfile.js  node_modules        README.md    www
db               includes     package.json        setup        yarn.lock
$ chown -R www-data:www-data vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/
$ chmod g+rw fs vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/
$ cp setup/nginx.conf /etc/nginx/sites-available/mlpvc-rr.conf
$ ln -s /etc/nginx/sites-available/mlpvc-rr.conf /etc/nginx/sites-enabled/
$ echo "127.0.0.1 domain.tld" > /etc/hosts
$ nano /etc/nginx/sites-available/mlpvc-rr.conf # Make your changes
$ service nginx reload
```

Optionally add this for better security:

```
$ cd /etc/ssl/certs
$ openssl dhparam -out dhparam.pem 4096
```

The top part of the `nginx` cofiguration is responsible for proxying HTTP requests on port 80 to a WebSocket server called [MLPVC-WS](https://github.com/ponydevs/MLPVC-WS), which is required to allow Let's Encrypt to verify it as a web service & issue SSL certificates to it. Since your development environment is most likely not exposed to the Internet, this part of the nginx configuration can safely be removed/commented out. 

### Importing the database schema

In order to allow changes to the databse schema without having to manually make modifications to the structure by hand, the site makes use of [Phinx](https://phinx.org/), a PHP migration manager. All the code necessary to set up the tables and to stay up to date with the latest changes is provided in the form of migrations. Simply run the command below, and assuimg you've properly set up your `conf.php` file the migrations should run without any issues.

```
$ vendor/bin/phinx migrate
```

Avoid using the `setup/mlpvc-rr.pg.sql` dump file as it can contain values specific to the live environment such as the databse name or user name which can differ from your development environment causing all sorts of issues.

### ElasticSearch

Once the application is fully configured, visit the Color Guide page and use the "Re-index" button to set up the index used by the application.

## Code style

If you plan to contribute, trying to conform to the project's code style should be the least of your worries. Feel free to use whatever suits you, and if you make a PR with your changes, I'll make the style changes (if necessary) myself after merging.

## Why do I not expect contributions?

>**TL;DR:** I didn't make this project open source to make the public at large do all of my work for me. If you want to contribute something, then go right ahead. If not, that's cool too. [Bug reports](https://github.com/ponydevs/MLPVC-RR/issues/new) are welcome.

First and foremost, I want to say that for the longest time this project has been maintained by me, and me alone, and as such, I have not been expecting code contributions. The site's codebase was originally made publicly available in order to make independent inspection possible, thus allowing the adimns of [MLP-VectorClub](http://mlp-vectorclub.deviantart.com/) to take a look inside of the software I offered to make for them. When a random person just walks up to the people in charge and says "Hey, here's this thing I made, you should use it" I imagine they'd proceed with caution, and have lots of questions.

I wanted to give these people the ability to look into exactly what's happening behind the scenes, and more importantly, that I'm not doing this to steal their password/wipe the group from the space-time continuum/etc. but to help ease the montonous task the group admins were facing, due to both time constraints and the limitations of DeviantArt's platform. 

While this initiative proved to be effective in gaining and solidifying the trust of the group's staff, there was one unintended side-effect. Back when this project started the repository was under my name. Not so long after, I was told that I could move the repository the GitHub organization [PonyDevs](https://github.com/ponydevs) which marked the time when I decided to discourage contributions.

Joining the team gave the rest of the members the ability to commit straight into my project, and due to the trust I have already built, I did not want people unknown to the admins making changes to the code without prior consultation, and thus, the notice about not expecting contributions was born, along with a permission change in the organization that made me the only one who could directly commit to the repository. (This change has since been overridden by GitHub's introduction of organization-wide default permissions, which makes this change irrelevant, and in theory, this is still an issue, however it has not been abused by any organization member so far.)

---

Well... that, and the fact that setting the development environment up is quite a lengthy process. I can't expect anyone to go through all of this trouble just to be able to help out, so that's why I encourage you to [submit an issue](https://github.com/ponydevs/MLPVC-RR/issues/new) instead if you have any feature ideas or found bugs, and I'll see what I can do.

If you do take your time to write up something useful and your PR makes it into the project, I'll include your GitHub username in a decidated section at the bottom of README.md as a way to thank you for your hard work (unless you explicitly ask me not to). Your name will show up in the list of contributors on GitHub anyway, but I want to make the list easier to see.

## Push-to-deploy setup

Git `post-receive` hooks are used for deploying to the production server. The hook script can be found in the `setup` folder and needs to be copied to a new bare repository and set to be executable.

```
$ cd /var
$ mkdir bare && cd bare
$ mkdir MLPVC-RR.git && cd MLPVC-RR.git
$ git init --bare
$ cp /var/www/MLPVC-RR/setup/post-receive.sh hooks/post-receive
$ nano hooks/post-receive # Change PROJ_DIR to point to /var/www/MLPVC-RR
$ chmod +x hooks/post-receive 
```

On your local machine, ensure that the SSH configuration is set properly, then add the remote and push changes.

```
$ cat /etc/hosts | grep production.vps
192.0.2.1   production.vps
$ cat ~/.ssh/config
Host production.vps
	Port <port>
	User <user>
	IdentityFile ~/.ssh/id_rsa
$ git remote add production production.vps:/var/bare/MLPVC-RR.git
$ git push production
```
