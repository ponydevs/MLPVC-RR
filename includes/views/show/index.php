<?php
use App\CoreUtils;
use App\Episodes;
use App\Permission;
/** @var $Episodes \App\Models\Episode[] */
/** @var $Movies \App\Models\Episode[] */
/** @var $EpisodesPagination \App\Pagination */
/** @var $MoviesPagination \App\Pagination */
/** @var $areMovies bool */
global $EP_TITLE_REGEX; ?>
<div id="content">
	<div class="sidebyside">
		<h1>Episodes</h1>
<?  if (Permission::sufficient('staff')) { ?>
		<div class="actions">
			<button id="add-episode" class="green typcn typcn-plus">Add Episode</button>
		</div>
<?  }
	echo $EpisodesPagination;
	if (!empty($Episodes) || (empty($Episodes) && Permission::sufficient('staff'))){ ?>
		<table id="episodes">
			<thead>
				<tr>
					<th>S<span class="mobile-hide">eason</span></th>
					<th>E<span class="mobile-hide">pisode</span></th>
					<th>Title & Air Date</th>
				</tr>
			</thead>
			<tbody><?=Episodes::getTableTbody($Episodes)?></tbody>
		</table>
<?  }
	echo $EpisodesPagination; ?>
	</div>
	<div class="sidebyside">
		<h1>Movies &amp; Shorts</h1>
<?  if (Permission::sufficient('staff')) { ?>
		<div class="actions">
			<button id="add-movie" class="green typcn typcn-plus">Add Movie</button>
		</div>
<?  }
	echo $MoviesPagination;
	if (!empty($Movies) || (empty($Movies) && Permission::sufficient('staff'))){ ?>
		<table id="movies">
			<thead>
				<tr>
					<th><span class="mobile-hide">Overall </span>#</th>
					<th>Title &amp; Air Date</th>
				</tr>
			</thead>
			<tbody><?=Episodes::getTableTbody($Movies, true)?></tbody>
		</table>
<?  }
	echo $MoviesPagination; ?>
	</div>
</div>
<?php
	if (Permission::sufficient('staff'))
		echo CoreUtils::exportVars(['EP_TITLE_REGEX' => $EP_TITLE_REGEX]);
