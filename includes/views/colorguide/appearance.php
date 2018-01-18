<?php
use App\CGUtils;
use App\CoreUtils;
use App\Cutiemarks;
use App\Models\Appearance;
use App\Models\User;
use App\Permission;
use App\Tags;
/** @var $Appearance Appearance */
/** @var $Owner User|null */
/** @var $EQG bool */
/** @var $heading string */
/** @var $isOwner bool */ ?>
<div id="content">
	<div class="sprite-wrap"><?=$Appearance->getSpriteHTML(Permission::sufficient('staff') || $isOwner)?></div>
	<h1><?=CoreUtils::escapeHTML($heading)?></h1>
	<p>from <?php
	if (isset($Owner))
		echo $Owner->toAnchor().CoreUtils::posess($Owner->name, true)." <a href='/@{$Owner->name}/cg'>Personal Color Guide</a>";
	else {
		$eqgpath = $EQG ? '/eqg' : '';
		$guideprefix = $EQG ? 'EQG ' : '';
		echo "the MLP-VectorClub's <a href='/cg$eqgpath'>{$guideprefix}Color Guide</a>";
	} ?></p>

<?	if ($isOwner || Permission::sufficient('staff')){ ?>
	<div class="notice warn align-center appearance-private-notice"<?=!empty($Appearance->private)?'':' style="display:none"'?>><p><span class="typcn typcn-lock-closed"></span> <strong>This appearance is currently private (its colors are only visible to <?=isset($Owner)?(($isOwner?'you':$Owner->name).' and '):''?>staff members)</strong></p></div>
<?php
	}

	$RenderPath = $Appearance->getPalettePath();
	$FileModTime = '?t='.CoreUtils::filemtime($RenderPath); ?>
	<div id="p<?=$Appearance->id?>" class="section-container">
		<div class='button-block align-center'>
			<a class='btn link typcn typcn-image' href='/cg/v/<?="{$Appearance->id}p.png$FileModTime".(!empty($_GET['token']) ? "&token={$_GET['token']}" : '')?>' target='_blank'>View as PNG</a>
			<button class='getswatch typcn typcn-brush teal'>Download swatch file</button>
<?  if ($isOwner || Permission::sufficient('staff')){ ?>
			<button class='blue share typcn typcn-export' <?=$Appearance->private?'data-private="true"':''?> data-url="<?=rtrim(ABSPATH,'/').$Appearance->toURL().($Appearance->private ? "?token={$Appearance->token}" : '')?>">Share</button>
			<button class='darkblue edit-appearance typcn typcn-pencil'>Edit metadata</button>
<?php   if ($Appearance->id){ ?>
			<button class='red delete-appearance typcn typcn-trash'>Delete apperance</button>
<?php   }
	} ?>
		</div>

<?  if ($Appearance->owner_id === null){
		echo $Appearance->getChangesHTML();
		if ($Appearance->id !== 0 && (\App\DB::$instance->where('appearance_id', $Appearance->id)->has('tagged') || Permission::sufficient('staff'))){ ?>
		<section id="tags">
			<h2><span class='typcn typcn-tags'></span>Tags</h2>
			<div class='tags'><?=$Appearance->getTagsHTML(NOWRAP)?></div>
<?php       if (Permission::sufficient('staff')){ ?>
			<div class="button-block">
				<button id="edit-tags-btn" class="darkblue typcn typcn-pencil">Edit tags</button>
				<!-- <a class="btn link typcn typcn-document" href="/cg/tag-changes/<?=$Appearance->id?>">Tag changes</a> -->
			</div>
<?php       } ?>
		</section>
<?php
		}
		echo $Appearance->getRelatedEpisodesHTML($EQG);
	}
	if (!empty($Appearance->notes_src)){ ?>
		<section>
			<h2><span class='typcn typcn-info-large'></span>Additional notes</h2>
			<div id="notes"><?=$Appearance->getNotesHTML(NOWRAP, NOTE_TEXT_ONLY)?></div>
		</section>
<?php
	}

	$CutieMarks = Cutiemarks::get($Appearance);
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
			<div class="admin button-block">
				<button class="darkblue typcn typcn-arrow-unsorted reorder-cgs">Re-order groups</button>
				<button class="green typcn typcn-plus create-cg">Create group</button>
				<button class="darkblue typcn typcn typcn-document-add apply-template">Apply template</button>
			</div>
<?  }
	if ($placehold = $Appearance->getPendingPlaceholder())
		echo $placehold;
	else { ?>
			<ul id="colors" class="colors"><?php
		$CGs = $Appearance->color_groups;
		$AllColors = CGUtils::getColorsForEach($CGs);
		foreach ($CGs as $cg)
			echo $cg->getHTML($AllColors, WRAP, NO_COLON, OUTPUT_COLOR_NAMES);
			?></ul>
		</section>
<?  }
	if ($Appearance->owner_id === null)
		echo $Appearance->getRelatedHTML(); ?>
	</div>
</div>

<?  $export = [
		'EQG' => $EQG,
		'AppearancePage' => true,
		'PersonalGuide' => $Owner->name ?? false,
	];
	if ($isOwner || Permission::sufficient('staff')){
		global $HEX_COLOR_REGEX, $TAG_NAME_REGEX;
		$export = array_merge($export, [
			'TAG_TYPES_ASSOC' => Tags::TAG_TYPES,
			'TAG_NAME_REGEX' => $TAG_NAME_REGEX,
			'MAX_SIZE' => CoreUtils::getMaxUploadSize(),
			'HEX_COLOR_PATTERN' => $HEX_COLOR_REGEX,
		]);
	}
	echo CoreUtils::exportVars($export); ?>
