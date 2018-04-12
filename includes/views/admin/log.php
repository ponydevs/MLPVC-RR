<?php
use App\Logs;
/** @var $heading string */
/** @var $Pagination App\Pagination */
/** @var $LogItems \App\Models\Logs\Log[] */ ?>
<div id="content">
	<h1><?=$heading?></h1>
<?php
	if (!empty($MSG)){
		echo "<p>$MSG</p>";
	}
	else { ?>
	<p>Displaying <?=$Pagination->getItemsPerPage()?> items/page</p>
	<div class='align-center button-block'>
		<a class='btn link typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</div>
	<form id="filter-form">
		<strong>Show</strong>
		<select name="type" class="entrytype">
			<option value=''<?=!isset($type)?' selected':''?>>all</option>
			<optgroup label="Specific entry type"><?php
		$descs = Logs::$LOG_DESCRIPTION;
		asort($descs);
		foreach ($descs as $value => $label)
			echo "<option value='$value' ".(($type??null)===$value?'selected':'').">$label</option>";
		?></optgroup>
		</select>
		<strong>entries from</strong>
		<input type="text" name="by" class="username" size="22" placeholder="any user / IP"<?=isset($by)||isset($ip)?" value='".($by??$ip)."'":''?> pattern="^(<?=USERNAME_PATTERN?>|Web server|[\da-fA-F.:]+)$" maxlength="20" list="from_values">
		<button type="submit" class="blue typcn typcn-zoom" title="Apply filter"></button>
		<button type="reset" class="orange typcn typcn-times" title="Clear filters"<?=isset($by)||isset($ip)||isset($type)?'':' disabled'?>></button>
		<datalist id="from_values">
			<option>Web server</option>
			<option>you</option>
			<option>your IP</option>
		</datalist>
	</form>
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
		<tbody><?=Logs::getTbody($LogItems)?></tbody>
	</table>
<?php
		echo $Pagination;
	} ?>
</div>
