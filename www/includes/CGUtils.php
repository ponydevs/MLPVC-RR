<?php
	##########################################################
	## Utility functions related to the color guide feature ##
	##########################################################

	// Constant to disable returning of wrapper element with markup generators
	define('NOWRAP', false);

	// Returns the markup of the color list for a specific pony \\
	function get_colors_html($PonyID, $wrap = true){
		global $CGDb;

		$ColorGroups = $CGDb->where('ponyid', $PonyID)->get('colorgroups');
		$HTML = '';
		if (!empty($ColorGroups)){
			$HTML = $wrap ? "<ul class=colors>" : '';
			foreach ($ColorGroups as $cg){
				$cg['label'] = apos_encode($cg['label']);
				$HTML .= "<li><span class=cat>{$cg['label']}: </span>";

				$Colors = $CGDb->where('groupid', $cg['groupid'])->get('colors');
				if (!empty($Colors))
					foreach ($Colors as $i => $c){
						$title = apos_encode($c['label']);
						$HTML .= "<span id='{$c['colorid']}-{$cg['groupid']}' style=background-color:{$c['hex']} title='$title'>{$c['hex']}</span> ";
					};

				$HTML .= "</li>";
			}
			if ($wrap) $HTML .= "</ul>";
		}
		return $HTML;
	}

	// Return the markup of a set of tags belonging to a specific pony \\
	function get_tags_html($PonyID, $wrap = true){
		global $CGDb;

		$Tags = $CGDb->rawQuery(
			'SELECT cgt.*
			FROM tagged cgtg
			LEFT JOIN tags cgt ON cgtg.tid = cgt.tid
			WHERE cgtg.ponyid = ?
			ORDER BY cgt.type DESC, cgt.name',array($PonyID));
		$HTML = '';
		if (!empty($Tags)){
			$HTML = $wrap ? "<div class=tags>" : '';
			foreach ($Tags as $i => $t){
				$class = " class='tag id-{$t['tid']}".(!empty($t['type'])?' typ-'.$t['type']:'')."'";
				$title = !empty($t['title']) ? " title='".apos_encode($t['title'])."'" : '';
				$HTML .= "<span$class$title>{$t['name']}</span> ";
			}
			if ($wrap) $HTML .= "</div>";
		}
		return $HTML;
	}

	// List of usable tag types
	$TAG_TYPES_ASSOC = array(
		'app' => 'Appearance',
		'cat' => 'Category',
		'ep' => 'Episode',
		'gen' => 'Gender',
		'spec' => 'Species',
	);
	$TAG_TYPES = array_keys($TAG_TYPES_ASSOC);
	define('TAG_NAME_PATTERN', '[a-z\d ().-]');
	define('INVERSE_TAG_NAME_PATTERN', implode('^',explode('[', TAG_NAME_PATTERN)));

	// Returns the markup for the notes displayed under an appaerance
	function get_notes_html($p){
		$p['notes'] = !empty($p['notes']) ? nl2br(htmlspecialchars($p['notes'])) : '';
		return "<div class='notes'>{$p['notes']}</div>";
	}

	// Returns the markup for an array of pony datase rows \\
	function get_ponies_html($Ponies, $wrap = true){
		global $CGDb;

		$HTML = $wrap ? '<ul id=list>' : '';
		if (!empty($Ponies)) foreach ($Ponies as $p){
			$p['label'] = htmlspecialchars($p['label']);
			$imgPth = "img/cg/{$p['id']}.png";
			if (!file_Exists(APPATH.$imgPth)) $imgPth = "img/blank-pixel.png";
			$img = '';
			$img .= "<div><img src='/$imgPth' alt='".apos_encode($p['label'])."'></div>";

			$notes = get_notes_html($p);
			$tags = get_tags_html($p['id']);
			$colors = get_colors_html($p['id']);
			$editBtn = PERM('inspector') ? '<button class="edit typcn typcn-edit blue" title="Enable edit mode" disabled></button><button class="delete typcn typcn-trash red" title="Delete" disabled></button>' : '';

			$HTML .= "<li id=p{$p['id']}>$img<div><strong>{$p['label']}$editBtn</strong>$notes$tags$colors</div></li>";
		}

		return $HTML.($wrap?'</ul>':'');
	}
