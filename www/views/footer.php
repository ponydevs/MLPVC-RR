
	</div>

	<footer><strong>MLPVC-RR v0.<?=LATEST_COMMIT_ID?></strong> (last updated <?=timetag(LATEST_COMMIT_TIME)?>) | <a href="<?=GITHUB_URL?>">View on GitHub</a> | <a href="<?=GITHUB_URL?>/issues">Issue tracker</a></footer>

<?php 	if (isset($customJS)) foreach ($customJS as $js){ ?>
<script src="/js/<?=$js?>.js?<?=LATEST_COMMIT_ID?>"></script>
<?php 	} ?>
<script>
<?php if (!isset($_SERVER['HTTP_DNT'])){ ?>
/* We respect your privacy. Enable "Do Not Track" in your browser, and this tracking code will disappear. */
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

ga('create', '<?=GA_TRACKING_CODE?>', 'auto');
ga('require', 'displayfeatures');
ga('send', 'pageview');

<?php } ?>
$(function(){
	var $w = $(window),
		$header = $('header'),
		tbh = $('#topbar').outerHeight();
	$w.on('scroll',function(){
		if ($w.scrollTop() > tbh)
			$header.addClass('fixed');
		else $header.removeClass('fixed');
	});
});
</script>
</body>
</html>