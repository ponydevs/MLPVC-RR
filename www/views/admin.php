<div id="content">
	<h1><?=$title?></h1>
	<p>Various tools related to managing the site</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-document-text' href="/admin/logs">Global Logs</a>
	</p>

	<section class="the-final-countdown">
		<h2><span class="typcn typcn-time"></span>It's the final countdown</h2>
		<div class="countdown" data-time="2016-05-04T16:46:47+02:00"></div>
	</section>

	<section class="useful-links">
		<h2><span class="typcn typcn-link"></span>Manage useful links</h2>
		<p><button class="green typcn typcn-plus" id="add-link">Add link</button><button class='darkblue typcn typcn-arrow-unsorted' id="reorder-links">Re-order links</button></p>
		<div><?=render_useful_links_list()?></div>
	</section>

	<section class="recent-posts">
		<h2><span class="typcn typcn-bell"></span>Most recent posts</h2>
		<div><?=render_most_recent_posts()?></div>
	</section>
</div>

<?php
	ExportVars(array(
		'ROLES_ASSOC' => $ROLES_ASSOC,
		'PRINTABLE_ASCII_REGEX' => PRINTABLE_ASCII_REGEX
	));
