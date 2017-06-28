<?php
use App\Auth;
use App\Permission;
use App\Time;

/** @var string $heading */
/** @var string $EventType */
/** @var \App\Models\Event $Event */
/** @var \App\Models\EventEntry[] $UserEntries */ ?>
<div id="content">
	<h1><?=$heading?></h1>
	<p><?=$EventType?> for <?=$Event->getEntryRoleName()?> &bull; <?=$Event->hasStarted() ? (($Event->hasEnded() ? 'Ended' : 'Ends').' '.Time::tag($Event->ends_at)) : 'Starts '.Time::tag($startts)?></p>

<?php   $couldEnter = Auth::$signed_in && $Event->checkCanEnter(Auth::$user);
		$canEnter = $couldEnter && $Event->hasStarted() && !$Event->hasEnded();
		$finalized = $Event->isFinalized();
		if (Auth::$signed_in && !$finalized){ ?>
	<div class="align-center" id="event-<?=$Event->id?>">
		<button class="green typcn typcn-user-add" <?=$canEnter?'':'disabled'?> id="enter-event">Enter</button>
<?php       if (Permission::sufficient('staff')){ ?>
		<button class="blue typcn typcn-pencil edit-event">Edit</button>
		<button class="darkblue typcn typcn-image finalize-event" <?=$Event->type === 'collab' ? '':'disabled'?>>Finalize</button>
		<button class="red typcn typcn-trash delete-event">Delete</button>
<?php       } ?>
	</div>
<?php   }
		if ($finalized){ ?>
	<section>
		<h2><span class="typcn typcn-image"></span><?=$Event->type === 'collab' ? 'Finished image' : 'Results'?></h2>
		<?=$Event->getWinnerHTML()?>
	</section>
<?php   } ?>

	<section>
		<h2><span class='typcn typcn-info-large'></span>Description</h2>
		<div id="description"><?=$Event->desc_rend?>
			<p>Entries will be accepted until <?= Time::tag(strtotime($Event->ends_at), Time::TAG_EXTENDED, Time::TAG_STATIC_DYNTIME)?>. Entrants can submit <?=isset($Event->max_entries) ? 'a maximum of '.\App\CoreUtils::makePlural('entry', $Event->max_entries, PREPEND_NUMBER):'an unlimited number of entries'?> each.</p>
<?php   if ($Event->type === 'contest'){ ?>
			<p>
				The entry that receives the highest positive overall score will be the winner. <?=\App\CoreUtils::makePlural(Permission::ROLES_ASSOC[$Event->vote_role])?> may vote only once per entry, and entrants cannot vote on their own entries.<br>
				Votes can only be changed 1 hour after being cast, they are locked in afterwards. Editing the entry removes this lock from cast votes.
			</p>
<?php   }
		if (!$canEnter) {
			if (!$finalized){ ?>
			<p class="color-red"><?=Auth::$signed_in?'You '.($couldEnter?($Event->hasEnded()?'can no longer':''):'cannot').' participate in this event'.($couldEnter && !$Event->hasStarted()?' yet':'').'.':'You must be signed in to participate in events.'?></p>
<?php       }
			else { ?>
			<p class="color-blue">This event has concluded. Thank you to everyone who participated!</p>
<?php  		}
		} ?>
		</div>
	</section>

	<section>
		<h2><span class='typcn typcn-group'></span>Entries</h2>
		<?=$Event->getEntriesHTML()?>
	</section>
</div>
<?php
	echo \App\CoreUtils::exportVars([
		'EVENT_TYPES' => \App\Models\Event::EVENT_TYPES,
		'EventPage' => true,
		'EventType' => $Event->type,
	]);
