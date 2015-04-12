
	</div>

	<footer>
		<p><strong>MLPVC-RR v0.1</strong> is open source. You can <a href="<?=GITHUB_URL?>">view it on GitHub</a> or <a href="<?=GITHUB_URL?>/issues">report an issue</a> with the site.</p>
	</footer>

<?php 	if (isset($customJS)) foreach ($customJS as $js){ ?>
<script src="<?=djpth('js>'.(!preg_match('/\.js\.php$/',strtok($js,'?')) ? "$js.js" : $js))?>"></script>
<?php 	} ?>
<script>
<?php if (!isset($_SERVER['HTTP_DNT'])){ ?>
  /* We respect your privacy. Enable "Do Not Track" in your browser, and this tracking code will disappear. */
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-35773360-5', 'auto');
  ga('require', 'displayfeatures');
  ga('send', 'pageview');

<?php } ?>
$(function(){
	var $nav = $('header nav');
	if ($nav.length > 0){
		var wlpn = window.location.pathname,
			$a = $nav.children('a').filter(function(){ return this.host === window.location.host && this.pathname === wlpn });
		if ($a.length > 0)
			$a.addClass('active').removeAttr('href').on('mousedown dragstart click',function(e){e.preventDefault()});
	}
});
</script>
</body>
</html>