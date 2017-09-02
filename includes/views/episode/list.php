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
<?  if (empty($Episodes))
		echo '<p>There are no episodes stored in the database</p>';
	if (Permission::sufficient('staff')) { ?>
		<div class="actions">
			<button id="add-episode" class="green typcn typcn-plus">Add Episode</button>
		</div>
<?      echo CoreUtils::exportVars(['EP_TITLE_REGEX' => $EP_TITLE_REGEX]);
	}
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
		<h1>Movies</h1>
<?  if (empty($Movies))
		echo '<p>There are no movies stored in the database</p>';
	if (Permission::sufficient('staff')) { ?>
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
