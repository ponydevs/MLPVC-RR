<div id="content">
	<h1><?=$title?></h1>
	<p>List of all users in the database</p>

	<div id="users">
<?php
use App\CoreUtils;
use App\Permission;

/** @var $Users \App\Models\User[] */
$Users = $Database->orderBy('name','asc')->get('users');
if (!empty($Users)){
	$Arranged = array();
	foreach ($Users as $u){
		if (!isset($Arranged[$u->role])) $Arranged[$u->role] = array();

		$Arranged[$u->role][] = $u;
	}
	foreach (array_reverse(Permission::$ROLES) as $r => $v){
		if (empty($Arranged[$r])) continue;
		/** @var $users \App\Models\User[] */
		$users = $Arranged[$r];
		$group = CoreUtils::makePlural(Permission::$ROLES_ASSOC[$r], count($users), true);
		$groupInitials = '['.Permission::LabelInitials($r).']';
		$usersStr = array();
		foreach ($users as $u)
			$usersStr[] = $u->getProfileLink();
		$usersStr = implode(', ', $usersStr);
		echo <<<HTML
<section>
	<h2>$group $groupInitials</h2>
	$usersStr
</section>
HTML;
	}
} ?>
	</div>
</div>
