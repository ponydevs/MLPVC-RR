
	</main>

	<footer>
		<p><strong>MLPVC-RR v0.1</strong> is open source. You can <a href="<?=GITHUB_URL?>">view it on GitHub</a> or <a href="<?=GITHUB_URL?>/issues">report an issue</a> with the site.</p>
	</footer>

<?php 	if (isset($customJS)) foreach ($customJS as $js){ ?>
<script src="<?=djpth('js>'.(!preg_match('/\.js\.php$/',strtok($js,'?')) ? "$js.js" : $js))?>"></script>
<?php 	} ?>
<script>
$(function(){
	var $nav = $('header nav');
	if ($nav.length > 0){
		var wlpn = window.location.pathname,
			$a = $nav.children('a').filter(function(){ return this.pathname === wlpn });
		if ($a.length > 0)
			$a.addClass('active').removeAttr('href').on('mousedown dragstart click',function(e){e.preventDefault()});
	}
});
</script>
</body>
</html>