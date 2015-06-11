<div id=content>
	<h1>Global logs</h1>
<?php if (!empty($MSG)){ ?>
	<p><?=$MSG?></p>
<?php } else { ?>
	<p>Displaying <?=$ItemsPerPage?> items/page</p>
<?php	$Pagination = array();
		for ($i = 1; $i <= $MaxPages; $i++){
			$li = $i;
			if ($li !== $Page)
				$li = "<a href='/logs/$li'>$li</a>";
			else $li = "<strong>$li</strong>";
			$Pagination[] = "<li>$li</li>";
		}
		$Pagination = '<ul class=pagination>'.implode('',$Pagination).'</ul>';
		echo $Pagination; ?>
	<table id="logs">
		<thead>
			<tr>
				<td class="entryid">#</td>
				<td class="timestamp">Timestamp</td>
				<td class="ip">Initiator</td>
				<td class="reftype">Event</td>
			</tr>
		</thead>
		<tbody><?php log_tbody_render($LogItems); ?></tbody>
	</table>
<?php
		echo $Pagination;
	} ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>