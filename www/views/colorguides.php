<div id=content>
	<h1><?=$title?></h1>
	<p>A sortable/filterable list of every character color keyed so far*</p>

	<ul><?php
	$Groups = $Database->get('colorguides__groups');
	foreach ($Groups as $g){
		echo "<li><strong>{$g['label']}</strong>";
		$Ponies = $Database->rawQuery('SELECT cgp.* FROM colorguides__group_pony_binds cggpb LEFT JOIN colorguides__ponies cgp ON cggpb.ponyid = cgp.id WHERE cggpb.ggid = ?',array($g['ggid']));
		if (!empty($Ponies)){
			echo "<ul>";
			foreach ($Ponies as $p){
				echo "<li>{$p['label']}";

				$ColorGroups = $Database->where('ponyid', $p['id'])->get('colorguides__colorgroups');
				echo "<ul>";
				# TODO WE NEED TO GO DEEPER
				foreach ($ColorGroups as $cg){
					echo "<li class=colors>{$cg['label']}: ";

					$Colors = $Database->where('groupid', $cg['groupid'])->get('colorguides__colors');
					if (!empty($Colors))
						foreach ($Colors as $c){
							$title = str_replace("'",'&apos;',$c['label']);
							echo "<span style=background-color:{$c['hex']} title='$title'></span> ";
						}

					echo "</li>";
				}
				echo "</ul>";

				echo "</li>";
			}
			echo "</ul>";
		}
		echo "</li>";
	}
?></ul>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>