<?php
use App\CoreUtils;
/** @var $heading string */
/** @var $Appearance \App\Models\Appearance */
/** @var $Colors array */
/** @var $Map array */
/** @var $AllColors array */
/** @var $ColorGroups array */ ?>
<div id="content" class="section-container">
	<h1>Sprite Color Checker</h1>
	<div class="button-block align-center">
		<a href="/cg/v/<?=$Appearance->id?>" class="btn link typcn typcn-arrow-back"> Back to appearance page</a>
		<button class="darkblue typcn typcn-adjust-contrast" id="server-side-check">Run server-side check</button>
	</div>

	<section class="checker">
		<div id="svg-cont"></div>
		<div id="input-cont"></div>
	</section>
	<section class="color-list">
		<ul id="colors" class="colors"><?php
	foreach ($ColorGroups as $cg)
		echo $cg->getHTML($AllColors, WRAP, NO_COLON, OUTPUT_COLOR_NAMES, FORCE_EXTRA_INFO);
		?></ul>
	</section>
</div>
<?php
	echo CoreUtils::exportVars([
		'AppearanceID' => $Appearance->id,
		'AppearanceColors' => $Colors,
		'SpriteColorList' => $Map['colors'],
	]);
