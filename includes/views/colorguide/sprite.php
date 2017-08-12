<?php
use App\ColorGroups;
use App\CoreUtils;
/** @var $heading string */
/** @var $Appearance \App\Models\Appearance */
/** @var $Colors array */
/** @var $Map array */
/** @var $AllColors array */
/** @var $ColorGroups array */ ?>
<div id="content" class="section-container">
	<h1>Sprite Color Checker</h1>
	<p><a href="/cg/v/<?=$Appearance->id?>"><span class="typcn typcn-arrow-back"></span> Back to appearance page</a></p>

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
