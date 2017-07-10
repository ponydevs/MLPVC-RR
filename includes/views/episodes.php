<?php
use App\CoreUtils;
use App\Episodes;
use App\Permission;
/** @var $Episodes \App\Models\Episode[] */
/** @var $Movies \App\Models\Episode[] */
/** @var $Pagination \App\Pagination */ ?>
<div id="content">
	<div class="sidebyside">
		<h1><?=empty($Episodes)?'No episodes found':'Episode list'?></h1>
<?  if (empty($Episodes)){ ?>
		<p>There are no episodes stored in the database</p>
<?  }
	if (Permission::sufficient('staff')) { ?>
		<div class="actions">
			<button id="add-episode" class="green typcn typcn-plus">Add Episode</button>
		</div>
<?      echo CoreUtils::exportVars(['EP_TITLE_REGEX' => $EP_TITLE_REGEX]);
	}
	echo $Pagination;
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
	echo $Pagination; ?>
	</div>
	<div class="sidebyside">
<?  $Movies = $Database->where('season', 0)->orderBy('episode','DESC')->get('episodes'); ?>
		<h1>Movies</h1>
<?  if (empty($Movies))
		echo '<p>There are no movies stored in the database</p>';
	if (Permission::sufficient('staff')) { ?>
		<div class="actions">
			<button id="add-movie" class="green typcn typcn-plus">Add Movie</button>
		</div>
<?  }
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
<?  } ?>
	</div>
</div>
