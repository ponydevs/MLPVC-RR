<div id="content">
	<h1>Sprite Color Checker</h1>
	<p><a href="/cg/v/<?=$Appearance['id']?>"><span class="typcn typcn-arrow-back"></span> Back to appearrance page</a></p>

	<section>
		<div id="svg-cont"><?=$SVG?></div>
		<div id="input-cont"><h3>JavaScript is required to use the viewer</h3></div>
	</section>
		<section class="color-list">
			<h2 class="admin">Color groups</h2>
			<ul id="colors" class="colors"><?php
	foreach ($ColorGroups as $cg)
		echo \CG\ColorGroups::GetHTML($cg, $AllColors, WRAP, NO_COLON, OUTPUT_COLOR_NAMES, FORCE_EXTRA_INFO);
			?></ul>
		</section>
</div>
<?php
	CoreUtils::ExportVars(array(
		'AppearanceColors' => $Colors,
		'SpriteColorList' => $Map['colors'],
	));
