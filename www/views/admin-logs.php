<div id="content">
	<h1><?=$heading?></h1>
<?php
	if (!empty($MSG)){
		echo "<p>$MSG</p>";
	}
	else { ?>
	<p>Displaying <?=$Pagination->itemsPerPage?> items/page</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</p>
	<form id="filter-form">
		<strong>Show entries</strong>
		<select name="type">
			<option value=''<?=!isset($type)?' selected':''?>>of any type</option>
			<optgroup label="Specific entry type"><?php
		foreach (Log::$LOG_DESCRIPTION as $value => $label)
			echo "<option value='$value'".($type===$value?' selected':'').">of type $label</option>";
		?></optgroup>
		</select>
		<strong>by</strong>
		<input type="text" name="by" placeholder="any user"<?=isset($by)?" value='$by'":''?> pattern="^(<?=USERNAME_PATTERN?>|Web server)$" maxlength="20">
		<button type="submit" class="blue typcn typcn-zoom" title="Apply filter"></button>
		<button type="reset" class="orange typcn typcn-times" title="Clear filters"<?=isset($by)||isset($type)?'':' disabled'?>></button>
	</form>
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
