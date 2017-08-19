<div id="content" class="section-container">
	<img src="/img/logo.svg" alt="MLP Vector Club Website Logo">
	<h1><a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a> Website</h1>
	<p>Handling requests, reservations & the Color Guide since 2015</p>
<?php
use App\About;
use App\CoreUtils;

$about = \App\File::get(INCPATH.'views/about/readme_snippet.html');
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
			<div class="stats-alltimeposts">
				<h3>Total posts since launch</h3>
				<div class="stats-wrapper">
					<canvas></canvas>
				</div>
				<div class="legend"></div>
			</div>
		</div>
	</section>
	<section id="supported-providers">
		<h2>What image hosting providers do you support?</h2>
		<div>
			<p>As you can probably tell we do not host a large majority of images you can see on episode pages, we just <del>steal the bandwidth</del> use the help of already established sites. Here's a full list of all providers we can recognize and that you can use to submit images:</p>
			<ul>
				<li><a href="http://sta.sh/">Sta.sh</a>*</li>
				<li><a href="http://deviantart.com/">DeviantArt</a>*</li>
				<li><a href="http://imgur.com/">Imgur</a></li>
				<li><a href="http://derpibooru.org/">Derpibooru</a></li>
				<li><a href="http://app.prntscr.com/">LightShot</a></li>
				<li><del>Puush</del> (no longer supported)</li>
			</ul>
			<p>* Using direct links from these providers is not supported due to the ambugious URL schema shared by both Sta.sh and DeviantArt (both links end with an ID in the same format). Example: the link <q>http://fav.me/<wbr>dzc2af9</q> could have a download link that points to <q>http://orig11.deviantart.net/<wbr>27f5/<wbr>f/<wbr>2017/<wbr>107/<wbr>3/<wbr>b/<wbr>image_by_user-dzc2af9.png</q>. If you use the second link, the site won't accept it. <a id="butwhy" href="#show-boring-details">But why?</a><span class="hidden" id="thisiswhy"><br>To understand why I, as a developer cannot provide support for this, let's look at an example with a normal link and its corresponding direct link from Sta.sh: <q>https://sta.sh/<wbr>0z5gg4as67m</q> &rarr; <q>http://orig00.deviantart.net/<wbr>9935/<wbr>f/<wbr>2016/<wbr>089/<wbr>8/<wbr>e/<wbr>another_image_by_user-dxc2f9v.jpg</q> Notice anything different? It's the lack of distinct difference between the two, there's nothing to tell which site the image came from. Hopefully this gives you an idea of why things are the way they are.</span></p>
		</div>
	</section>
	<section>
		<h2>What are those characters and numbers in the footer?</h2>
		<div>
			<p>This website’s complete codebase is available for anyone to see on GitHub at <a href="<?=GITHUB_URL?>"><?=GITHUB_URL?></a>. What you're seeing is the version number, which consists of the first few characters of the latest commit’s ID. In this case, a commit is simply an update to the site. Whenever a new update is applied, the version number changes automatically.</p>
		</div>
	</section>
</div>
