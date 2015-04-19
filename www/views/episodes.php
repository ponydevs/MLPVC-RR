<div id=content>
	<h1><span class=eps-gt-0 style="display: <?=!empty($Episodes)?'block':'none'?>">Episode list</span><span class=eps-0 style="display: <?=empty($Episodes)?'block':'none'?>">No episodes found</span></h1>
	<p class=eps-0 style="display: <?=empty($Episodes)?'block':'none'?>">There are no episodes stored in the database</p>
<?php   if (PERM('episodes.manage')) { ?>
	<div class="align-center">
		<button id="add-episode" class="typcn typcn-plus">Add an episode</button>
	</div>
<?php   }
		if (!empty($Episodes) || (empty($Episodes) && PERM('episodes.manage'))){ ?>
	<table id=episodes>
		<thead>
			<tr>
				<th>Season</th>
				<th>Episode</th>
				<th>Title</th>
			</tr>
		</thead>
		<tbody><?=get_eptable_tbody($Episodes)?></tbody>
	</table>
<?php   } ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>