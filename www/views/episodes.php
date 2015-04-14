<div id=content>
<?php if(!empty($Episodes)){ ?>
<?php } else { ?>
	<div class="notice fail">
		<p><strong>Sorry</strong>, this section doesn't work properly yet :(</p>
	</div>
	<h1>No episode found</h1>
	<p>There are no episodes stored in the database</p>

<?php   if (PERM('episodes.manage')){ ?>
	<div class="notice info">
		<label>Actions</label>
		<p><a data-href="<?=djpth('episodes>add')?>" class="btn disabled">Add an episode</a></p>
	</div>
<?php   }
	} ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>