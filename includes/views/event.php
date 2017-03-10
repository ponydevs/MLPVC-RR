<?php
/** @var string $heading */
/** @var \App\Models\Event $Event */?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Ends <?=\App\Time::tag(strtotime($Event->ends_at))?></p>

	<section>
		<h2><span class='typcn typcn-info-large'></span>Description</h2>
		<div id="description"><?=nl2br($Event->description)?></div>
	</section>
</div>
