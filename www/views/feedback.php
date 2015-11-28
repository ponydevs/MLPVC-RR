<div id="content">
	<h1>Feedback</h1>
	<p><?=PERM('developer')?'View user feedback':'View feedback you\'ve submitted'?></p>
	<div class="align-center">
		<button class="typcn typcn-plus send-feedback">Submit feedback</button>
	</div>
	<?=$Pagination?>
	<?=render_feedback_list_html($Feedback)?>
	<?=$Pagination?>
</div>
