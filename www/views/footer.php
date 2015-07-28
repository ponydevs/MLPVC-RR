
	</div>

	<footer><strong>MLPVC-RR build <a href="<?=GITHUB_URL?>"><?=LATEST_COMMIT_ID?></a></strong> (last updated <?=timetag(LATEST_COMMIT_TIME)?>) | <a href="<?=GITHUB_URL?>/issues">Report an issue</a></footer>

<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
<script>window.jQuery||document.write('\x3Cscript src="/js/jquery-2.1.4.min.js">\x3C/script>')</script>
<script>(function(w,d,u){w.RELPATH='<?=RELPATH?>';$.ajaxPrefilter(function(e){var t,n=d.cookie.split("; ");$.each(n,function(e,n){n=n.split("=");if(n[0]==="CSRF_TOKEN"){t=n[1];return false}});if(typeof t!=="undefined"){if(typeof e.data==="undefined")e.data="";if(typeof e.data==="string"){var r=e.data.length>0?e.data.split("&"):[];r.push("CSRF_TOKEN="+t);e.data=r.join("&")}else e.data.CSRF_TOKEN=t}});$.ajaxSetup({statusCode:{401:function(){$.Dialog.fail(u,"Cross-site Request Forgery attack detected. Please notify the site administartors.")},500:function(){$.Dialog.fail(false,'The request failed due to an internal server error.<br>If this persists, please <a href="<?=GITHUB_URL?>/issues" target="_blank">open an issue on GitHub</a>!')}}})})(window,document);</script>
<?php 	if (isset($customJS)) foreach ($customJS as $js){ ?>
<script src="/js/<?=$js?>.js?<?=filemtime(APPATH."/js/$js.js")?>"></script>
<?php 	} ?>
<script>
<?php if (!isset($_SERVER['HTTP_DNT']) && !empty(GA_TRACKING_CODE) && !PERM('inspector')){ ?>
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
	}).triggerHandler('scroll');
});
</script>
</body>
</html>
