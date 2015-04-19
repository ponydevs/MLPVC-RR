<div id=content>
<?php if (!empty($Episodes)){ ?>
	<h1>Episode list</h1>
<?php	if (PERM('episodes.manage')) { ?>
	<div class="align-center">
		<button id="add-episode" class="typcn typcn-plus">Add an episode</button>
	</div>
<?php   } ?>
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
<?php } else { ?>
	<h1>No episode found</h1>
	<p>There are no episodes stored in the database</p>
<?php } ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>