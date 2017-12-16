<?php

namespace App\Controllers;

use App\Appearances;
use App\Auth;
use App\CGUtils;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\Input;
use App\Logs;
use App\Models\Notification;
use App\Models\PCGSlotGift;
use App\Models\PCGSlotHistory;
use App\Notifications;
use App\Pagination;
use App\Permission;
use App\Response;
use App\Time;
use App\UserPrefs;
use App\Users;

class PersonalGuideController extends ColorGuideController {
	public function list($params){
		$this->_initPersonal($params);

		if (!$this->_ownedBy->canVisitorSeePCG())
			CoreUtils::noPerm();

		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
	    $_EntryCount = $this->_ownedBy->getPCGAppearanceCount();

	    $Pagination = new Pagination("@{$this->_ownedBy->name}/cg", $AppearancesPerPage, $_EntryCount);
	    $Ponies = $this->_ownedBy->getPCGAppearances($Pagination);

		CoreUtils::fixPath("$this->_cgPath/{$Pagination->page}");
		$heading = CoreUtils::posess($this->_ownedBy->name).' Personal Color Guide';
		$title = "Page {$Pagination->page} - $heading";

		$Pagination->respondIfShould(Appearances::getHTML($Ponies, NOWRAP), '#list');

		$settings = [
			'title' => $title,
			'heading' => $heading,
			'css' => ['pages/colorguide/guide'],
			'js' => ['jquery.qtip', 'jquery.ctxmenu', 'pages/colorguide/guide', 'paginate'],
			'import' => [
				'Ponies' => $Ponies,
				'Pagination' => $Pagination,
				'User' => $this->_ownedBy,
				'isOwner' => $this->_isOwnedByUser,
			],
		];
		if ($this->_isOwnedByUser || Permission::sufficient('staff')){
			$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
		}
		CoreUtils::loadPage('UserController::colorGuide', $settings);
	}

	public function pointHistory($params){
		$this->_initPersonal($params);

		if (!$this->_isOwnedByUser && Permission::insufficient('staff'))
			CoreUtils::noPerm();

		$EntriesPerPage = 20;
	    $_EntryCount = $this->_ownedBy->getPCGSlotHistoryEntryCount();

	    $Pagination = new Pagination("@{$this->_ownedBy->name}/cg/point-history", $EntriesPerPage, $_EntryCount);
	    $Entries = $this->_ownedBy->getPCGSlotHistoryEntries($Pagination);
	    if (\count($Entries) === 0){
	        $this->_ownedBy->recalculatePCGSlotHistroy();
	        $Entries = $this->_ownedBy->getPCGSlotHistoryEntries($Pagination);
	    }

		CoreUtils::fixPath("{$this->_cgPath}/point-history/{$Pagination->page}");
		$heading = ($this->_isOwnedByUser ? 'Your' : CoreUtils::posess($this->_ownedBy->name)).' Point History';
		$title = "Page {$Pagination->page} - $heading";

		$Pagination->respondIfShould(CGUtils::getPCGSlotHistoryHTML($Entries, NOWRAP), '#history-entries tbody');

		$js = ['paginate'];
		if (Permission::sufficient('staff'))
			$js[] = true;
		CoreUtils::loadPage('UserController::pcgSlots', [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => $js,
			'import' => [
				'Entries' => $Entries,
				'Pagination' => $Pagination,
				'User' => $this->_ownedBy,
				'isOwner' => $this->_isOwnedByUser,
			],
		]);
	}

	public function pointRecalc($params){
		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();

		$this->_initPersonal($params);

		$this->_ownedBy->recalculatePCGSlotHistroy();

		Response::done();
	}

	public function checkAvailSlots($params){
		CSRFProtection::protect();

		if (!isset($params['name']))
			Response::fail('Missing username');

		$targetUser = Users::get($params['name'], 'name');
		if (empty($targetUser))
			Response::fail('User not found');

		if (!UserPrefs::get('a_pcgmake', $targetUser))
			Response::fail(Appearances::PCG_APPEARANCE_MAKE_DISABLED);

		$avail = $targetUser->getPCGAvailablePoints(false);
		if ($avail < 10){
			$sameUser = $targetUser->id === Auth::$user->id;
			$You = $sameUser ? 'You' : $targetUser->name;
			$nave = $sameUser ? 'have' : 'has';
			$you = $sameUser ? 'you' : 'they';
			$cont = Permission::sufficient('member', $targetUser->role)
				? ", but $you can always fulfill some requests"
				: '. '.(
					$sameUser
					? 'Consider joining the group and fulfilling some requests on our site'
					: 'They should join the group and fulfill some requests on our site'
				);
			Response::fail("$You $nave no available slots left$cont to get more, or delete/edit ones $you've added already.");
		}
		Response::done();
	}

	public const NOT_ENOUGH_SLOTS_TO_GIFT = 'You need at least 1 slot you earned from completing requests to gift others. Remember that the free slot cannot be gifted away.';

	public function verifyGiftableSlots(){
		CSRFProtection::protect();

		if (!Auth::$signed_in)
			Response::fail();

		$avail = Auth::$user->getPCGAvailablePoints(false);

		if ($avail < 20)
			Response::fail(self::NOT_ENOUGH_SLOTS_TO_GIFT);

		Response::done([ 'avail' => $avail ]);
	}

	public function giftSlots($params){
		CSRFProtection::protect();

		if (!Auth::$signed_in)
			Response::fail();

		$target = Users::get($params['name'], 'name');
		if (empty($target))
			Response::fail('The specified user does not exist');
		if (Auth::$user->id === $target->id)
			Response::fail('You cannot gift slots to yourself');
		if (Permission::insufficient('member', $target->role))
			Response::fail('The target user must be a Club Member');
		$existingGift = DB::$instance
			->querySingle(
				'SELECT COUNT(*) as cnt FROM pcg_slot_gifts
				WHERE sender_id = ? AND receiver_id = ? AND NOT (rejected = TRUE OR claimed = TRUE OR refunded_by IS NOT NULL)', [Auth::$user->id, $target->id]);
		if ($existingGift['cnt'] !== 0)
			Response::fail('You have already sent a gift to this user, please wait for them to accept or reject it before sending another. If they haven\'t accepted your gift after 2 weeks, you can <a class="send-feedback">contact us</a> to have it refunded ot you.');

		$availSlots = Auth::$user->getPCGAvailablePoints(false);
		if ($availSlots < 20)
			Response::fail(self::NOT_ENOUGH_SLOTS_TO_GIFT);

		$amount = (new Input('amount','int',[
			Input::IN_RANGE => [1, floor($availSlots)],
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Amount of slots to gift is missing',
				Input::ERROR_INVALID => 'Amount of slots to gift (@value) is invalid',
				Input::ERROR_RANGE => 'Amount of slots to gift must be between @min and @max',
			],
		]))->out();


		PCGSlotGift::send(Auth::$user->id, $target->id, $amount);

		$nslots = CoreUtils::makePlural('slot', $amount, PREPEND_NUMBER);
		Response::success("<p>Your gift of $nslots is on its way! {$target->name} will be notified. Your generosity is commendable.</p>".CoreUtils::responseSmiley(':)'));
	}

	public function getPendingSlotGifts($params){
		CSRFProtection::protect();

		if (Permission::insufficient('staff'))
			Response::fail();

		$target = Users::get($params['name'], 'name');
		if (empty($target))
			Response::fail('The specified user does not exist');

		$gifts = $target->getPendingPCGSlotGifts();
		if (empty($gifts))
			Response::success('No pending gifts found.');

		$pendingGifts = [];
		foreach ($gifts as $gift){
			$pendingGifts[] = [
				'id' => $gift->id,
				'from' => $gift->sender->toAnchor(),
				'amount' => CoreUtils::makePlural('slot', $gift->amount, PREPEND_NUMBER),
				'sent' => Time::tag($gift->created_at),
			];
		}
		Response::done([
			'pendingGifts' => $pendingGifts,
		]);
	}

	public function refundSlotGifts($params){
		CSRFProtection::protect();

		if (Permission::insufficient('staff'))
			Response::fail();

		$giftIDs = (new Input('giftids','int[]',[
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'List of gifts to refund is missing',
				Input::ERROR_INVALID => 'List of gifts to refund (@value) is invalid',
			],
		]))->out();

		/** @var $gifts PCGSlotGift[] */
		$gifts = DB::$instance
			->setModel(PCGSlotGift::class)
			->where('id', $giftIDs)
			->where('rejected', false)
			->where('claimed', false)
			->where('refunded_by IS NULL')
			->get(PCGSlotGift::$table_name);
		if (\count($gifts) !== \count($giftIDs)){
			$found = [];
			foreach ($gifts as $gift)
				$found[] = $gift->id;
			CoreUtils::error_log("Gift refund item count mismatch.\nGot: ".implode(',', $giftIDs)."\nFound: ".implode(',', $found));
			Response::fail("The number of gifts to refund doesn't match the number of gifts found in the database. Please tell the developer to look into why this might have happened.");
		}

		foreach ($gifts as $gift){
			$giftIDArr =[ 'gift_id' => $gift->id ];
			PCGSlotHistory::makeRecord($gift->sender_id, 'gift_refunded', $gift->amount, $giftIDArr);
			$gift->sender->syncPCGSlotCount();

			$gift->refunded_by = Auth::$user->id;
			$gift->save();

			$notif = DB::$instance
				->where('recipient_id',$gift->receiver_id)
				->where('type','pcg-slot-gift')
				->where("data->'gift_id'", $gift->id)
				->getOne(Notification::$table_name);
			Notifications::safeMarkRead($notif->id, null, true);

			Logs::logAction('pcg_gift_refund', $giftIDArr);
			Notification::send($gift->sender_id, 'pcg-slot-refund', $giftIDArr);
		}

		Response::success('The selected gifts have been successfully refunded to their senders.');
	}
}
