<div id="content">
	<img src="/img/logo.svg" alt="MLP Vector Club Requests &amp; Reservations Logo">
	<h1>MLP Vector Club Requests &amp; Reservations</h1>
	<p>An automated system for handling requests &amp; reservations, made for <a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a></p>
	<section>
		<h2>What's this site?</h2>
		<div>
			<p>This website is a new, automatic way to process and store the requests &amp; reservations users want to make. It's that simple.</p>
			<p>In the past, the management of comments under journals was done manually. Because of this, there had to be a person who checks those comments, evaluates them, then updates the journal accordingly. This took time, sometimes, longer time than it should have taken. The group's staff consists of busy people, and we can't expect them to consantly monitor new incoming comments. But, with the help of this website, new entries can be submitted and added to a list, just like the journals, automatically, without having to have someone do this monotonous task.</p>
		</div>
	</section>
	<section>
		<h2>Why does the version number look so&hellip; <em>random</em>?</h2>
		<div>
			<p>This website's complete codebase is <a href="<?=GITHUB_URL?>">available for anyone to see on GitHub</a>. The version number is the first few characters of the latest commit's ID. In this case, a commit is basically an update to the site. Whenever a new update is applied, the version number changes automatically.</p>
		</div>
	</section>
	<section>
		<h2>Attributions</h2>
		<div class="attributions">
			<?=file_get_contents(APPATH.'views/about.html')?>
			<p class="ramnode"><a href="https://clientarea.ramnode.com/aff.php?aff=2648"><img src="https://www.ramnode.com/images/banners/affbannerlightnewlogoblack.png" alt="high performance ssd vps""></a></p>
		</div>
	</section>
<? if (PERM('inspector')){ ?>
	<section>
		<h2><a href="/users"><span class="typcn typcn-arrow-back"></span>Linked users</a></h2>
		<p><em>This section has been moved to its own page.</em></p>
	</section>
<?  } ?>
</div>
