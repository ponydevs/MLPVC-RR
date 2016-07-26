<div id="content">
	<h1>Sprite Color Editor</h1>
	<p><a href="/cg/v/<?=$Appearance['id']?>"><span class="typcn typcn-arrow-back"></span> Back to appearrance page</a></p>

	<section>
		<div id="svg-cont"><?=$SVG?></div>
		<form id="form-cont"><h3>JavaScript is required to use the editor</h3></form>
	</section>
</div>
<?php
	CoreUtils::ExportVars(array(
		'AppearanceColors' => $Colors,
		'HEX_COLOR_REGEX' =>  $HEX_COLOR_REGEX,
		'SpriteColorMap' => $ColorMap,
	));
