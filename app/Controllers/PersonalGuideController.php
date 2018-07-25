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
use App\Models\PCGPointGrant;
use App\Models\PCGSlotGift;
use App\Models\PCGSlotHistory;
use App\Models\User;
use App\Notifications;
use App\Pagination;
use App\Permission;
use App\Response;
use App\Time;
use App\UserPrefs;
use App\Users;

class PersonalGuideController extends ColorGuideController {
	use UserLoaderTrait;

	public function list($params){
		$this->_initialize($params);

		if (!$this->owner->canVisitorSeePCG())
			CoreUtils::noPerm();

		$AppearancesPerPage = UserPrefs::get('cg_itemsperpage');
	    $_EntryCount = $this->owner->getPCGAppearanceCount();

	    $pagination = new Pagination($this->path, $AppearancesPerPage, $_EntryCount);
	    $appearances = $this->owner->getPCGAppearances($pagination);

		CoreUtils::fixPath($pagination->toURI());
		$heading = CoreUtils::posess($this->owner->name).' Personal Color Guide';
		$title = "Page {$pagination->getPage()} - $heading";

		$is_owner = $this->ownerIsCurrentUser;
		$owner_or_staff = $is_owner || Permission::sufficient('staff');

		$settings = [
			'title' => $title,
			'heading' => $heading,
			'css' => ['pages/colorguide/guide'],
			'js' => ['jquery.ctxmenu', 'pages/colorguide/guide', 'paginate'],
			'import' => [
				'appearances' => $appearances,
				'pagination' => $pagination,
				'user' => $this->owner,
				'is_owner' => $is_owner,
				'owner_or_staff' => $owner_or_staff,
				'max_upload_size' => CoreUtils::getMaxUploadSize(),
			],
		];
		if ($owner_or_staff){
			global $HEX_COLOR_REGEX;

			$settings['css'] = array_merge($settings['css'], self::GUIDE_MANAGE_CSS);
			$settings['js'] = array_merge($settings['js'], self::GUIDE_MANAGE_JS);
			$settings['import']['hex_color_regex'] = $HEX_COLOR_REGEX;
		}
		CoreUtils::loadPage('UserController::colorguide', $settings);
	}

	public function pointHistory($params){
		$this->_initialize($params);

		if (!$this->ownerIsCurrentUser && Permission::insufficient('staff'))
			CoreUtils::noPerm();

		$EntriesPerPage = 20;
	    $_EntryCount = $this->owner->getPCGSlotHistoryEntryCount();

	    $pagination = new Pagination("{$this->path}/point-history", $EntriesPerPage, $_EntryCount);
	    $entries = $this->owner->getPCGSlotHistoryEntries($pagination);
	    if (\count($entries) === 0){
	        $this->owner->recalculatePCGSlotHistroy();
	        $entries = $this->owner->getPCGSlotHistoryEntries($pagination);
	    }

		CoreUtils::fixPath($pagination->toURI());
		$heading = ($this->ownerIsCurrentUser ? 'Your' : CoreUtils::posess($this->owner->name)).' Point History';
		$title = "Page {$pagination->getPage()} - $heading";

		$js = ['paginate'];
		if (Permission::sufficient('staff'))
			$js[] = true;
		CoreUtils::loadPage('UserController::pcgSlots', [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => $js,
			'import' => [
				'entries' => $entries,
				'pagination' => $pagination,
				'user' => $this->owner,
				'is_owner' => $this->ownerIsCurrentUser,
				'pcg_slot_history' => CGUtils::getPCGSlotHistoryHTML($entries),
			],
		]);
	}

	public function pointRecalc($params){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();

		$this->load_user($params);

		$this->user->recalculatePCGSlotHistroy();

		Response::done();
	}

	public function slotsApi($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		switch ($this->action){
			case 'GET':
				$this->load_user($params);

				if (!UserPrefs::get('a_pcgmake', $this->user))
					Response::fail(Appearances::PCG_APPEARANCE_MAKE_DISABLED);

				$avail = $this->user->getPCGAvailablePoints(false);
				if ($avail < 10){
					$sameUser = $this->user->id === Auth::$user->id;
					$You = $sameUser ? 'You' : $this->user->name;
					$nave = $sameUser ? 'have' : 'has';
					$you = $sameUser ? 'you' : 'they';
					$cont = Permission::sufficient('member', $this->user->role)
						? ", but $you can always fulfill some requests"
						: '. '.(
							$sameUser
							? 'Consider joining the group and fulfilling some requests on our site'
							: 'They should join the group and fulfill some requests on our site'
						);
					Response::fail("$You $nave no available slots left$cont to get more, or delete/edit ones $you've added already.");
				}
				Response::done();
			break;
			case 'POST':
				if (!Auth::$signed_in)
					Response::fail();

				$this->load_user($params);

				if (Auth::$user->id === $this->user->id)
					Response::fail('You cannot gift slots to yourself');
				if (Permission::insufficient('member', $this->user->role))
					Response::fail('The target user must be a Club Member');
				$existingGift = DB::$instance
					->querySingle(
						'SELECT COUNT(*) as cnt FROM pcg_slot_gifts
						WHERE sender_id = ? AND receiver_id = ? AND NOT (rejected = TRUE OR claimed = TRUE OR refunded_by IS NOT NULL)', [Auth::$user->id, $this->user->id]);
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

				PCGSlotGift::send(Auth::$user->id, $this->user->id, $amount);

				$nSlots = CoreUtils::makePlural('slot', $amount, PREPEND_NUMBER);
				Response::success("<p>Your gift of $nSlots is on its way! {$this->user->name} will be notified. Your generosity is commendable.</p>".CoreUtils::responseSmiley(':)'));
			break;
			default:
				CoreUtils::notAllowed();
		}
	}

	public const NOT_ENOUGH_SLOTS_TO_GIFT = 'You need at least 1 slot you earned from completing requests to gift others. Remember that the free slot cannot be gifted away.';

	public function verifyGiftableSlots(){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (!Auth::$signed_in)
			Response::fail();

		$avail = Auth::$user->getPCGAvailablePoints(false);

		if ($avail < 20)
			Response::fail(self::NOT_ENOUGH_SLOTS_TO_GIFT);

		Response::done([ 'avail' => $avail ]);
	}

	public function getPendingSlotGifts($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_user($params);

		$gifts = $this->user->getPendingPCGSlotGifts();
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
		Response::done([ 'pendingGifts' => $pendingGifts ]);
	}

	public function refundSlotGifts(){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

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
			PCGSlotHistory::record($gift->sender_id, 'gift_refunded', $gift->amount, $giftIDArr);
			$gift->sender->syncPCGSlotCount();

			$gift->refunded_by = Auth::$user->id;
			$gift->save();

			/** @var $notif Notification */
			$notif = DB::$instance
				->where('recipient_id',$gift->receiver_id)
				->where('type','pcg-slot-gift')
				->where("data->'gift_id'", $gift->id)
				->getOne(Notification::$table_name);
			$notif->safeMarkRead();

			Logs::logAction('pcg_gift_refund', $giftIDArr);
			Notification::send($gift->sender_id, 'pcg-slot-refund', $giftIDArr);
		}

		Response::success('The selected gifts have been successfully refunded to their senders.');
	}

	public function pointsApi($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (Permission::insufficient('staff'))
			Response::fail();

		$this->load_user($params);

		switch ($this->action){
			case 'GET':
				Response::done([ 'amount' => $this->user->getPCGAvailablePoints(false)-10 ]);
			break;
			case 'POST':
				$amount = (new Input('amount','int',[
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Amount of slots to give is missing',
						Input::ERROR_INVALID => 'Amount of slots to give (@value) is invalid',
						Input::ERROR_RANGE => 'Amount of slots to give must be between @min and @max',
					],
				]))->out();
				if ($amount === 0)
					Response::fail("You have to enter an integer that isn't 0");

				$availableSlots = $this->user->getPCGAvailablePoints(false);
				if ($availableSlots + $amount < 10)
					Response::fail('This would cause the users points to go below 10');

				$comment = (new Input('comment','string',[
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [2, 140],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_INVALID => 'Comment (@value) is invalid',
						Input::ERROR_RANGE => 'Comment must be between @min and @max chars',
					],
				]))->out();
				CoreUtils::checkStringValidity($comment, 'Comment', INVERSE_PRINTABLE_ASCII_PATTERN);

				PCGPointGrant::record($this->user->id, Auth::$user->id, $amount, $comment);

				$nPoints = CoreUtils::makePlural('point', abs($amount), PREPEND_NUMBER);
				$given = $amount > 0 ? 'given' : 'taken';
				$to = $amount > 0 ? 'to' : 'from';
				Response::success("You've successfully $given $nPoints $to {$this->user->name}");
			break;
			default:
				CoreUtils::notAllowed();
		}
	}
}
