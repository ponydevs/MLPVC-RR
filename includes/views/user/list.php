<?php /** @var $title string */ ?>
<div id="content">
	<h1><?=$title?></h1>
	<p>List of all users in the database</p>

	<div id="users" class="section-container">
<?php
use App\CoreUtils;
use App\Permission;

/** @var $Users \App\Models\User[] */
if (!empty($Users)){
	$Arranged = [];
	foreach ($Users as $u){
		if (!isset($Arranged[$u->role])) $Arranged[$u->role] = [];

		$Arranged[$u->maskedRole()][] = $u;
	}
	foreach (array_reverse(Permission::ROLES) as $r => $v){
		if (empty($Arranged[$r])) continue;
		/** @var $users \App\Models\User[] */
		$users = $Arranged[$r];
		$group = CoreUtils::makePlural(Permission::ROLES_ASSOC[$r], count($users), true);
		if (count($users) > 50){
			$usersOut = [];
			foreach ($users as $u){
				$firstletter = strtoupper($u->name[0]);
				if (preg_match(new \App\RegExp('^[^a-z]$','i'), $firstletter))
					$firstletter = '#';
				$usersOut[$firstletter][] = $u->toAnchor();
			}

			ksort($usersOut);

			$usersStr = '';
			foreach ($usersOut as $chr => $users){
				$usersStr .= " <strong>$chr</strong> ".implode(', ',$users);
			}
		}
		else {
			$usersOut = [];
			foreach ($users as $u)
				$usersOut[] = $u->toAnchor();
			$usersStr = implode(', ',$usersOut);
		}
		echo <<<HTML
<section>
	<h2>$group</h2>
	<div>$usersStr</div>
</section>
HTML;
	}
} ?>
	</div>
</div>
