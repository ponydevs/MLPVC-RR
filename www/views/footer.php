
	</div>

	<footer><?=get_footer()?></footer>

<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/jquery-2.1.4.min.js">\x3C/script>');
var REWRITE_REGEX = <?=str_replace('~','/',str_replace('/','\/',REWRITE_REGEX))?>i,
	SITE_TITLE = '<?=SITE_TITLE?>',
	PRINTABLE_ASCII_REGEX = '<?=PRINTABLE_ASCII_REGEX?>',
	DocReady = [];</script><?
	if (!isset($_SERVER['HTTP_DNT']) && !empty(GA_TRACKING_CODE) && !PERM('inspector')){ ?>

<!-- We respect your privacy. Enable "Do Not Track" in your browser, and this tracking code will disappear. -->
<script async src='https://www.google-analytics.com/analytics.js'></script>
<script>
// http://nickcraver.com/blog/2015/03/24/optimization-considerations/
var ga=ga||function(){(ga.q=ga.q||[]).push(arguments)};ga.l=+new Date;
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
