<div id="content">
	<img src="/img/logo.svg" alt="MLP Vector Club Website Logo">
	<h1><a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a> Website</h1>
	<p>Handling requests, reservations & the Color Guide since 2015</p>
<?php
use App\About;
use App\CoreUtils;

$about = file_get_contents(INCPATH.'views/about.html');
if (!empty($about)){
	echo str_replace(GITHUB_URL.'/blob/master/www','',$about);

	$osver = About::getServerOS();
	$phpver = About::getPHPVersion();
	$server = About::getServerSoftware();
	$pgver = About::getPostgresVersion();
	$esver = About::getElasticSearchVersion();
	$elastic = isset($esver) ? ", ElasticSearch $esver" : '';
	echo <<<HTML
<strong>Server Software:</strong> $osver, PHP $phpver, $server, PostgreSQL $pgver$elastic<br>
</section>
HTML;
}
else echo CoreUtils::notice('warn','This section went missing due to a bug, and will be restored ASAP. Until then the section’s contents are available at <a href="'.GITHUB_URL.'#attributions">'.GITHUB_URL.'#attributions</a>'); ?>
	<section>
		<h2>Statistics</h2>
		<p>Here you can see various graphs about the site. The information below is cached to reduce server load, you can see when each graph was last updated below their title.</p>
		<div id="stats">
			<div class="stats-posts">
				<h3>Posts in the last 2 months</h3>
				<div class="stats-wrapper">
					<canvas></canvas>
				</div>
				<div class="legend"></div>
			</div>
			<div class="stats-approvals">
				<h3>Post approvals in the last 2 months</h3>
				<div class="stats-wrapper">
					<canvas></canvas>
				</div>
				<div class="legend"></div>
			</div>
		</div>
	</section>
	<section>
		<h2 id="supported-providers">What image hosting providers do you support?</h2>
		<div>
			<p>As you can probably tell we do not host a large majority of images you can see on episode pages, we just <del>steal the bandwidth</del> use the help of already established sites. Here's a full list of all providers we can recognize and that you can use to submit images:</p>
			<ul>
				<li><a href="http://sta.sh/">Sta.sh</a></li>
				<li><a href="http://deviantart.com/">DeviantArt</a></li>
				<li><a href="http://imgur.com/">Imgur</a></li>
				<li><a href="http://derpibooru.org/">Derpibooru</a></li>
				<li><a href="http://puush.me/">Puush</a></li>
				<li><a href="http://app.prntscr.com/">LightShot</a></li>
			</ul>
		</div>
	</section>
	<section>
		<h2>What are those characters and numbers in the footer?</h2>
		<div>
			<p>This website’s complete codebase is available for anyone to see on GitHub at <a href="<?=GITHUB_URL?>"><?=GITHUB_URL?></a>. What you're seeing is the version number, which consists of the first few characters of the latest commit’s ID. In this case, a commit is simply an update to the site. Whenever a new update is applied, the version number changes automatically.</p>
		</div>
	</section>
</div>
