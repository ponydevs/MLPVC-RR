<?php
use App\CGUtils;
use App\CoreUtils;
use App\Cutiemarks;
use App\Models\User;
use App\Permission;
use App\Appearances;
use App\ColorGroups;
use App\Tags;
/** @var $Appearance array */
/** @var $Owner User */
/** @var $EQG int */
/** @var $heading string */
/** @var $isOwner bool */ ?>
<div id="content">
	<div class="sprite-wrap"><?=Appearances::getSpriteHTML($Appearance, Permission::sufficient('staff') || $isOwner)?></div>
	<h1><?=CoreUtils::escapeHTML($heading)?></h1>
	<p>from <?=isset($Owner)?"<a href='/@{$Owner->name}'>{$Owner->name}</a>".CoreUtils::posess($Owner->name, true)." <a href='/@{$Owner->name}/cg'>Personal Color Guide</a>":"the MLP-VectorClub's <a href='/cg".($EQG?'/eqg':'')."'>".($EQG?'EQG ':'').'Color Guide</a>' ?></p>

<?  if (Permission::sufficient('staff') || $isOwner){ ?>
	<div class="notice warn align-center appearance-private-notice"<?=!empty($Appearance['private'])?'':' style="display:none"'?>><p><span class="typcn typcn-lock-closed"></span> <strong>This appearance is currently private (its colors are only visible to <?=isset($Owner)?(($isOwner?'you':$Owner->name).' and '):''?>staff members)</strong></p></div>
<?php
	}

	$RenderPath = FSPATH."cg_render/{$Appearance['id']}.png";
	$FileModTime = '?t='.(file_exists($RenderPath) ? filemtime($RenderPath) : time()); ?>
	<div id="p<?=$Appearance['id']?>">
		<div class='align-center'>
			<a class='btn link typcn typcn-image' href='/cg/v/<?="{$Appearance['id']}p.png$FileModTime"?>' target='_blank'>View as PNG</a>
			<button class='getswatch typcn typcn-brush teal'>Download swatch file</button>
<?  if (Permission::sufficient('staff') || $isOwner){ ?>
			<button class='darkblue edit typcn typcn-pencil'>Edit metadata</button>
<?php   if ($Appearance['id']){ ?>
			<button class='red delete typcn typcn-trash'>Delete apperance</button>
<?php   }
	} ?>
		</div>

<?  if (!isset($Appearance['owner'])){
		if (!empty($Changes))
			echo str_replace('@',CGUtils::getChangesHTML($Changes),CGUtils::CHANGES_SECTION);
		if ($Appearance['id'] !== 0 && ($Database->where('ponyid', $Appearance['id'])->has('tagged') || Permission::sufficient('staff'))){ ?>
		<section id="tags">
			<h2><span class='typcn typcn-tags'></span>Tags</h2>
			<div class='tags'><?=Appearances::getTagsHTML($Appearance['id'],NOWRAP)?></div>
		</section>
<?php
		}
		echo Appearances::getRelatedEpisodesHTML($Appearance, $EQG);
	}
	if (!empty($Appearance['notes'])){ ?>
		<section>
			<h2><span class='typcn typcn-info-large'></span>Additional notes</h2>
			<p id="notes"><?=Appearances::getNotesHTML($Appearance, NOWRAP, NOTE_TEXT_ONLY)?></p>
		</section>
<?php
	}

	$CutieMarks = Cutiemarks::get($Appearance['id']);
	$hideList = empty($CutieMarks); ?>
		<section class="approved-cutie-mark<?=$hideList?' hidden':''?>">
			<h2>Cutie Mark</h2>
			<p class="aside"><?=count($CutieMarks)===1?'This is just an illustration':'These are just illustrations'?>, the body shape & colors are <strong>not</strong> guaranteed to reflect the actual design.</p>
<?php
	if (!$hideList)
		echo Cutiemarks::getListForAppearancePage($CutieMarks); ?>
		</section>
		<section class="color-list">
<?  if (Permission::sufficient('staff') || $isOwner){ ?>
			<h2 class="admin">Color groups</h2>
			<div class="admin">
				<button class="darkblue typcn typcn-arrow-unsorted reorder-cgs">Re-order groups</button>
				<button class="green typcn typcn-plus create-cg">Create group</button>
			</div>
<?  }
	if ($placehold = Appearances::getPendingPlaceholderFor($Appearance))
		echo $placehold;
	else { ?>
			<ul id="colors" class="colors"><?php
		$CGs = ColorGroups::get($Appearance['id']);
		$AllColors = ColorGroups::getColorsForEach($CGs);
		foreach ($CGs as $cg)
			echo ColorGroups::getHTML($cg, $AllColors, WRAP, NO_COLON, OUTPUT_COLOR_NAMES);
			?></ul>
		</section>
<?  }
	if (!isset($Appearance['owner']))
		echo Appearances::getRelatedHTML(Appearances::getRelated($Appearance['id'])); ?>
	</div>
</div>

<?  $export = [
		'EQG' => $EQG,
		'AppearancePage' => true,
		'PersonalGuide' => $Owner->name ?? false,
];
	if (Permission::sufficient('staff') || $isOwner)
		$export = array_merge($export, [
			'TAG_TYPES_ASSOC' => Tags::$TAG_TYPES_ASSOC,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		]);
	echo CoreUtils::exportVars($export); ?>
