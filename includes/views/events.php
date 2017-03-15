<?php
/** @var \App\Models\Event[] $Events */
/** @var \App\Pagination $Pagination */ ?>
<div id="content">
    <h1>Events</h1>
	<p>Organized by the club staff</p>

	<div class="align-center">
		<button class="green typcn typcn-plus" id="add-event">New event</button>
	</div>

	<?=$Pagination.\App\Events::getListHTML($Events).$Pagination?>
</div>
<?php
	echo \App\CoreUtils::exportVars([
		'EVENT_TYPES' => \App\Models\Event::EVENT_TYPES,
	]);
