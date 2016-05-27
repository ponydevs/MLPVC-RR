<div id="content">
	<h1><?=$title?></h1>
	<p>Various tools related to managing the site</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-document-text' href="/admin/logs">Global Logs</a>
	</p>

	<section class="useful-links">
		<h2><span class="typcn typcn-link"></span>Manage useful links</h2>
		<p><button class="green typcn typcn-plus" id="add-link">Add link</button><button class='darkblue typcn typcn-arrow-unsorted' id="reorder-links">Re-order links</button></p>
		<div><?=CoreUtils::GetSidebarUsefulLinksList()?></div>
	</section>

	<section class="overdue-submissions">
		<h2><span class="typcn typcn-time"></span>Overdue submissions</h2>
		<div><?=CoreUtils::GetOverdueSubmissionList()?></div>
	</section>

	<section class="recent-posts">
		<h2><span class="typcn typcn-bell"></span>Most recent posts</h2>
		<div><?=Posts::GetMostRecentList()?></div>
	</section>
</div>

<?php
	CoreUtils::ExportVars(array(
		'ROLES_ASSOC' => Permission::$ROLES_ASSOC,
		'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN
	));
