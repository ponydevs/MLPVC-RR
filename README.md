An automated system for handling requests & reservations, made for MLP-VectorClub by [DJDavid98](http://djdavid98.eu)

## Local setup

In order to get the site up and running locally you'll need to set up a few things in advance. Here's a step-by-step guide on getting everything to work on your local machine:

1. Clone the repository
2. Import `setup/database.sql` to a new, empty database of your choice
3. Copy `setup/conf.php` to `www/conf.php` and fill in the details<br>(you can get the deviantArt client keys for yourself [here](http://www.deviantart.com/developers/register))
	- `DB_HOST`: Database host (most likely `localhost`)
	- `DB_NAME`: Name of the database you imported the SQL file into in the previous step
	- `DB_USER`: Database user that has access to said database
	- `DB_PASS`: Password for the user
	- `DA_CLIENT`: deviantArt application's `client_id`
	- `DA_SECRET`: deviantArt application's `client_secret`
	- `EP_DL_SITE`: Absolute URL of the website where episode downloads can be found<br>(not going to make that one public for obvious reasons)
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

The only parts of the server config that are crucial and must be kept intact are the rewrites which make readable & pretty URLs without the `.php` extension possible.