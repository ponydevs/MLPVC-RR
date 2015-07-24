<p align="center"><img src="https://raw.githubusercontent.com/ponydevs/MLPVC-RR/master/www/img/logo.png" alt=""></p>

<h1 align="center">MLP-VectorClub Requests & Reservations</h1>
<p align="center"><em>An automated system for handling requests & reservations, made for <a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a></em></p>

## What's this site?

This website is a new, automatic way to process and store the requests & reservations users want to make. It's that simple.

In the past, the management of comments under journals was done manually. Because of this, there had to be a person who checks those comments, evaluates them, then updates the journal accordingly. This took time, sometimes, longer time than it should have taken. The group's staff consists of busy people, and we can't expect them to consantly monitor new incoming comments. But, with the help of this website, new entries can be submitted and added to a list, just like the journals, automatically, without having to have someone do this monotonous task.

## Attributions

**Used libraries & icons include:** [jQuery](http://jquery.com/), [qTip2](http://qtip2.com/), [MysqliDb](https://github.com/joshcam/PHP-MySQLi-Database-Class), [Typicons](http://www.typicons.com/), [Uglify-js](https://www.npmjs.com/package/uglify-js), [SASS](http://sass-lang.com/)<br>
**Header font:** [Celestia Medium Redux](http://www.mattyhex.net/CMR/)<br>
**deviantArt logo** *(used on profile pages)* &copy; [DeviantArt](http://www.deviantart.com/)<br>
**Application logo** based on [Christmas Vector of the MLP-VC Mascot](http://pirill-poveniy.deviantart.com/art/Collab-Christmas-Vector-of-the-MLP-VC-Mascot-503196118) by the following artists:

 - [Pirill-Poveniy](http://pirill-poveniy.deviantart.com/)
 - [thediscorded](http://thediscorded.deviantart.com/)
 - [masemj](http://masemj.deviantart.com/)
 - [Ambassad0r](http://ambassad0r.deviantart.com/) *(idea)*
 
**Pre-ban dialog illustration ([direct link](https://raw.githubusercontent.com/ponydevs/MLPVC-RR/master/www/img/ban-before.png)):** [Twilight - What Have I Done?](http://synthrid.deviantart.com/art/Twilight-What-Have-I-Done-355177596) by [Synthrid](http://synthrid.deviantart.com/) *(edited with daylight colors)*<br>
**Post-ban dialog illustration ([direct link](https://raw.githubusercontent.com/ponydevs/MLPVC-RR/master/www/img/ban-after.png)):** [Sad Twilight Sparkle](http://sairoch.deviantart.com/art/Sad-Twilight-Sparkle-354710611) by [Sairoch](http://sairoch.deviantart.com/) *([shading-free version](http://sta.sh/0mddtxyru0w) used)*<br>
**Extrenal link icon** (licensed GPL) taken from [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Icon_External_Link.svg)<br>
Coding and design by [DJDavid98](http://djdavid98.eu/)

## Local setup

Below, you can find a step-by-step guide, which tells you how to set up your machine to be able to properly run & edit the site's code locally.

1. Clone the repository<br>Recommended (free) tool: [Atlassian SourceTree](http://www.sourcetreeapp.com/)
2. Import the files `setup/mlpvc-rr.sql` and `setup/mlpvc-colorguide.sql` to your MySQL server<br><em>Protip: It's good practice to create a separate user that only has access to those databases instead of just using the `root` account everywhere</em> 
3. Copy `setup/conf.php` to `www/conf.php` and fill in the details<br>(you can get the deviantArt client keys for yourself [here](http://www.deviantart.com/developers/register))
	- `DB_HOST`: The IP address or domain of the MySQL server (most likely `localhost`)
	- `DB_USER`: Name of the database user that has access to the 2 databases
	- `DB_PASS`: Password for said user
	- `DA_CLIENT`: deviantArt application's `client_id`
	- `DA_SECRET`: deviantArt application's `client_secret`
	- `GA_TRACKING_CODE`: Google Analytics tracking code (if left blank, disables tracking code)
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
5. Type your domain (or `localhost`) into the address bar of your favourite browser and the home page should appear<br>In order to set up the site permissions and roles, sign in with your deviantArt account, and you'll automatically be granted the developer role in the process.<br><em>Fun fact: Only the developer's user ID is capitalized, making it easy to distinguish from regular users' IDs.</em>
6. You'll also need to set up a JavaScript minifier & CSS preprocessing. The way you decide to do this is entirely up to you, but I'll provide the settings for my setup in the next section.
 - Setting this up is optional, since I'll re-minfy contributed source code myself, if there will be any. This is mostly needed so that you can test locally.
 - If you do not wish to set this up, just delete any minified code and the unminified version will load automatically for both CSS and JS. Just make sure that you do not commit the deletion of minified files with your pull request.

### An example CSS preprocessing & JS minification setup 

This is just the way I have my personal development environment set up locally, but I felt that including this may help others who wish to set their machines up for editing the site's code. Here's what I use:

- Windows 8.1
- PHPStorm IDE + File Watchers plugin (Built-in)
- Ruby > SASS gem
- Node.js > npm Uglify-js package

#### Install necessary tools

1. Install [SASS](http://sass-lang.com/install) (includes install of Ruby)
2. Install [Node.js](https://nodejs.org/download/)<br>If the `PATH` environment variable is not properly updated after install, do the following:
	1. Press `Windows+R`, then type `cmd` and press `Enter`
	2. If you installed to a drive other than `C:`, type `<drive letter>:` to switch to that drive
	3. Type `cd <node install directory>` *(e.g. `cd C:\Program Files\nodejs`)* and press `Enter`
	4. Now you can run the command below
3. Install [autoprefixer](https://www.npmjs.com/package/autoprefixer#cli), [cssnano](https://www.npmjs.com/package/cssnano) and [uglify-js](https://www.npmjs.com/package/uglify-js) using `npm`<br>Run the command: `npm install postcss-cli autoprefixer cssnano uglify-js --global`<br>(You can paste into the comand window by right clikcing and selecting `Paste`)

#### Set up CSS preprocessing

1. Open PHPStorm settings
2. In the sidebar, select `Tools > File Watchers`
3. Press `Alt+Insert`
4. Select `SCSS`
	- Under `Options`, uncheck `Immediate file sychronization`
	- Set `Program` to the path of the SASS gem's `scss.bat` file<br>(e.g. `C:\Ruby22\bin\scss.bat`)
	- Set `Arguments` to `--sourcemap=auto --no-cache --update $FileName$:../css/$FileNameWithoutExtension$.min.css`
	- Set `Output paths to refresh` to `../css/$FileNameWithoutExtension$.min.css:../css/$FileNameWithoutExtension$.min.css.map`
	- Click OK
5. Press `Alt+Insert`
6. Select `<custom>`
	- Name it `Autoprefix & minify CSS` or similar
	- Under `Options`, uncheck `Immediate file sychronization`
	- Set `File type` dropdown to `Cascading Style Sheet`
	- Set `Program` to the path of the `postcss.cmd` file<br>(e.g. `C:\Users\<username>\AppData\Roaming\npm\postcss.cmd`)
	- Set `Arguments` to `-u cssnano --no-autoprefix --no-comments -u autoprefixer $FileName$ -o $FileName$`
	- Set `Working directory` to `$FileDir$`
	- Set `Output paths to refresh` to `$FileName$:$FileName$.map`
	- Click OK
	
#### Set up JS minification

1. Open PHPStorm settings
2. In the sidebar, select `Tools > File Watchers`
3. Press `Alt+Insert`
4. Select `UglifyJS`
	- Under `Options`, uncheck `Immediate file sychronization`
	- Set `Program` to the path of the `uglifyjs.cmd` file<br>(e.g. `C:\Users\<username>\AppData\Roaming\npm\uglifyjs.cmd`)
	- Set `Arguments` to `$FileName$ -o $FileNameWithoutExtension$.min.js --screw-ie8 -c -m --source-map $FileNameWithoutExtension$.min.map`
	- Set `Output paths to refresh` to `$FileNameWithoutExtension$.min.js:$FileNameWithoutExtension$.min.js.map`
	- Click OK
