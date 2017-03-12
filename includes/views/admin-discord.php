<?php /** @var string $heading */
use App\Models\DiscordMember; ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p>Manage Discord server membership of users</p>
	<div class='align-center links'>
		<button class="blue typcn typcn-refresh" id="rerequest-members">Re-request user list</button>
		<a class='btn darkblue typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
	</div>

	<div class="twoside-manager">
		<div class="left-side">
			<div id="member-search"><input type="text" placeholder="Search members" autocomplete="off" spellcheck="false"></div>
			<ul class="discord-members loading"></ul>
		</div>
		<div class="right-side">
			<h2 class="right-header">Manage DeviantArt Link<span id="linkof-member"></span></h2>
			<div id="manage-area">
				<p>Click a member in the list to link them with a DeviantArt user.</p>
			</div>
		</div>
	</div>
</div>
<?php
	echo \App\CoreUtils::exportVars([
		'USERNAME_REGEX' => $USERNAME_REGEX,
	]);
