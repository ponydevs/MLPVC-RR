# Contributing to MLPVC-RR

## Contributions are welcome, but not expected

>**TL;DR:** I didn't make this project open source to make the public at large do my work for me. If you want to contribute something, then go right ahead. If not, that's cool too. Bug reports are always welcome. [Skip to next section](#what-youll-need)

First and foremost, I want to say that for the longest time this project has been maintained by me, and me alone, and I have not been expecting code contributions. The site's codebase was originally made publicly available in order to make independent inspection possible by the [MLP-VectorClub](http://mlp-vectorclub.deviantart.com/) or any person they trust to take a look inside of the software I offered to make for them. When a random person just walks up to the people in charge and says "Hey, here's this thing I made, you should use it" most people would have some reservations, and rightfully so.

I wanted to be as transparent as I could be to signify that I'm not doing this in order to steal their password/wipe the group from the space-time continuum/etc. but to help ease the monotonous task they were facing. Making the site open source and giving them the ability to make sure the code isn't malicious was one of my most important considerations during the time I was making initial contact with the staff.

The lack of this explanation along with my immaturity has caused some confusion during the time I got my first set of contributions, which made me outright "ban" them to the best of my ability. However, as the years passed I've gradually become more familiar with what "open source" truly means, and I realized that being so conservative might not be the best path going forward.

In addition to the reasons above, due to the fact that setting the development environment up is quite a lengthy process I wouldn't expect anyone to go through all the trouble just to be able to help out, so that's why I would rather encourage you to [submit an issue](https://github.com/ponydevs/MLPVC-RR/issues/new) instead if you have any feature ideas or bug reports, and I'll see what I can do. If you do take your time to write up something useful, be it an issue or a PR, and your suggestions/changes makes it into the project, I'll be sure include your name in a dedicated section at the end of [README.md](README.md#thanks-to) as a way to thank you for your work (unless you explicitly ask me not to).

## What you'll need

In order to contribute you will most likely need an environment to test your new changes in. Below you will find the details on how to set up your development environment similar to mine. If you follow these instructions I can guarantee with 50% accuracy that your setup will work as well. These values are for **local development only** and some are not meant to be used in production!

The site is known to work with the following set of software, assuming correct configurations:

| Category          | Name and version                              |
| ----------------- | --------------------------------------------- |
| Operating System  | Debian 9, Linux Mint 18<br>*Using Windows to run the server isn't supported.* |
| Web Server        | nginx 1.13+                                   |
| Database Server   | PostgreSQL 10+                                |
| Asset Compilation | Node.js 8.0+                                  |
| Search Server     | ElasticSearch 6+                              |
| Runtime & Dependencies | Composer (latest)<br>PHP 7.2+            |
| Source Control    | Git<br><small>(the site shows the commit data in the footer, so the binary should be runnable by the application)</small> |
| SSL Certificate   | Self-signed ([get a pair here](https://seinopsys.hu/selfsigned))<br><small>(required to use the site through HTTPS while developing)</small> |

If you can get it to work on some other setup then congratulations, but I cannot guarantee that everything will function as intended. 

## Configuration

## Project-specific commands

This project makes use of [wtfcmd](https://github.com/blunt1337/wtfcmd) to maintain an easy to remember list of command aliases. Just run `wtf` (or equivalent, depending on your install) after cloning to get a brief overview of all the commands used during development. Or you can just view [.wtfcmd.json](.wtfcmd.json) directly, I guess.

### PostrgeSQL

Create a database user with a name and password that you can remember for at least as long as it takes you to enter it in the configuration file. While you're at it, you might as well create the database and extensions. You can find the commands to do this below, but if you're not familiar with the command line you can use [Adminer](https://www.adminer.org/) to run the SQL commands and import the `setup/create_extensions.pg.sql` file.

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

#### php.ini

Make sure `short_open_tag` is enabled in `php.ini`. File uploading should be enabled and reasonable sizes must be set for `upload_max_filesize` and `post_max_size` to be able to upload sprite images to the Color Guide.

Setting `max_execution_time` to `30` *(seconds)* or below is recommended for development in case an infinite loop breaks loose. You never know. It has to be a reasonably big though, because requests to DeviantArt's API can cause script execution to take longer than usual.

#### App-specific config file

You'll need to copy the `setup/conf.php` file into the `includes` directory and change the empty values to whatever your environment uses. Most values are self-explanatory, but you should find explanations for some of them in this document.

##### OAuth credentials

You will need to add OAuth application keys for the following services to your `conf.php` file:

 - [DeviantArt](https://www.deviantart.com/developers/register):  `DA_CLIENT`, `DA_SECRET`
 - [Discord](https://discordapp.com/developers/applications/me): `DISCORD_CLIENT`, `DISCORD_SECRET`
 
##### API keys

If you want to test broken YouTube video removal you will need a Google API key from the [Google API Console](https://console.developers.google.com) that has the [YouTube Data v3 API](https://console.developers.google.com/apis/library/youtube.googleapis.com/) enabled. This key should be set as the value of the `GOOGLE_API_KEY` constant. 

#### Dependencies

Be sure to run `composer install` to download all the dependencies and see if you need to install/enable any other extensions for the site to work.
 
#### Directory permissions

The `fs` and the `vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache`folders must be writable by PHP.

### Node.js

Make sure the `build-essential` package is installed, otherwise some required asset compilation tools might fail to build. Run `npm install -g yarn && yarn install` in the project directory to get the dependencies, then run `npm install -g gulp` so you can use the `gulp` command to kick the file watchers into action.

### Web Server (nginx)

The `php7.2-fpm` package is used used by the provided `setup/nginx.conf` configuration file.

Replace `domain.tld` with the domain of your choice, and change `/path/to/*` placeholders appropriately. `/path/to/www` is the `www` directory of this repository, and `/path/to/error.log` should point to a file in the `logs` directory. The site assumes that this isn't the only site running on your machine, so you'll have to add your domain to your `/etc/hosts` file (`C:\Windows\System32\drivers\etc\hosts` on Windows) on all machines where you want to reach the server from.

```
$ cd /var/www/MLPVC-RR
$ dir
composer.json    fs           LICENSE       phinx.php    tests
composer.lock    graphics     logs          phpunit.xml  vendor
CONTRIBUTING.md  Gulpfile.js  node_modules  README.md    www
db               includes     package.json  setup        yarn.lock
$ chown -R www-data:www-data vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/
$ chmod g+rw fs vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/
$ cp setup/nginx.conf /etc/nginx/sites-available/mlpvector.lc.conf
$ ln -s /etc/nginx/sites-available/mlpvector.lc.conf /etc/nginx/sites-enabled/
$ echo "127.0.0.1 domain.tld" > /etc/hosts
$ nano /etc/nginx/sites-available/mlpvector.lc.conf # Make your changes
$ service nginx reload
```

Optionally add this for better security:

```
$ cd /etc/ssl/certs
$ openssl dhparam -out dhparam.pem 4096
```

The top part of the `nginx` cofiguration is responsible for proxying HTTP requests on port 80 to a WebSocket server called [MLPVC-WS](https://github.com/ponydevs/MLPVC-WS), which is required to allow Let's Encrypt to verify it as a web service & issue SSL certificates to it. Since your development environment is most likely not exposed to the Internet, this part of the nginx configuration can safely be removed/commented out. 

### Importing the database schema

In order to allow changes to the database schema without having to make modifications by hand, the site makes use of [Phinx](https://phinx.org/), a PHP migration manager. All the code necessary to set up the tables and to stay up to date with the latest changes is provided in the form of migrations. Simply run one of the command below, and assuming you've properly set up your `conf.php` file the migrations should run without any issues.

```
$ wtf db migrate
```

You can use `wtf db` To see all Phinx-related commands.

Avoid using the `setup/mlpvc-rr.pg.sql` dump file as it can contain values specific to the live environment such as the database name or username which can differ from your development environment causing all sorts of issues.

### ElasticSearch

Once the application is fully configured, visit the Color Guide and use the "Re-index" button to set up the index(es) used by the application.

## Code style

If you plan to contribute, trying to conform to the project's code style should be the least of your worries. Feel free to use whatever suits you, and if you make a PR with your changes, I'll make the style changes (if necessary) myself after merging.

## Push-to-deploy setup

Git `post-receive` hooks are used for deploying to the production server. This requires that a repository is used on the server. The hook script can be found in the `setup` folder. It needs to be copied to `/path/to/repo/.git/hooks/post-receive` and set to be executable. An additional command is required to allow pushing to the same branch on the server.

```
$ cd /path/to/repo
$ cp setup/post-receive.sh .git/hooks/post-receive
$ chmod +x .git/hooks/post-receive 
$ git config receive.denyCurrentBranch updateInstead
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
$ git remote add production production.vps:/var/www/MLPVC-RR/
$ git push production
```
