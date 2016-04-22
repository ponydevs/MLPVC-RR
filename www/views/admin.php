<div id="content">
	<h1><?=$title?></h1>
	<p>Various tools related to managing the site</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-document-text' href="/admin/logs">Global Logs</a>
	</p>

	<section class="the-final-countdown">
<?php $coupts = '2016-05-04T14:46:47Z'; ?>
		<h2><span class="typcn typcn-time"></span><?=Time::Tag($coupts, EXTENDED, NO_DYNTIME)?></h2>
		<div class="countdown" data-time="<?=$coupts?>"><span>&hellip;</span> weeks <span>&hellip;</span> days<br> <span>&hellip;</span></div>
	</section>
<?php
	$FavmeIDs = array();
	foreach ($Database->where('author','Hawk9mm')->get('deviation_cache',null,'id') as $row)
		$FavmeIDs[] = $row['id'];
	if (count($FavmeIDs)){
		$Appearances = $CGDb->rawQuery('SELECT id,label FROM appearances WHERE cm_favme IN (\''.implode('\',\'',$FavmeIDs).'\')');
		if (count($Appearances)){ ?>
	<section class="hawk9mm">
		<h2><span class="typcn typcn-warning"></span>Appearances with Hawk9mm CMs that need replacing</h2>
		<ul><?php
		foreach ($Appearances as $p)
			echo "<li><a href='/colorguide/appearance/{$p['id']}'>".htmlspecialchars($p['label']).'</a></li>';
		?></ul>
	</section>
<?php   }
	} ?>

	<section class="useful-links">
		<h2><span class="typcn typcn-link"></span>Manage useful links</h2>
		<p><button class="green typcn typcn-plus" id="add-link">Add link</button><button class='darkblue typcn typcn-arrow-unsorted' id="reorder-links">Re-order links</button></p>
		<div><?=CoreUtils::GetSidebarUsefulLinksList()?></div>
	</section>

	<section class="recent-posts">
		<h2><span class="typcn typcn-bell"></span>Most recent posts</h2>
		<div><?=Posts::GetMostRecentList()?></div>
	</section>
</div>

<?php
	CoreUtils::ExportVars(array(
		'ROLES_ASSOC' => Permission::$ROLES_ASSOC,
		'PRINTABLE_ASCII_REGEX' => PRINTABLE_ASCII_REGEX
	));
