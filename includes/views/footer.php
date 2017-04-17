<?php
use App\CoreUtils; ?>
	</div>

	<footer><?=CoreUtils::getFooter(isset($view) && $view === 'fatalerr')?></footer>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-cCueBR6CsyA4/9szpPfrX3s49M9vUU5BgtiJj06wt/s=" crossorigin="anonymous"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/min/jquery-3.2.1.js">\x3C/script>');</script>
<?php
	echo CoreUtils::exportVars(array(
		'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN,
		'DocReady' => array(),
		'signedIn' => $signedIn,
	));
	if (isset($customJS)) foreach ($customJS as $js){
		echo "<script src='$js'></script>\n";
	} ?>
</body>
</html>
