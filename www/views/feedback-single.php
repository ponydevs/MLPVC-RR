<div id="content">
	<h1>Feedback #<?=$ChainID?></h1>
	<p><?=$Chain['subject']?></p>
	<p class='align-center'>Sent by <?=profile_link($Author)?> <?=timetag($Chain['created'])?> - Status: <strong class='color-<?=$Chain['open']?'green':'red'?>'><?=$Chain['open']?'Open':'Closed'?></strong><br><?
	if (PERM('developer'))
		echo "<a href='/feedback' class='btn blue typcn typcn-arrow-back'>Back to all feedback</a> ";
	echo "<button disabled id='fb-".($Chain['open']?'close':'reopen')."' class='typcn typcn-lock".($Chain['open']?'-closed red':'green')."'>".($Chain['open']?'Close':'Re-open').'</button>'; ?></p>
	<?=render_feedback_chain_html($ChainID)?>
</div>
