<div id="content">
	<?=(file_exists(APPATH.($relpath="img/cg/{$Appearance['id']}.png")))?"<img src='/$relpath' alt='{$Appearance['id']}'>":''?>
	<h1><?=$heading?></h1>
	<p>from the MLP-VectorClub <a href="/colorguide"><?=$Color?> Guide</a></p>
	<?=Notice('info',"You can click any {$color}ed square on this page to copy its HEX color code to your clipboard. To toggle whether the # symbol will be copied, use the button below. Your choice will be remembered on this browser.

	<button class='typcn typcn-refresh' id='toggle-copy-hash'>Checking...</button>")?>
	<ul id="colors">
<?  foreach (get_cgs($Appearance['id']) as $cg)
		echo get_cg_html($cg, WRAP, NO_COLON); ?>
	</ul>
</div>

<script>var Color = '<?=$Color?>', color = '<?=$color?>';</script>
