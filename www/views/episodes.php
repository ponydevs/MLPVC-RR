<div id=content>
<?php if (!empty($Episodes)){
		if (PERM('episodes.manage')) { ?>
	<h1>Previous episodes</h1>
	<div class="align-center">
		<a data-href="<?= djpth('episodes>add') ?>" class="btn disabled typcn typcn-plus">Add an episode</a>
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
		<tbody><?php
		foreach ($Episodes as $ep) {
			$Title = format_episode_title($ep, AS_ARRAY);
			$href = djpth("episode>{$Title['id']}");
			echo <<<HTML
			<tr>
				<td class=season>{$Title['season']}</td>
				<td class=episode><span>{$Title['episode']}</span></td>
				<td class=title><a href="$href">{$Title['title']}</a></td>
			</tr>
HTML;
		}
		?></tbody>
	</table>
<?php } else { ?>
	<h1>No episode found</h1>
	<p>There are no episodes stored in the database</p>
<?php } ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>