<div id=content>
	<h1>Global logs</h1>
<?php if (!empty($MSG)){ ?>
	<p><?=$MSG?></p>
<?php } else { ?>
	<p>Displaying <?=$ItemsPerPage?> items/page</p>
	<?=$Pagination = get_pagination_html('logs', $Page, $MaxPages)?>
	<table id="logs">
		<thead>
			<tr>
				<td class="entryid">#</td>
				<td class="timestamp">Timestamp</td>
				<td class="ip">Initiator</td>
				<td class="reftype">Event</td>
			</tr>
		</thead>
		<tbody><?=log_tbody_render($LogItems)?></tbody>
	</table>
<?php
		echo $Pagination;
	} ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>
