<div id=content>
	<h1><?=$title?></h1>
	<p>A searchable<sup title="To Be Implemented">TBI</sup> list of <del style=opacity:.3;color:red>every</del> <ins style=color:green>some</ins> characters color keyed so far</p>
	<p class=align-center><a href="/colorguides/manage" class="typcn typcn-spanner btn large darkblue" disabled>Manage</a></p>
	<ul id=list><?php
	$Ponies = $Database->orderBy('label', 'ASC')->get('cg__ponies');
	if (!empty($Ponies)) foreach ($Ponies as $p){
		echo "<li>";

		if (!empty($p['sprite'])) echo "<div><img src='/img/cg/{$p['sprite']}.png' alt='".apos_encode($p['label'])."'></div>";
		echo "<div><strong>{$p['label']}</strong>";

		$Tags = $Database->rawQuery(
			'SELECT cgt.*
			FROM cg__tagged cgtg
			LEFT JOIN cg__tags cgt ON cgtg.tid = cgt.tid
			WHERE cgtg.ponyid = ?
			ORDER BY cgt.type DESC, cgt.name',array($p['id']));
		if (!empty($Tags)){
			echo "<div class=tags>";
			foreach ($Tags as $t){
				$class = !empty($t['type']) ? " class='{$t['type']}'" : '';
				$title = !empty($t['title']) ? " title='".apos_encode($t['title'])."'" : '';
				echo "<span$class$title>{$t['name']}</span> ";
			}
			echo "</div>";
		}

		$ColorGroups = $Database->where('ponyid', $p['id'])->get('cg__colorgroups');
		if (!empty($ColorGroups)){
			echo "<ul class=colors>";

			foreach ($ColorGroups as $cg){
				echo "<li>{$cg['label']}: ";

				$Colors = $Database->where('groupid', $cg['groupid'])->get('cg__colors');
				if (!empty($Colors))
					foreach ($Colors as $c){
						$title = apos_encode($c['label']);
						echo "<span style=background-color:{$c['hex']} title='$title'>{$c['hex']}</span> ";
					}

				echo "</li>";
			}
			echo "</ul>";
		}

		echo "</div></li>";
	}
?></ul>
</div>
<div id=sidebar>
<?php include "views/sidebar.php"; ?>
</div>