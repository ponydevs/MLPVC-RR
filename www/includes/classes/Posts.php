<?php

	class Posts {
		static
			$TYPES = array('request','reservation'),
			$REQUEST_TYPES = array(
				'chr' => 'Characters',
				'obj' => 'Objects',
				'bg' => 'Backgrounds',
			);

		/**
		 * Retrieves requests & reservations for the episode specified
		 *
		 * @param int $season
		 * @param int $episode
		 * @param bool $only
		 *
		 * @return array
		 */
		static function Get($season, $episode, $only = false){
			global $Database;

			$Query =
				'SELECT
					*,
					(CASE
						WHEN (r.deviation_id IS NOT NULL && r.reserved_by IS NOT NULL)
						THEN 1
						ELSE 0
					END) as finished
				FROM "coloumn" r
				WHERE season = ? && episode = ?
				ORDER BY finished, posted';

			$return = array();
			if ($only !== ONLY_RESERVATIONS) $return[] = $Database->rawQuery(str_ireplace('coloumn','requests',$Query),array($season, $episode));
			if ($only !== ONLY_REQUESTS) $return[] = $Database->rawQuery(str_ireplace('coloumn','reservations',$Query),array($season, $episode));

			if (!$only) return $return;
			else return $return[0];
		}

		/**
		 * Get list of most recent posts
		 *
		 * @param bool $wrap
		 *
		 * @return string
		 */
		static function GetMostRecentList($wrap = true){
			global $Database;

			$cols = 'id,season,episode,label,posted,preview,lock,deviation_id,reserved_by,finished_at';
			$RecentPosts = $Database->rawQuery(
				"SELECT * FROM
				(
					SELECT $cols, requested_by, true AS rq, reserved_at FROM requests
					WHERE posted > NOW() - INTERVAL '20 DAYS'
					UNION ALL
					SELECT $cols, null AS requested_by, false AS rq, null AS reserved_at FROM reservations
					WHERE posted > NOW() - INTERVAL '20 DAYS'
				) t
				ORDER BY posted DESC
				LIMIT 20");

			$HTML = $wrap ? '<ul>' : '';
			foreach ($RecentPosts as $p)
				$HTML .= self::GetLi($p, isset($p['requested_by']), true);
			return $HTML.($wrap?'</ul>':'');
		}


		/**
		 * Get link to a specific post
		 *
		 * @param array $Post
		 *
		 * @return array
		 */
		static function GetLink($Post){
			$thing = empty($Post['rq']) ? 'reservation' : 'request';
			$id = "$thing-{$Post['id']}";
			if ($Post['season'] !== 0){
				$page = "S{$Post['season']}E{$Post['episode']}";
				$link = "/episode/$page#$id";
			}
			else {
				$page = "EQG {$Post['episode']}";
				$link = "/eqg/{$Post['episode']}";
			}
			return array($link,$page);
		}

		/**
		 * POST data validator function used when creating/editing posts
		 *
		 * @param string $thing "request"/"reservation"
		 * @param array $array Array to output the checked data into
		 * @param array|null $Post Optional, exsting post to compare new data against
		 */
		static function CheckPostDetails($thing, &$array, $Post = null){
			$editing = !empty($Post);

			$label = (new Input('label','string',array(
				Input::IS_OPTIONAL => true,
				Input::IN_RANGE => [3,255],
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_RANGE => 'The description must be between @min and @max characters'
				)
			)))->out();
			if (isset($label)){
				if (!$editing || $label !== $Post['label']){
					CoreUtils::CheckStringValidity($label,'The description',INVERSE_PRINTABLE_ASCII_PATTERN);
					$array['label'] = $label;
				}
			}
			else if (!$editing && $thing !== 'reservation')
				CoreUtils::Respond('Description cannot be empty');

			if ($thing === 'request'){
				$type = (new Input('type',function($value){
					if (!in_array($value,array('chr','obj','bg')))
						return Input::ERROR_INVALID;
				},array(
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_INVALID => "Request type (@value) is invalid"
					)
				)))->out();
				if (isset($type)){

				}
				else if (!$editing)
					respnd("Missing request type");

				if (!$editing || (isset($type) && $type !== $Post['type']))
					$array['type'] = $type;

				if (Permission::Sufficient('developer')){
					$reserved_at = self::ValidateReservedAt();
					if (isset($reserved_at)){
						if ($reserved_at !== strtotime($Post['reserved_at']))
							$array['reserved_at'] = date('c', $reserved_at);
					}
					else $array['reserved_at'] = null;
				}
			}

			if (Permission::Sufficient('developer')){
				$posted = (new Input('posted','timestamp',array(
					Input::IS_OPTIONAL => true,
					Input::CUSTOM_ERROR_MESSAGES => array(
						Input::ERROR_INVALID => '"Posted" timestamp (@value) is invalid',
					)
				)))->out();
				if (isset($posted) && $posted !== strtotime($Post['posted']))
					$array['posted'] = date('c', $posted);

				$finished_at = self::ValidateFinishedAt();
				if (isset($finished_at)){
					if ($finished_at !== strtotime($Post['finished_at']))
						$array['finished_at'] = date('c', $finished_at);
				}
				else $array['finished_at'] = null;
			}
		}

		/**
		 * Check image URL in POST request
		 *
		 * @param string $image_url
		 * @param array|null $Post Existing post for comparison
		 *
		 * @return ImageProvider
		 */
		static function CheckImage($image_url, $Post = null){
			try {
				$Image = new ImageProvider($image_url);
			}
			catch (Exception $e){ CoreUtils::Respond($e->getMessage()); }

			global $Database;
			foreach (Posts::$TYPES as $type){
				if (!empty($Post['id']))
					$Database->where('r.id',$Post['id'],'!=');
				$UsedUnder = $Database
					->join('episodes ep','r.season = ep.season && r.episode = ep.episode','LEFT')
					->where('r.preview',$Image->preview)
					->getOne("{$type}s r",'ep.*, r.id as post_id');
				if (!empty($UsedUnder)){
					$EpID = Episode::FormatTitle($UsedUnder,AS_ARRAY,'id');
					CoreUtils::Respond("This exact image has already been used for a $type under <a href='/episode/$EpID#$type-{$UsedUnder['post_id']}' target='_blank'>$EpID</a>");
				}
			}

			return $Image;
		}

		/**
		 * Checks the image which allows a request to be finished
		 *
		 * @param string|null $ReserverID
		 *
		 * @return array
		 */
		static function CheckRequestFinishingImage($ReserverID = null){
			global $Database;
			$deviation = (new Input('deviation','string',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Please specify a deviation URL',
				)
			)))->out();
			try {
				$Image = new ImageProvider($deviation, array('fav.me', 'dA'));

				foreach (Posts::$TYPES as $what){
					if ($Database->where('deviation_id', $Image->id)->has("{$what}s"))
						CoreUtils::Respond("This exact deviation has already been marked as the finished version of a different $what");
				}

				$return = array('deviation_id' => $Image->id);
				$Deviation = DeviantArt::GetCachedSubmission($Image->id);
				if (!empty($Deviation['author'])){
					$Author = User::Get($Deviation['author'], 'name');

					if (!empty($Author)){
						if (!isset($_POST['allow_overwrite_reserver']) && !empty($ReserverID) && $Author['id'] !== $ReserverID){
							global $currentUser;
							$sameUser = $currentUser['id'] === $ReserverID;
							$person = $sameUser ? 'you' : 'the user who reserved this post';
							CoreUtils::Respond("You've linked to an image which was not submitted by $person. If this was intentional, press Continue to proceed with marking the post finished <b>but</b> note that it will make {$Author['name']} the new reserver.".($sameUser
									? "<br><br>This means that you'll no longer be able to interact with this post until {$Author['name']} or an administrator cancels the reservation on it."
									: ''), 0, array('retry' => true));
						}

						$return['reserved_by'] = $Author['id'];
					}
				}

				if (CoreUtils::IsDeviationInClub($return['deviation_id']) === true)
					$return['lock'] = true;

				return $return;
			}
			catch (MismatchedProviderException $e){
				CoreUtils::Respond('The finished vector must be uploaded to DeviantArt, '.$e->getActualProvider().' links are not allowed');
			}
			catch (Exception $e){ CoreUtils::Respond($e->getMessage()); }
		}

		/**
		 * Generate HTML of requests for episode pages
		 *
		 * @param array $Requests
		 * @param bool  $returnArranged Return an arranged array of posts instead of raw HTML
		 *
		 * @return string|array
		 */
		static function GetRequestsSection($Requests, $returnArranged = false){
			$Arranged = array('finished' => !$returnArranged ? '' : array());
			if (!$returnArranged){
				$Arranged['unfinished'] = array();
				$Arranged['unfinished']['bg'] =
				$Arranged['unfinished']['obj'] =
				$Arranged['unfinished']['chr'] = $Arranged['finished'];
			}
			else $Arranged['unfinished'] = $Arranged['finished'];
			if (!empty($Requests) && is_array($Requests)){
				foreach ($Requests as $R){
					$HTML = !$returnArranged ? self::GetLi($R, IS_REQUEST) : $R;

					if (!$returnArranged){
						if (!empty($R['finished']))
							$Arranged['finished'] .= $HTML;
						else $Arranged['unfinished'][$R['type']] .= $HTML;
					}
					else {
						$k = (empty($R['finished'])?'un':'').'finished';
						$Arranged[$k][] = $HTML;
					}
				}
			}
			if ($returnArranged) return $Arranged;

			$Groups = '';
			foreach ($Arranged['unfinished'] as $g => $c)
				$Groups .= "<div class='group' id='group-$g'><h3>".self::$REQUEST_TYPES[$g].":</h3><ul>$c</ul></div>";

			if (Permission::Sufficient('user')){
				$makeRq = '<button id="request-btn" class="green">Make a request</button>';
				$reqForm = self::_getForm('request');
			}
			else $reqForm = $makeRq = '';

			return <<<HTML
	<section id="requests">
		<div class="unfinished">
			<h2>List of Requests$makeRq</h2>
			$Groups
		</div>
		<div class="finished">
			<h2>Finished Requests</h2>
			<ul>{$Arranged['finished']}</ul>
		</div>$reqForm
	</section>
HTML;
		}


		/**
		 * Generate HTML of reservations for episode pages
		 *
		 * @param array $Reservations
		 * @param bool  $returnArranged Return an arranged array of posts instead of raw HTML
		 *
		 * @return string|array
		 */
		static function GetReservationsSection($Reservations, $returnArranged = false){
			$Arranged = array();
			$Arranged['unfinished'] =
			$Arranged['finished'] = !$returnArranged ? '' : array();

			if (!empty($Reservations) && is_array($Reservations)){
				foreach ($Reservations as $R){
					$k = (empty($R['finished'])?'un':'').'finished';
					if (!$returnArranged)
						$Arranged[$k] .= self::GetLi($R);
					else $Arranged[$k][] = $R;
				}
			}

			if ($returnArranged) return $Arranged;

			if (Permission::Sufficient('member')){
				$makeRes = '<button id="reservation-btn" class="green">Make a reservation</button>';
				$resForm = self::_getForm('reservation');

			}
			else $resForm = $makeRes = '';
			$addRes = Permission::Sufficient('staff') ? '<button id="add-reservation-btn" class="darkblue">Add a reservation</button>' :'';

			return <<<HTML
	<section id="reservations">
		<div class="unfinished">
			<h2>List of Reservations$makeRes</h2>
			<ul>{$Arranged['unfinished']}</ul>
		</div>
		<div class="finished">
			<h2>Finished Reservations$addRes</h2>
			<ul>{$Arranged['finished']}</ul>
		</div>$resForm
	</section>

HTML;
		}

		/**
		 * Get Request / Reservation Submission Form HTML
		 *
		 * @param string $type
		 *
		 * @return string
		 */
		private static function _getForm($type){
			global $currentUser;

			$Type = strtoupper($type[0]).substr($type,1);
			$optional = $type === 'reservation' ? 'optional, ' : '';
			$optreq = $type === 'reservation' ? '' : 'required';

			$HTML = <<<HTML

		<form class="hidden post-form" data-type="$type">
			<h2>Make a $type</h2>
			<div>
				<label>
					<span>$Type description ({$optional}3-255 chars)</span>
					<input type="text" name="label" pattern="^.{3,255}$" maxlength="255" $optreq>
				</label>
				<label>
					<span>Image URL</span>
					<input type="text" name="image_url" pattern="^.{2,255}$" required>&nbsp;
					<button type="button" class="check-img red typcn typcn-arrow-repeat">Check image</button><br>
				</label>
				<div class="img-preview">
					<div class="notice info">
						<p>Please click the <strong>Check image</strong> button after providing an URL to get a preview & verify if the link is correct.</p>
						<hr>
						<p class="keep">You can use a link from any of the following providers: <a href="http://sta.sh/" target="_blank">Sta.sh</a>, <a href="http://deviantart.com/" target="_blank">DeviantArt</a>, <a href="http://imgur.com/" target="_blank">Imgur</a>, <a href="http://derpibooru.org/" target="_blank">Derpibooru</a>, <a href="http://puush.me/" target="_blank">Puush</a>, <a href="http://app.prntscr.com/" target="_blank">LightShot</a></p>
					</div>
				</div>

HTML;
			if ($type === 'request')
				$HTML .= <<<HTML
				<label>
					<span>$Type type</span>
					<select name="type" required>
						<option value="" style="display:none" selected>Choose one</option>
						<optgroup label="$Type types">
							<option value="chr">Character</option>
							<option value="bg">Background</option>
							<option value="obj">Object</option>
						</optgroup>
					</select>
				</label>

HTML;
			if (Permission::Sufficient('developer')){
				$UNP = USERNAME_PATTERN;
				$HTML .= <<<HTML
				<label>
					<span>$Type as user</span>
					<input type="text" name="post_as" pattern="^$UNP$" maxlength="20" placeholder="Username" spellcheck="false">
				</label>

HTML;
			}

			$HTML .= <<<HTML
			</div>
			<button class="green">Submit $type</button> <button type="reset">Cancel</button>
		</form>
HTML;
			return $HTML;
		}

		static function IsOverdue($Post){
			return isset($Post['requested_by']) && isset($Post['reserved_by']) && time() - strtotime($Post['reserved_at']) >= Time::$IN_SECONDS['week']*3;
		}

		/**
		 * List ltem generator function for request & reservation generators
		 *
		 * @param array $R             Requests/Reservations array
		 * @param bool  $isRequest     Is the array an array of requests
		 * @param bool  $view_only     Only show the "View" button
		 * @param bool  $cachebust_url Append a random string to the image URL to force a re-fetch
		 *
		 * @return string
		 */
		static function GetLi($R, $isRequest = false, $view_only = false, $cachebust_url = false){
			$finished = !empty($R['deviation_id']);
			$type = $isRequest ? 'request' : 'reservation';
			$ID = "$type-{$R['id']}";
			$alt = !empty($R['label']) ? CoreUtils::AposEncode($R['label']) : '';
			$postlink = "/episode/S{$R['season']}E{$R['episode']}#$ID";
			$ImageLink = $view_only ? $postlink : $R['fullsize'];
			$cachebust = $cachebust_url ? '?t='.time() : '';
			$Image = "<div class='image screencap'><a href='$ImageLink'><img src='{$R['preview']}$cachebust' alt='$alt'></a></div>";
			$post_label = !empty($R['label']) ? '<span class="label'.(strpos($R['label'],'"') !== false?' noquotes':'').'">'.self::ProcessLabel($R['label']).'</span>' : '';
			$permalink = "<a href='#$ID'>".Time::Tag($R['posted']).'</a>';

			$posted_at = '<em class="post-date">';
			if ($isRequest){
				global $signedIn, $currentUser;
				$isRequester = $R['requested_by'] === $currentUser['id'];
				$isReserver = $R['reserved_by'] === $currentUser['id'];

				if (!$finished && !empty($R['reserved_by']) && Permission::Sufficient('member') && self::IsOverdue($R)){
					if (Permission::Sufficient('staff') || $isReserver)
						$overdue = true;
					else if (!$isReserver)
						$R['reserved_by'] = null;
				}

				$posted_at .= "Requested $permalink";
				if ($signedIn && (Permission::Sufficient('staff') || $isRequester || $isReserver))
					$posted_at .= ' by '.($isRequester ? 'You' : User::GetProfileLink(User::Get($R['requested_by'])));
			}
			else $posted_at .= "Reserved $permalink";
			$posted_at .= "</em>";

			$R['reserver'] = false;
			if (!empty($R['reserved_by'])){
				$R['reserver'] = User::Get($R['reserved_by']);
				$reserved_at = $isRequest && !empty($R['reserved_at']) ? "<em class='reserve-date'>Reserved <strong>".Time::Tag($R['reserved_at'])."</strong></em>" : '';
				if ($finished){
					$approved = !empty($R['lock']);
					$Deviation = DeviantArt::GetCachedSubmission($R['deviation_id'],'fav.me',true);
					if (empty($Deviation)){
						$ImageLink = $view_only ? $postlink : "http://fav.me/{$R['deviation_id']}";
						$Image = "<div class='image deviation error'><a href='$ImageLink'>Preview unavailable<br><small>Click to view</small></a></div>";
					}
					else {
						$alt = CoreUtils::AposEncode($Deviation['title']);
						$ImageLink = $view_only ? $postlink : "http://fav.me/{$Deviation['id']}";
						$Image = "<div class='image deviation'><a href='$ImageLink'><img src='{$Deviation['preview']}$cachebust' alt='$alt'>";
						if ($approved)
							$Image .= "<span class='typcn typcn-tick' title='This submission has been accepted into the group gallery'></span>";
						$Image .= "</a></div>";
					}
					if (Permission::Sufficient('staff')){
						$finished_at = !empty($R['finished_at']) ? "<em class='finish-date'>Finished <strong>".Time::Tag($R['finished_at'])."</strong></em>" : '';
						$locked_at = '';
						if ($approved){
							global $Database;

							$LogEntry = $Database->rawQuerySingle(
								"SELECT l.timestamp
								FROM log__post_lock pl
								LEFT JOIN log l ON l.reftype = 'post_lock' && l.refid = pl.entryid
								WHERE type = ? && id = ?
								ORDER BY pl.entryid ASC
								LIMIT 1", array($type, $R['id'])
							);
							$locked_at = $approved ? "<em class='approve-date'>Approved <strong>".Time::Tag(strtotime($LogEntry['timestamp']))."</strong></em>" : '';
						}
						$Image .= $post_label.$posted_at.$reserved_at.$finished_at.$locked_at;
						if (!empty($R['fullsize']))
							$Image .= "<a href='{$R['fullsize']}' class='original color-green' target='_blank'><span class='typcn typcn-link'></span> Original image</a>";
					}
				}
				else $Image .= $post_label.$posted_at.$reserved_at;
			}
			else $Image .= $post_label.$posted_at;

			if (!empty($overdue))
				$Image .= "<strong class='color-blue contest-note' title=\"Because this request was reserved more than 3 weeks ago it's now available for other members to reserve\"><span class='typcn typcn-info-large'></span> Can be contested</strong>";

			return "<li id='$ID'>$Image".self::_getPostActions($R['reserver'], $R, $isRequest, $view_only ? $postlink : false).'</li>';
		}

		static function ProcessLabel($label){
			return preg_replace(new RegExp('(?:(f)ull[-\s](b)od(?:y|ied)(\sversion)?)','i'),'<strong class="color-darkblue">$1ull $2ody$3</strong>', htmlspecialchars($label));
		}

		/**
		 * Generate HTML for post action buttons
		 *
		 * @param array      $By
		 * @param array|bool $R
		 * @param bool       $isRequest
		 * @param bool       $view_only Only show the "View" button
		 *
		 * @return string
		 */
		private static function _getPostActions($By = null, $R = false, $isRequest = false, $view_only = false){
			global $signedIn, $currentUser;

			$sameUser = $signedIn && $By['id'] === $currentUser['id'];
			$requestedByUser = $isRequest && $signedIn && $R['requested_by'] === $currentUser['id'];
			$isNotReserved = empty($R['reserved_by']);
			$CanEdit = (empty($R['lock']) && Permission::Sufficient('staff')) || Permission::Sufficient('developer') || ($requestedByUser && $isNotReserved);
			$Buttons = array();

			if (is_array($R) && $isNotReserved) $HTML = Permission::Sufficient('member') && !$view_only ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';
			else {
				if (empty($By) || $By === true){
					if (!$signedIn) trigger_error('Trying to get reserver button while not signed in');
					$By = $currentUser;
				}
				$dAlink = User::GetProfileLink($By, FULL);
				$vectorapp = User::GetVectorAppClassName($By);
				if (!empty($vectorapp))
					$vectorapp .= "' title='Uses ".CoreUtils::$VECTOR_APPS[CoreUtils::Substring($vectorapp,5)]." to make vectors";
				$HTML =  "<div class='reserver$vectorapp'>$dAlink</div>";

				$finished = !empty($R['deviation_id']);
				$staffOrSameUser = ($sameUser && Permission::Sufficient('member')) || Permission::Sufficient('staff');
				if (!$finished && $staffOrSameUser){
					$Buttons[] = array('user-delete red cancel', 'Cancel Reservation');
					$Buttons[] = array('attachment green finish', ($sameUser ? "I'm" : 'Mark as').' finished');
				}
				if ($finished && empty($R['lock'])){
					if (Permission::Sufficient('staff'))
						$Buttons[] = array((empty($R['preview'])?'trash delete-only red':'media-eject orange').' unfinish',empty($R['preview'])?'Delete':'Unfinish');
					if ($staffOrSameUser)
						$Buttons[] = array('tick green check','Check');
				}
			}

			if (empty($R['lock']) && empty($Buttons) && (Permission::Sufficient('staff') || ($requestedByUser && $isNotReserved)))
				$Buttons[] = array('trash red delete','Delete');
			if ($CanEdit)
				array_splice($Buttons,0,0,array(array('pencil darkblue edit','Edit')));
			if ($R['lock'] && Permission::Sufficient('staff'))
				$Buttons[] = array('lock-open orange unlock','Unlock');

			$HTML .= "<div class='actions'>";
			if (!$view_only)
				$Buttons[] = array('export blue share', 'Share');
			if (!empty($Buttons)){
				if ($view_only)
					$HTML .="<div><a href='$view_only' class='btn blue typcn typcn-arrow-forward'>View</a></div>";
				else {
					$regularButton = count($Buttons) <3;
					foreach ($Buttons as $b){
						$WriteOut = "'".($regularButton ? ">{$b[1]}" : " title='".CoreUtils::AposEncode($b[1])."'>");
						$HTML .= "<button class='typcn typcn-{$b[0]}$WriteOut</button>";
					}
				}
			}
			$HTML .= '</div>';

			return $HTML;
		}

		/**
		 * Approves a specific post and optionally notifies it's author
		 *
		 * @param string $type         request/reservation
		 * @param int    $id           post id
		 * @param string $notifyUserID id of user to notify
		 *
		 * @return array
		 */
		static function Approve($type, $id, $notifyUserID = null){
			global $Database;
			if (!$Database->where('id', $id)->update("{$type}s", array('lock' => true)))
				CoreUtils::Respond(ERR_DB_FAIL);

			$postdata = array(
				'type' => $type,
				'id' => $id
			);
			Log::Action('post_lock',$postdata);
			if (!empty($notifyUserID))
				Notifications::Send($notifyUserID, 'post-approved', $postdata);

			return $postdata;
		}

		static function ValidateImageURL(){
			return (new Input('image_url','string',array(
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_MISSING => 'Please provide an image URL.',
				)
			)))->out();
		}

		static function ValidatePostAs(){
			return User::ValidateName('post_as', array(
				Input::ERROR_INVALID => '"Post as" username (@value) is invalid',
			), true);
		}

		static function ValidateReservedAt(){
			return (new Input('reserved_at','timestamp',array(
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_INVALID => '"Reserved at" timestamp (@value) is invalid',
				)
			)))->out();
		}

		static function ValidateFinishedAt(){
			return (new Input('finished_at','timestamp',array(
				Input::IS_OPTIONAL => true,
				Input::CUSTOM_ERROR_MESSAGES => array(
					Input::ERROR_INVALID => '"Finished at" timestamp (@value) is invalid',
				)
			)))->out();
		}
	}
