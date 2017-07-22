# Contributing to MLPVC-RR

## What you'll need

The site is known to work with the following set of software, assuming correct configurations:

| Category          | Name and version                              |
| ----------------- | --------------------------------------------- |
| Operating System  | Windows 10/8.1/7<br>Debian 8<br>Linux Mint 18 |
| Web Server        | nginx 1.10+<br>Apache 2.4+                    |
| Database Server   | PostgreSQL 9.5+                               |
| Asset Compliation | Node.js 6.0+                                  |
| Search Server     | ElasticSearch 5.4+                            |
| Runtime & Dependencies | <p>Composer (latest)</p><p>PHP 7.1+ with the following modules installed:</p><ul><li>curl</li><li>gd</li><li>json</li><li>mbsting</li><li>pdo</li><li>pdo_pgsql</li><li>pdo_sqlite</li><li>xml</li></ul> |
| Source Control    | Git<br><small>(as obvious as it may seem, the site shows the commit data in the footer, so the binary is required to be accessible by the application)</small> |
| SSL Certificate   | Self-signed ([get a pair here](https://djdavid98.hu/selfsigned))<br><small>(required to use the site through HTTPS while developing)</small> |

If you can get it to work on some other setup then congratulations, but I cannot guarantee that everything will function as intended. 

## Configuration

This is the way my development enviromnemt is set up, so if you follow these steps I can guarantee with 50% accuracy that your setup will work as well. These values are for **local development only** and some are not meant to be used in production!

### PostrgeSQL

The user must be named `mlpvc-rr` otherwise the permissions in the DB exports will not be set correctly and you'll likely get swamped with issues related to a missing role/user. WHile you're at it you might as well create the required database and extension.

```
$ su - postgres
$ psql
psql (9.5.4)
Type "help" for help.

postgres=# CREATE USER "mlpvc-rr" WITH LOGIN PASSWORD '<password>';
postgres=# CREATE DATABASE "mlpvc-rr" WITH OWNER "mlpvc-rr";
postgres=# CREATE EXTENSION citext;
postgres=# \q
$ exit
```

I recommend [Adminer](https://www.adminer.org/) for schema importing/editing, unless you know the command line well enough.

### PHP

Make sure `short_open_tag` is set to `On` or another truth-y value in `php.ini`. File uploading should be enabled and a reasonable maximum POST-able/uploadable file size must be set to be able to uplod sprite images in the Color Guide. You'll need to copy the `setup/conf.php` file into the `includes` directory and change the empty values to whatever your environment uses.

Optionally, use the `xdebug` extension or [Kint](http://raveren.github.io/kint/) to ease debugging with stack traces/cleaner var_dump outputs. Setting `max_execution_time` to `30` *(seconds)* or below is also recommended for development in case an infinite loop breaks loose. You never know. It has to be a reasonably big though because requests to DeviantArt's API can cause script execution to take longer than usual.

The `fs` and the `vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/`folders must be writeable by PHP for the site to function.

### Node.js

On Linux systems, make sure the `build-essential` package is installed, otherwise some required asset compilation tools might fail to build. Run `npm install -g yarn && yarn install` in the project directory to get the dependencies, then run `npm install -g gulp` so you can use the `gulp` command to kick the file watchers into action.

### Web Server

When using `nginx` the `php7.1-fpm` package is used used by the provided configuration file. The provided Apache configuration is assumes the initial setup provided by XAMPP.

Replace `domain.tld` with the domain of your choice, and set `/path/to/*` placeholders approperiately in the configuration file. `/path/to/www` is the `www` directory of this repository, and `/path/to/error.log` should be in the same level as `www` to prevent accessing the logs through the server. 

#### Apache (Windows)

Realistically speaking, nobody in their sane mind would use Apache, but for the time being [XAMPP](https://www.apachefriends.org/) seems to be the easiest one to set up for a local Windows development environment, just make sure to uncheck all of the garbage (everything that's not Apache & PHP) when installing. <small>You might as well download PHP separately, but it's easier for me to set everything PHP-related up in one step.</small>

You need to open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add the contents of the [apache.conf](https://github.com/ponydevs/MLPVC-RR/blob/master/setup/apache.conf) file to the end, replacing the placeholders with actual values. You should also add whatever you set `domain.tld` to into your `C:\Windows\system32\drivers\etc\hosts` file as 

    127.0.0.1 domain.tld
    
This kind of setup will allow you to easily run other websites from Apache later on, but I sincerely hope you're not stuck with it. 

#### nginx

```
$ cd /var/www/MLPVC-RR
$ dir
graphics     LICENSE    package.json  setup
Gulpfile.js  error.log  README.md     www
$ chown -R www-data:www-data ./
$ chmod g+rw ./
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

Part of the `nginx` cofiguration is responsible to handling the communication between a WebSocket server called [MLPVC-WS](https://github.com/ponydevs/MLPVC-WS). If you do not want to use said server during development, the part of the nginx configuration that's above the main server block can safely be removed/commented out. 

### Importing the `*.pg.sql` file

Inside the `setup` folder you'll find `mlpvc-rr.pg.sql` as well as some bash/batch files to re-create it in case of a database schema change. Import this file to the previously created database.

On a Linux machine this can be done like so:

```
$ su - postgres
$ psql
psql (9.6.n)
Type "help" for help.
postgres=# \c "mlpvc-rr"
postgres=# \i /var/www/MLPVC-RR/setup/mlpvc-rr.pg.sql
postgres=# \q
```

### ElasticSearch

Set the server port in `conf.php` if it's different from the default. Once the application is fully configured, visit the Color Guide page and use the "Re-index" button to set up the index used by the application.

## Code style

>**TL;DR:** Use whatever suits you. If you make a PR with your changes, I'll make the style changes (if needed) for you after merging, so you might as well skip this part. 

All code must be indented with hard tabs unless other types of indentation are required for proper functionality. The only exceptin is when there are characters in front of the code being indented; in those cases, spaces should be used. Tabs should ideally be 4 spaces wide, except for `*.scss` files, where this is set to 2 instead. Blocks should always be collapsed to a single line when possible.

PRs that do not follow these style guides will still be accepted but will likely be converted to use the styles outlined in this section. Each section has a specific example of some code showing the style in action. **Note:** Tabs were deliberately converted to spaces in the below examles to indicate their desired width setting.

### Ternary operator (all languages)

```js
$var = $short ? 'value' : 'other value';
$var = $long 
    ? 'This is one very long value which is far too long to fit nicely on a single line'
    : 'This is another very long value which is also too long to fit nicely on a single line'	
```

### PHP

If the indetation level would overlap with the PHP opening tag, a short open tag should be used. Variables should be included within double-quoted strings instead of appending with `.` when possible. There's a JSON and a RegExp class that comes with this project, and the methods contained within are preferred over raw `json_*` and `preg_*` functions.

Example:

```php
<?php
    $a = $_GET['a']; ?>
    <p><?="Time:\n"?>
<?  if ($a === 'c')
        echo 'ISO @ '.date($a);
    else {
        $a = htmlspecialchars($a);
        echo "Nice try, but we only accept ISO format (you entered $a)";
        unset($a);
    } ?></p>
```

### JavaScript

The asset pipeline includes [Babel](https://babeljs.io/) which enables the use of ES2015 syntax elements like classes, arrow functions, ´let´ and template strings. Old code need not be converted, but if changes are made, these syntax elements must be utilizedwhen approperiate. The use of `var` is **highly discouraged**. If jQuery selectors are used more than once within a single file they should be cached by setting them to a variable with the `$` prefix in its name.

Example:

```js
(function($){
    let $element = $('#selector').children().eq(0),
        element = $element.get(0),
        date = Date.now(),
        theEnd = new Date('2012-12-12T12:12:12');
    
    if (date < theEnd)
        element.innerHTML = 'The end is neigh!';
    else {
        let daysPassed = Math.round((date-theEnd)/1000/60/60/24);
        $element.html(`${daysPassed} days have passed since the apocalypse`);
        if ($element.is('div'))
            $element.addClass('survivor');
    }
})(jQuery);
```

### SASS / SCSS

Blocks with a single property inside should be collapsed to a single line without a trailing semicolon. For values shared between multiple `.scss` files or for color/font/size values used more than once, a variable should be used to make updating the value in the future easier. Nested blocks should be used to normalize selectors wherever possible. The ability to do in-place calculations should be used when pozitioning/resizing an element if it depends on another element's position/size.

Example:

```scss
$Breakpoint: 650px;
$SidebarWidth: 300px;

@media all and (min-width: $Breakpoint){
  #sidebar {
    a, .link {
      color: $Link;
      display: inline-block;
    }
  }
}

#sidebar {
  width: $SidebarWidth;
  height: 100%;
}

#content {
  width: calc(100% - #{$SidebarWidth});

  &, * { box-sizing: border-box }
  
  > h1 {
    font-size: 32px;
    text-decoration: underline;
  }
  
  .hidden { display: none }
}
```

## Why do I not expect contributions?

>**TL;DR:** I didn't make this project open source to make the public at large do all of my work for me. If you want to contribute something, then go right ahead. If not, that's cool too. [Bug reports](https://github.com/ponydevs/MLPVC-RR/issues/new) are welcome.

First and foremost, I want to say that for the longest time this project has been maintained by me, and me alone, and as such, I have not been expecting code contributions. The site's codebase was originally made publicly available in order to make independent inspection possible, thus allowing the adimn of [MLP-VectorClub](http://mlp-vectorclub.deviantart.com/) take a look inside of the software I offered to make for them. When a random person just walks up to the people in charge and says "Hey, here's this thing I made, you should use it" I imagine they'd proceed with caution, and have lots of questions.

<small>Technically I was already a member of the group before, so I wasn't a complete stranger, so let's imagine one person from a group of 400 classmates (who barely knew each other) handing something to one of 6 teachers.</small>

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
$ git remote add production vinyl.vps:/var/bare/MLPVC-RR.git
$ git push production
```
