<?php
/** @var string $heading */
/** @var \App\Models\Event $Event */
/** @var \App\Models\EventEntry[] $UserEntries */?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Ends <?=\App\Time::tag(strtotime($Event->ends_at))?></p>

	<div class="align-center">
<?php   if (empty($UserEntries)){ ?>
		<button class="green typcn typcn-user-add" disabled id="enter-event">Enter</button>
<?php   }
		else { ?>
		<button class="red typcn typcn-user-delete" disabled id="enter-event">Withdraw <?=\App\CoreUtils::makePlural('entry',count($UserEntries))?></button>
<?php   } ?>
	</div>

	<section>
		<h2><span class='typcn typcn-info-large'></span>Description</h2>
		<div id="description"><?=nl2br($Event->description)?></div>
	</section>
</div>
