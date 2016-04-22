<div id="content">
	<h1 data-none="No episodes found" data-list="Episode list"><?=empty($Episodes)?'No episodes found':'Episode list'?></h1>
	<p class="eps-0" style="display: <?=empty($Episodes)?'block':'none'?>">There are no episodes stored in the database</p>
<?  if (Permission::Sufficient('inspector')) { ?>
	<div class="align-center">
		<button id="add-episode" class="typcn typcn-plus">Add an episode</button>
	</div>
<?      CoreUtils::ExportVars(array('EP_TITLE_REGEX' => $EP_TITLE_REGEX));
	}
	echo $Pagination;
	if (!empty($Episodes) || (empty($Episodes) && Permission::Sufficient('inspector'))){ ?>
	<table id="episodes">
		<thead>
			<tr>
				<th>S<span class="mobile-hide">eason</span></th>
				<th>E<span class="mobile-hide">pisode</span></th>
				<th>Title & Air Date</th>
			</tr>
		</thead>
		<tbody><?=Episode::GetTableTbody($Episodes)?></tbody>
	</table>
<?  }
	echo $Pagination; ?>
	<h1>Movies</h1>
	<table id="movies">
		<thead>
			<tr>
				<th>Title &amp; Air Date</th>
			</tr>
		</thead>
		<tbody><?=Episode::GetTableTbody($Database->where('season', 0)->get('episodes'))?></tbody>
	</table>
</div>
