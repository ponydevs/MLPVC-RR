<div id=content>
	<h1 data-none="No episodes found" data-list="Episode list"><?=empty($Episodes)?'No episodes found':'Episode list'?></h1>
	<p class=eps-0 style="display: <?=empty($Episodes)?'block':'none'?>">There are no episodes stored in the database</p>
<?php   if (PERM('inspector')) { ?>
	<div class="align-center">
		<button id="add-episode" class="typcn typcn-plus">Add an episode</button>
	</div>
	<script>var EP_TITLE_REGEX = <?=rtrim(EP_TITLE_REGEX,'u')?>;</script>
<?php   }
		if (!empty($Episodes) || (empty($Episodes) && PERM('inspector'))){ ?>
	<table id=episodes>
		<thead>
			<tr>
				<th>S<span class=mobile-hide>eason</span></th>
				<th>E<span class=mobile-hide>pisode</span></th>
				<th>Title & Air Date</th>
			</tr>
		</thead>
		<tbody><?=get_eptable_tbody($Episodes)?></tbody>
	</table>
<?php   } ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>
