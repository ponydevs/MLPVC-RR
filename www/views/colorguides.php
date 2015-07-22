<div id=content>
	<h1><?=$title?></h1>
	<p>A searchable<sup title="To Be Implemented">TBI</sup> list of <del style=opacity:.3;color:red>every</del> <ins style=color:green>some</ins> characters color keyed so far</p>
	<div class="notice warn tagediting">
		<label>Tag editing unavailable</label>
		<p>Because you seem to be using a mobile device, editing tags is not available, because it requires you to right-click. If you want to edit tags, please do so from a computer.</p>
	</div>
	<ul id=list><?=get_ponies_html($Ponies)?></ul>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>