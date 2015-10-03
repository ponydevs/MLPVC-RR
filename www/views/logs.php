<div id="content">
	<h1>Global logs</h1>
<?php if (!empty($MSG)){ ?>
	<p><?=$MSG?></p>
<?php } else { ?>
	<p>Displaying <?=$ItemsPerPage?> items/page</p>
	<?=$Pagination?>
	<table id="logs">
		<thead>
			<tr>
				<th class="entryid">#</th>
				<th class="timestamp">Timestamp</th>
				<th class="ip">Initiator</th>
				<th class="reftype">Event</th>
			</tr>
		</thead>
		<tbody><?=log_tbody_render($LogItems)?></tbody>
	</table>
<?php
		echo $Pagination;
	} ?>
</div>
