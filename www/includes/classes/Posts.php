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

			$cols = 'id,season,episode,label,posted,preview,lock,deviation_id,reserved_by';
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

			if (!empty($_POST['label'])){
				$label = trim($_POST['label']);

				if (!$editing || $label !== $Post['label']){
					$labellen = strlen($label);
					if ($labellen < 3 || $labellen > 255)
						CoreUtils::Respond("The description must be between 3 and 255 characters in length");
					CoreUtils::CheckStringValidity($label,'The description',INVERSE_PRINTABLE_ASCII_REGEX);
					$array['label'] = $label;
				}
			}
			else if (!$editing && $thing !== 'reservation')
				CoreUtils::Respond('Description cannot be empty');

			if ($thing === 'request'){
				if (!empty($_POST['type'])){
					if (!in_array($_POST['type'],array('chr','obj','bg')))
						CoreUtils::Respond("Invalid request type");
				}
				else if (!$editing)
					respnd("Missing request type");

				if (!$editing || (!empty($_POST['type']) && $_POST['type'] !== $Post['type']))
					$array['type'] = $_POST['type'];

				if (!empty($_POST['reserved_at'])){
					if (!Permission::Sufficient('developer'))
						CoreUtils::Respond();

					$array['reserved_at'] = date('c', strtotime($_POST['reserved_at']));
				}
			}

			if (!empty($_POST['posted'])){
				if (!Permission::Sufficient('developer'))
					CoreUtils::Respond();

				$array['posted'] = date('c', strtotime($_POST['posted']));
			}
		}

		/**
		 * Check image URL in POST request
		 *
		 * @param array|null $Post Existing post for comparison
		 *
		 * @return ImageProvider
		 */
		static function CheckImage($Post = null){
			if (empty($_POST['image_url']))
				CoreUtils::Respond('Please enter an image URL');

			try {
				$Image = new ImageProvider($_POST['image_url']);
			}
			catch (Exception $e){ CoreUtils::Respond($e->getMessage()); }

			global $Database;
			foreach (Posts::$TYPES as $type){
				if (!empty($Post['id']))
					$Database->where('r.id',$Post['id'],'!=');
				$Database
					->join('episodes ep','r.season = ep.season && r.episode = ep.episode')
					->where('r.preview','','!=')
					->where('r.preview',$Image->preview)
					->getOne("{$type}s r",'ep.*, r.id');
				if (!empty($Used)){
					$EpID = Episode::FormatTitle($Used,AS_ARRAY,'id');
					CoreUtils::Respond("This exact image has already been used for a $type under <a href='/episode/$EpID#$type-{$Used['id']}' target='_blank'>$EpID</a>");
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
			if (!isset($_POST['deviation']))
				CoreUtils::Respond('Please specify a deviation URL');
			$deviation = $_POST['deviation'];
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
			$addRes = Permission::Sufficient('inspector') ? '<button id="add-reservation-btn" class="darkblue">Add a reservation</button>' :'';

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


		/**
		 * List ltem generator function for request & reservation generators
		 *
		 * @param array $R         Requests/Reservations array
		 * @param bool  $isRequest Is the array an array of requests
		 * @param bool  $view_only Only show the "View" button
		 *
		 * @return string
		 */
		static function GetLi($R, $isRequest = false, $view_only = false){
			$finished = !empty($R['deviation_id']);
			$ID = ($isRequest ? 'request' : 'reservation').'-'.$R['id'];
			$alt = !empty($R['label']) ? CoreUtils::AposEncode($R['label']) : '';
			$postlink = "/episode/S{$R['season']}E{$R['episode']}#$ID";
			$ImageLink = $view_only ? $postlink : $R['fullsize'];
			$Image = "<div class='image screencap'><a href='$ImageLink'><img src='{$R['preview']}' alt='$alt'></a></div>";
			$post_label = !empty($R['label']) ? '<span class="label">'.htmlspecialchars($R['label']).'</span>' : '';
			$permalink = "<a href='#$ID'>".Time::Tag($R['posted']).'</a>';

			$posted_at = '<em class="post-date">';
			if ($isRequest){
				global $signedIn, $currentUser;
				$sameUser = $signedIn && $R['requested_by'] === $currentUser['id'];

				$posted_at .= "Requested $permalink";
				if (Permission::Sufficient('inspector') || $sameUser)
					$posted_at .= ' by '.($sameUser ? 'You' : User::GetProfileLink(User::Get($R['requested_by'])));
			}
			else $posted_at .= "Reserved $permalink";
			$posted_at .= "</em>";

			$R['reserver'] = false;
			if (!empty($R['reserved_by'])){
				$R['reserver'] = User::Get($R['reserved_by']);
				$reserved_at = $isRequest && !empty($R['reserved_at']) ? "<em class='reserve-date'>Reserved <a href='#$ID'>".Time::Tag($R['reserved_at'])."</a></em>" : '';
				if ($finished){
					$Deviation = DeviantArt::GetCachedSubmission($R['deviation_id'],'fav.me',true);
					if (empty($Deviation)){
						$ImageLink = $view_only ? $postlink : "http://fav.me/{$R['deviation_id']}";
						$Image = "<div class='image deviation error'><a href='$ImageLink'>Preview unavailable<br><small>Click to view</small></a></div>";
					}
					else {
						$alt = CoreUtils::AposEncode($Deviation['title']);
						$ImageLink = $view_only ? $postlink : "http://fav.me/{$Deviation['id']}";
						$Image = "<div class='image deviation'><a href='$ImageLink'><img src='{$Deviation['preview']}' alt='$alt'>";
						if (!empty($R['lock']))
							$Image .= "<span class='typcn typcn-tick' title='This submission has been accepted into the group gallery'></span>";
						$Image .= "</a></div>";
					}
					if (Permission::Sufficient('inspector')){
						$Image .= $post_label.$posted_at.$reserved_at;
						if (!empty($R['fullsize']))
							$Image .= "<a href='{$R['fullsize']}' class='original' target='_blank'>Direct link to original image</a>";
					}
				}
				else $Image .= $post_label.$posted_at.$reserved_at;
			}
			else $Image .= $post_label.$posted_at;

			return "<li id='$ID'>$Image".self::_getPostActions($R['reserver'], $R, $isRequest, $view_only ? $postlink : false).'</li>';
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
			$CanEdit = (empty($R['lock']) && Permission::Sufficient('inspector')) || Permission::Sufficient('developer') || ($requestedByUser && $isNotReserved);
			$Buttons = array();

			if (is_array($R) && $isNotReserved) $HTML = Permission::Sufficient('member') && !$view_only ? "<button class='reserve-request typcn typcn-user-add'>Reserve</button>" : '';
			else {
				if (empty($By) || $By === true){
					if (!$signedIn) trigger_error('Trying to get reserver button while not signed in');
					$By = $currentUser;
				}
				$dAlink = User::GetProfileLink($By, FULL);

				$HTML =  "<div class='reserver'>$dAlink</div>";

				$finished = !empty($R['deviation_id']);
				$inspectorOrSameUser = ($sameUser && Permission::Sufficient('member')) || Permission::Sufficient('inspector');
				if (!$finished && $inspectorOrSameUser){
					$Buttons[] = array('user-delete red cancel', 'Cancel Reservation');
					$Buttons[] = array('attachment green finish', ($sameUser ? "I'm" : 'Mark as').' finished');
				}
				if ($finished && empty($R['lock'])){
					if (Permission::Sufficient('inspector'))
						$Buttons[] = array((empty($R['preview'])?'trash delete-only red':'media-eject orange').' unfinish',empty($R['preview'])?'Delete':'Un-finish');
					if ($inspectorOrSameUser)
						$Buttons[] = array('tick green check','Check');
				}
			}

			if (empty($R['lock']) && empty($Buttons) && (Permission::Sufficient('inspector') || ($requestedByUser && $isNotReserved)))
				$Buttons[] = array('trash red delete','Delete');
			if ($CanEdit)
				array_splice($Buttons,0,0,array(array('pencil darkblue edit','Edit')));

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
						$HTML .= "<button class='typcn typcn-{$b[0]}$WriteOut</button> ";
					}
				}
			}
			$HTML .= '</div>';

			return $HTML;
		}
	}
