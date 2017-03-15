<?php
/** @var string $heading */
/** @var \App\Models\Event $Event */
/** @var \App\Models\EventEntry[] $UserEntries */

$startts = strtotime($Event->starts_at);
$endts = strtotime($Event->ends_at);
?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>For <?=$Event->getEntryRoleName()?> &bull; <?=$startts > time() ? 'Starts '.\App\Time::tag($startts) : ($endts > time() ? 'Ends' : 'Ended').' '.\App\Time::tag($endts)?></p>

<?php   $canEnter = $signedIn && $Event->checkCanEnter($currentUser);
		if ($signedIn){ ?>
	<div class="align-center" id="event-<?=$Event->id?>">
		<button class="green typcn typcn-user-add" <?=$canEnter?'':'disabled'?> id="enter-event">Enter</button>
<?php       if (\App\Permission::sufficient('staff')){ ?>
		<button class="blue typcn typcn-pencil edit-event">Edit</button>
		<button class="red typcn typcn-trash delete-event">Delete</button>
<?php       } ?>
	</div>
<?php   } ?>

	<section>
		<h2><span class='typcn typcn-info-large'></span>Description</h2>
		<div id="description"><?=$Event->desc_rend?>
			<p>Entries will be accepted until <?=\App\Time::tag(strtotime($Event->ends_at), \App\Time::TAG_EXTENDED, \App\Time::TAG_STATIC_DYNTIME)?>. Entrants can submit <?=isset($Event->max_entries) ? 'a maximum of '.\App\CoreUtils::makePlural('entry', $Event->max_entries, PREPEND_NUMBER):'an unlimited number of entries'?> each.</p>
<?php   if (!$canEnter) { ?>
			<p class="color-red"><?=$signedIn?'You cannot participate in this event.':'You must be signed in to participate in events.'?></p>
<?php   } ?>
		</div>
	</section>

	<section>
		<h2><span class='typcn typcn-group'></span>Entries</h2>
		<?=$Event->getEntriesHTML()?>
	</section>
</div>
<?php
	echo \App\CoreUtils::exportVars([
		'EVENT_TYPES' => \App\Models\Event::EVENT_TYPES,
		'EventPage' => true,
	]);
