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

	<section class="mass-approve">
		<h2><span class="typcn typcn-tick"></span>Bulk approve posts</h2>
		<p>If you've approved a bunch of submissions and want to mark all of them as approved on the site, then go to your notifications, open the deviation stack for the new deviations in the club, press <kbd>Ctrl</kbd><kbd>A</kbd> followed by <kbd>Ctrl</kbd><kbd>V</kbd> then click into the box below (you should see a blinking cursor afterwards) and hit <kbd>Ctrl</kbd><kbd>V</kbd>. The script will look through the page, collecting any deviation links it finds, then sends the IDs over to the server to mark them as approved if they were used to finish posts on the site.</p>
		<div class="textarea" contenteditable="true"></div>
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
