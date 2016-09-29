<div id="content">
	<h1><?=empty($Episodes)?'No episodes found':'Episode list'?></h1>
<?  if (empty($Episodes)){ ?>
	<p>There are no episodes stored in the database</p>
<?  }
	if (Permission::Sufficient('staff')) { ?>
	<div class="actions">
		<button id="add-episode" class="green typcn typcn-plus">Add Episode</button>
	</div>
<?      CoreUtils::ExportVars(array('EP_TITLE_REGEX' => $EP_TITLE_REGEX));
	}
	echo $Pagination;
	if (!empty($Episodes) || (empty($Episodes) && Permission::Sufficient('staff'))){ ?>
	<table id="episodes">
		<thead>
			<tr>
				<th>S<span class="mobile-hide">eason</span></th>
				<th>E<span class="mobile-hide">pisode</span></th>
				<th>Title & Air Date</th>
			</tr>
		</thead>
		<tbody><?=Episodes::GetTableTbody($Episodes)?></tbody>
	</table>
<?  }
	echo $Pagination;
	$Movies = $Database->where('season', 0)->orderBy('episode','DESC')->get('episodes'); ?>
	<h1>Movies</h1>
<?  if (empty($Episodes)){ ?>
	<p>There are no movies stored in the database</p>
<?  }
	if (Permission::Sufficient('staff')) { ?>
	<div class="actions">
		<button id="add-movie" class="green typcn typcn-plus">Add Movie</button>
	</div>
<?  }
	if (!empty($Episodes) || (empty($Episodes) && Permission::Sufficient('staff'))){ ?>
	<table id="movies">
		<thead>
			<tr>
				<th><span class="mobile-hide">Overall </span>#</th>
				<th>Title &amp; Air Date</th>
			</tr>
		</thead>
		<tbody><?=Episodes::GetTableTbody($Movies, true)?></tbody>
	</table>
<?  } ?>
</div>
