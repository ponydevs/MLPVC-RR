<div id="content" class="sections-with-icons">
	<h1><?=$title?></h1>
	<p>List of all users in the database</p>

	<div id="users">
<?  $Users = $Database->orderBy('name','asc')->get('users');
	if (!empty($Users)){
		$Arranged = array();
		foreach ($Users as $u){
			if (!isset($Arranged[$u['role']])) $Arranged[$u['role']] = array();

			$Arranged[$u['role']][] = $u;
		}
		global $ROLES;
		foreach (array_reverse($ROLES) as $r){
			if (empty($Arranged[$r])) continue;
			$users = $Arranged[$r];
			$userCount = count($users);
			$group = $ROLES_ASSOC[$r].($userCount !== 1 ? 's' : '');
			$groupInitials = '['.label_to_initials($ROLES_ASSOC[$r]).']';
			$usersStr = array();
			foreach ($users as $u)
				$usersStr[] = profile_link($u);
			$usersStr = implode(', ', $usersStr);
			global $ROLES_ASSOC;
			echo <<<HTML
<section>
	<h2><strong>$userCount</strong> $group $groupInitials</h2>
	$usersStr
</section>
HTML;
		}
	}  ?>
	</div>
</div>
