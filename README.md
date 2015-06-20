<p align="center"><img src="https://raw.githubusercontent.com/DJDavid98/MLPVC-RR/master/www/img/logo.png" alt=""></p>

<h1 align="center">MLP-VectorClub Requests & Reservations</h1>
<p align="center"><em>An automated system for handling requests & reservations, made for <a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a></em></p>

## What's this site?

This website is a new, automatic way to process and store the requests & reservations users want to make. It's that simple.

In the past, the management of comments under journals was done manually. Because of this, there had to be a person who checks those comments, evaluates them, then updates the journal accordingly. This took time, sometimes, longer time than it should have taken. The group's staff consists of busy people, and we can't expect them to consantly monitor new incoming comments. But, with the help of this website, new entries can be submitted and added to a list, just like the journals, automatically, without having to have someone do this monotonous task.

## Attributions

**Used libraries & icons include:** [jQuery](http://jquery.com/), [MysqliDb](https://github.com/joshcam/PHP-MySQLi-Database-Class), [Typicons](http://www.typicons.com/), [Uglify-js](https://www.npmjs.com/package/uglify-js), [SASS](http://sass-lang.com/)<br>
**Header font:** [Celestia Medium Redux](http://www.mattyhex.net/CMR/)<br>
**deviantArt logo** *(used on profile pages)* &copy; [DeviantArt](http://www.deviantart.com/)<br>
**Application logo** based on [Christmas Vector of the MLP-VC Mascot](http://pirill-poveniy.deviantart.com/art/Collab-Christmas-Vector-of-the-MLP-VC-Mascot-503196118) by the following artists:

 - [Pirill-Poveniy](http://pirill-poveniy.deviantart.com/)
 - [thediscorded](http://thediscorded.deviantart.com/)
 - [masemj](http://masemj.deviantart.com/)
 - [Ambassad0r](http://ambassad0r.deviantart.com/) *(idea)*
 
**Extrenal link icon** (licensed GPL) taken from [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Icon_External_Link.svg)<br>
Coding and design by [DJDavid98](http://djdavid98.eu/)

## Local setup

Below, you can find a step-by-step guide, which tells you how to set up your machine to be able to properly run & edit the site's code locally.

1. Clone the repository<br>Recommended (free) tool: [Atlassian SourceTree](http://www.sourcetreeapp.com/)
2. Import `setup/database.sql` to a new, empty database of your choice
3. Copy `setup/conf.php` to `www/conf.php` and fill in the details<br>(you can get the deviantArt client keys for yourself [here](http://www.deviantart.com/developers/register))
	- `DB_HOST`: Database host (most likely `localhost`)
	- `DB_NAME`: Name of the database you imported the SQL file into in the previous step
	- `DB_USER`: Database user that has access to said database
	- `DB_PASS`: Password for the user
	- `DA_CLIENT`: deviantArt application's `client_id`
	- `DA_SECRET`: deviantArt application's `client_secret`
	- `GA_TRACKING_CODE`: Google Analytics tracking code
4. Configure your server
    1. If you're using NGINX, you can see a sample configuration in `setup/nginx.conf`. Modify this file as described below, move it to `/etc/nginx/sites-available` (you should rename it, but you don't have to) and create a symlink in `/etc/nginx/sites-enabled` to enable the configuration.
        - Change `server_name` to your domain or use `localhost`
        - Set the `root` to the `www` directory of this repository on your machine
        - In the last location block, make sure that the `php5-fpm` configuration matches your server setup, if not you'll have to edit that as well.
        - `service nginx restart`
    2. If you're using Apache (on Windows), then check `setup/apache.conf`. Modify the settings as decribed below, then copy the edited configuration to the end of `<apache folder>/conf/extra/httpd-vhosts.conf` (or to wherever you want, this just seems to be a good place for storing the virtual host).
        - Change `ServerName` to your domain or use `localhost`
        - Set the `DocumentRoot` and the first `Directory` block's path to the `www` directory of this repository on your machine
        - You'll also need to get the Certificate Authority bundle file (`ca-bundle.crt`) from [this](https://github.com/bagder/ca-bundle/) repository, place it somewhere in your system, and note it's file path. Then, in `php.ini`, set `curl.cainfo` to the absolute file path of said bundle file.
        - Restart Apache
5. Type your domain (or `localhost`) into the address bar of your favourite browser and the home page should appear
6. You'll also need to set up a JavaScript minifier & CSS preprocessing. The way you decide to do this is entirely up to you, but I'll provide the settings for my setup in the next section

### An example CSS preprocessing & JS minification setup 

This is just the way I have my personal development environment set up locally, but I felt that including this may help others who wish to set their machines up for editing the site's code. Here's what I use:

- Windows 8.1
- PHPStorm IDE + File Watchers plugin (Built-in)
- Ruby > SASS gem
- Node.js > npm Uglify-js package

#### Set up CSS preprocessing

1. [Install SASS](http://sass-lang.com/install)
2. Open PHPStorm settings
3. In the sidebar, select `Tools > File Watchers`
4. Press `Alt+Insert`
5. Select `SCSS`
	- Under `Options`, uncheck `Immediate file sychronization`
	- Set `Program` to the path of the SASS gem's `scss.bat` file<br>(e.g. `C:\Ruby22\bin\scss.bat`)
	- Set `Arguments` to `--sourcemap=none --style compressed --no-cache --update $FileName$:../css/$FileNameWithoutExtension$.min.css`
	- Set `Output paths to refresh` to `../css/$FileNameWithoutExtension$.min.css`
	- Click OK
	
#### Set up JS minification

1. Install [Node.js](https://nodejs.org/download/)
2. Install [uglify-js](https://www.npmjs.com/package/uglify-js) using npm
2. Open PHPStorm settings
3. In the sidebar, select `Tools > File Watchers`
4. Press `Alt+Insert`
5. Select `UglifyJS`
	- Under `Options`, uncheck `Immediate file sychronization`
	- Set `Program` to the path of the `uglifyjs.cmd` file<br>(e.g. `C:\Users\<username>\AppData\Roaming\npm\uglifyjs.cmd`)
	- Set `Arguments` to `$FileName$ -o $FileNameWithoutExtension$.min.js --screw-ie8 -c -m`
	- Set `Output paths to refresh` to `$FileNameWithoutExtension$.min.js`
	- Click OK