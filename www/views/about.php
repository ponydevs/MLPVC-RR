<div id=content>
	<img src="/img/logo.png" alt="MLP Vector Club Requests & Reservations Logo">
	<h1>MLP Vector Club Requests & Reservations</h1>
	<p>An automated system for handling requests & reservations, made for MLP-VectorClub</p>
	<section>
		<h2>What's this site?</h2>
		<div>
			<p>This website is a new, automatic way to process and store the requests & reservations users want to make. It's that simple.</p>
			<p>In the past, the management of comments under journals was done manually. Because of this, there had to be a person who checks those comments, evaluates them, then updates the journal accordingly. This took time, sometimes, longer time than it should have taken. The group's staff consists of busy people, and we can't expect them to consantly monitor new incoming comments. But, with the help of this website, new entries can be submitted and added to a list, just like the journals, automatically, without having to have someone do this monotonous task.</p>
		</div>
	</section>
	<section>
		<h2>Why does the version number look so... <em>random</em>?</h2>
		<div>
			<p>This website's complete codebase is <a href="<?=GITHUB_URL?>">available for anyone to see on GitHub</a>. The version number is the first few characters of the latest commit's ID. In this case, a commit is basically an update to the site. Whenever a new update is applied, the version number changes automatically.</p>
		</div>
	</section>
	<section>
		<h2>Roll the credits, please!</h2>
		<div>
			<p>Your wish is my command.</p>
			<p><strong>Coding, design, hosting:</strong> <?=$DevLink?></p>
			<p><strong>Used libraries &amp; icons include:</strong> <a href="http://jquery.com/">jQuery</a>, <a href="https://github.com/joshcam/PHP-MySQLi-Database-Class">MysqliDb</a>, <a href="http://www.typicons.com/">Typicons</a>, <a href="https://www.npmjs.com/package/uglify-js">Uglify-js</a>, <a href="http://sass-lang.com/">SASS</a></p>
			<p><strong>Header font:</strong> <a href="http://www.mattyhex.net/CMR/">Celestia Medium Redux</a></p>
			<p><strong>deviantArt logo</strong> <em>(used on profile pages)</em> &copy; <a href="http://www.mattyhex.net/CMR/">DeviantArt</a></p>
			<p>
				<strong>Application logo</strong> based on <a href="http://pirill-poveniy.deviantart.com/art/Collab-Christmas-Vector-of-the-MLP-VC-Mascot-503196118">Christmas Vector of the MLP-VC Mascot</a> by the following artists:
				<ul>
					<li><a href="http://pirill-poveniy.deviantart.com/">Pirill-Poveniy</a></li>
					<li><a href="http://thediscorded.deviantart.com/">thediscorded</a></li>
					<li><a href="http://masemj.deviantart.com/">masemj</a></li>
					<li><a href="http://ambassad0r.deviantart.com/">Ambassad0r</a> <em>(idea)</em></li>
				</ul>
			<p><strong>Extrenal link icon</strong> (licensed GPL) taken from <a href="https://commons.wikimedia.org/wiki/File:Icon_External_Link.svg">Wikimedia Commons</a></p>
		</div>
	</section>
<?php
	if (PERM('users.listall')){
		$Users = $Database->orderBy('name')->get('users');
		if (!empty($Users)){
?>
	<section>
		<h2>Linked users</h2>
		<div>
<?php
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
				$usersStr = array();
				foreach ($users as $u)
					$usersStr[] = profile_link($u);
				$usersStr = implode(', ', $usersStr);
				global $ROLES_ASSOC;
				echo <<<HTML
			<p><strong>$userCount $group:</strong> $usersStr</p>

HTML;
			} ?>
		</div>
	</section>
<?php   }
	} ?>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>