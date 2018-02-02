<?php
use App\Auth;
use App\Permission;
use App\Time;

/** @var string $heading */
/** @var string $EventType */
/** @var string $startts */
/** @var \App\Models\Event $Event */
/** @var \App\Models\EventEntry[] $UserEntries */ ?>
<div id="content" class="section-container">
	<h1><?=$heading?></h1>
	<p><?=$EventType?> for <?=$Event->getEntryRoleName()?> &bull; <?=$Event->hasStarted() ? (($Event->hasEnded() ? 'Ended' : 'Ends').' '.Time::tag($Event->ends_at)) : 'Starts '.Time::tag($Event->starts_at)?></p>

<?php   $couldEnter = Auth::$signed_in && $Event->checkCanEnter(Auth::$user);
		$canEnter = $couldEnter && $Event->isOngoing();
		$finalized = $Event->isFinalized();
		if (Auth::$signed_in && !$finalized){ ?>
	<div class="align-center button-block" id="event-<?=$Event->id?>">
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
			<p class="color-red"><?php
				if (Auth::$signed_in){
					if ($couldEnter){
						if (!$Event->hasStarted())
							echo "You can't participate in this event yet.";
						else echo 'You can no longer participate in this event';
					}
					else {
						if ($Event->entry_role === 'spec_discord'){
							echo 'You must be a member of our Discord server to participate in this event. <a href="'.DISCORD_INVITE_LINK.'">Join now</a>';
							if (!Auth::$user->isDiscordLinked())
								echo "<br>Be sure to <a href='".Auth::$user->toURL()."#discord-connect'>link your account</a> on the site once you've joined.";
						}
						else echo 'You cannot participate in this event.';
					}
				}
				else echo 'You must be signed in to see whether you can participate in events.';
			?></p>
<?php       }
			else echo "<p class='color-blue'>This event has concluded. Thank you to everyone who participated!</p>";
		} ?>
		</div>
	</section>

	<section>
		<h2><span class='typcn typcn-group'></span>Entries</h2>
		<?=$Event->getEntriesHTML(true)?>
	</section>
</div>
<?php
	echo \App\CoreUtils::exportVars([
		'EVENT_TYPES' => \App\Models\Event::EVENT_TYPES,
		'EventPage' => true,
		'EventType' => $Event->type,
	]);
