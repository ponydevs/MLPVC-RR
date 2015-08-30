
	</div>

	<footer>Running <strong><a href="<?=GITHUB_URL?>" title="Visit GitHub repository">MLPVC-RR</a>@<a href="<?=GITHUB_URL?>/commit/<?=LATEST_COMMIT_ID?>" title="See exactly what was changed and why"><?=LATEST_COMMIT_ID?></a></strong> <em>(<?=IS_LATEST_COMMIT?'latest':'outdated'?>)</em> created <?=timetag(LATEST_COMMIT_TIME)?> | <a href="<?=GITHUB_URL?>/issues">Report an issue</a></footer>

<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/jquery-2.1.4.min.js">\x3C/script>');
var REWRITE_REGEX = <?=str_replace('~','/',str_replace('/','\/',REWRITE_REGEX))?>i, DocReady = [];<?
	if (!isset($_SERVER['HTTP_DNT']) && !empty(GA_TRACKING_CODE) && !PERM('inspector')){ ?>

/* We respect your privacy. Enable "Do Not Track" in your browser, and this tracking code will disappear. */
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', '<?=GA_TRACKING_CODE?>', 'auto');
ga('require', 'displayfeatures');
ga('send', 'pageview');
<?  } ?></script>
<?  if (isset($customJS)) foreach ($customJS as $js){
		echo "<script src='$js'></script>\n";
	} ?>
</body>
</html>
