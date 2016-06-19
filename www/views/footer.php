
	</div>

	<footer><?=CoreUtils::GetFooter()?></footer>

<script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/jquery-2.1.4.min.js">\x3C/script>');</script>
<?  CoreUtils::ExportVars(array(
	'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN,
	'DocReady' => array(),
	'signedIn' => $signedIn,
	));
	if (isset($customJS)) foreach ($customJS as $js){
		echo "<script src='$js'></script>\n";
	} ?>
</body>
</html>
