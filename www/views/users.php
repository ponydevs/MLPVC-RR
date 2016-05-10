<div id="content">
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
		foreach (array_reverse(Permission::$ROLES) as $r => $v){
			if (empty($Arranged[$r])) continue;
			$users = $Arranged[$r];
			$userCount = count($users);
			$group = Permission::$ROLES_ASSOC[$r].($userCount !== 1 ? 's' : '');
			$groupInitials = '['.Permission::LabelInitials($r).']';
			$usersStr = array();
			foreach ($users as $u)
				$usersStr[] = User::GetProfileLink($u);
			$usersStr = implode(', ', $usersStr);
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
