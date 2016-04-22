<div id="content">
	<h1>Global logs</h1>
<?php if (!empty($MSG)){ ?>
	<p><?=$MSG?></p>
<?php } else { ?>
	<p>Displaying <?=$Pagination->itemsPerPage?> items/page</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</p>
	<?=$Pagination->HTML?>
	<table id="logs">
		<thead>
			<tr>
				<th class="entryid">#</th>
				<th class="timestamp">Timestamp</th>
				<th class="ip">Initiator</th>
				<th class="reftype">Event</th>
			</tr>
		</thead>
		<tbody><?=Log::GetTbody($LogItems)?></tbody>
	</table>
<?php
		echo $Pagination->HTML;
	} ?>
</div>
