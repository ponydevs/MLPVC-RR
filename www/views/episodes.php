<div id=content>
<?php if (!empty($Episodes)){
		if (PERM('episodes.manage')) { ?>
	<h1>Previous episodes</h1>
	<div class="align-center">
		<p><a data-href="<?= djpth('episodes>add') ?>" class="btn disabled">Add an episode</a></p>
	</div>
<?php   } ?>
	<table id=episodes>
		<thead>
			<tr>
				<th class="left top">Season</th>
				<th class="top right">Episode</th>
				<th class="top right">Title</th>
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