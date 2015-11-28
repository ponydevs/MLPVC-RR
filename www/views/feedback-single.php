<div id="content">
	<h1><?=$Chain['subject']?></h1>
	<p>Feedback #<?=$ChainID?></p>
	<p class='align-center'>Sent by <?=profile_link($Author)?> <?=timetag($Chain['created'])?> - Status: <strong id="fb-status" class='color-<?=$Chain['open']?'green':'red'?>'><?=$Chain['open']?'Open':'Closed'?></strong><br><?
	if (PERM('developer'))
		echo "<a href='/feedback' class='btn blue typcn typcn-arrow-back'>Back to all feedback</a> ";
	echo "<button id='fb-open-toggle' class='typcn typcn-lock-".($Chain['open']?'closed red':'open green')."'>".($Chain['open']?'Close':'Re-open').'</button>'; ?></p>
	<?=render_feedback_chain_html($ChainID, $Author)?>
	<section id="respond"<?=$Chain['open']?'':' style="display:none"'?>>
		<h2>Respond to this feedback</h2>
		<textarea name="message" required maxlength=500></textarea>
		<button class='blue typcn typcn-arrow-forward'>Send response</button>
	</section>
</div>
