<div id="content">
	<h1><?=$title?></h1>
	<p>Various tools related to managing the site</p>
	<p class='align-center links'>
		<a class='btn darkblue typcn typcn-document-text' href="/admin/logs">Global Logs</a>
	</p>

	<section class="useful-links">
		<h2>Manage useful links</h2>
		<div><?=render_useful_links_list()?></div>
	</section>
</div>

<?php
	ExportVars(array(
		'ROLES_ASSOC' => $ROLES_ASSOC,
	));
