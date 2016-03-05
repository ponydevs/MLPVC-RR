
	</div>

	<footer><?=get_footer()?></footer>

<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/jquery-2.1.4.min.js">\x3C/script>');
var REWRITE_REGEX = <?=$REWRITE_REGEX->jsExport()?>,
	SITE_TITLE = '<?=SITE_TITLE?>',
	PRINTABLE_ASCII_REGEX = '<?=PRINTABLE_ASCII_REGEX?>',
	DocReady = [];</script><?
	if (!isset($_SERVER['HTTP_DNT']) && !empty(GA_TRACKING_CODE) && !PERM('inspector')){ ?>

<!-- We respect your privacy. Enable "Do Not Track" in your browser, and this tracking code will disappear. -->
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
ga('create','<?=GA_TRACKING_CODE?>','auto');
ga('require', 'displayfeatures');
ga('send','pageview');
</script>
<?  }
	if (isset($customJS)) foreach ($customJS as $js){
		echo "<script src='$js'></script>\n";
	} ?>
</body>
</html>
