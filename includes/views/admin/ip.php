<?php
/** @var $ip string */
/** @var $Users App\Models\User[] */
/** @var $KnownIPs App\Models\KnownIP[] */ ?>
<div id="content" class="section-container">
	<h1><?=$ip?></h1>
	<p>Details for this specific IP address</p>
	<p class='align-center links'>
		<a class='btn link typcn typcn-arrow-back' href="/admin">Back to Admin Area</a>
		<a class='btn link typcn typcn-document-text' href="/admin/logs?by=<?=$ip?>">View Log Entries</a>
	</p>

	<section>
		<h2>Occurences</h2>
<?  if (!empty($KnownIPs )){ ?>
		<ol><?
		foreach ($KnownIPs as $knownIP){
			$fs = \App\Time::tag($knownIP->first_seen);
			$ls = \App\Time::tag($knownIP->last_seen);
			$u = $knownIP->user_id === null ? 'Anonymous' : $knownIP->user->toAnchor();
			echo <<<HTML
<li>
	<ul>
		<li><strong>User:</strong> $u</li>
		<li><strong>First seen:</strong> $fs</li>
		<li><strong>Last seen:</strong> $ls</li>
	</ul>
</li>
HTML;
		} ?></ol>
<?	} ?>
	</section>
	<section>
		<h2>Users</h2>
<?php
	if (!empty($Users)){ ?>
		<p class="user-list"><?php
		$links = [];
		foreach ($Users as $u)
			$links[] = $u->toAnchor(true);
		echo implode(' ', $links); ?></p>
<?php
	}
	else echo \App\CoreUtils::notice('info','No users found'); ?>
	</section>
</div>
