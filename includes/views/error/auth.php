<?php
use App\DeviantArt;
use App\CoreUtils;

/** @var string $title */
/** @var string $err */
/** @var string|null $errdesc */?>

<div id="content">
	<h1><?=$title?></h1>

	<?=CoreUtils::notice('fail', 'There was a(n) <strong>'.CoreUtils::escapeHTML($err).'</strong> error while trying to authenticate with DeviantArt'.(!empty(DeviantArt::OAUTH_RESPONSE[$err])?'; '.DeviantArt::OAUTH_RESPONSE[$err]:'.').(!empty($errdesc)?"\n\nAdditional details: ".CoreUtils::escapeHTML($errdesc):''),true) ?>
</div>
