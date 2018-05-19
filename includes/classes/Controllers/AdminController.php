<?php

namespace App\Controllers;
use App\Appearances;
use App\Auth;
use App\CoreUtils;
use App\CSRFProtection;
use App\DB;
use App\Input;
use App\JSON;
use App\Logs;
use App\Models\Appearance;
use App\Models\DiscordMember;
use App\Models\Logs\Log;
use App\Models\Notice;
use App\Models\UsefulLink;
use App\Models\User;
use App\Models\UserPref;
use App\Pagination;
use App\Permission;
use App\PostgresDbWrapper;
use App\Posts;
use App\RegExp;
use App\Response;
use App\UserPrefs;
use App\Users;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use IPTools\IP;
use RestCord\DiscordClient;

class AdminController extends Controller {
	public $do = 'admin';

	public function __construct(){
		parent::__construct();

		if (!Permission::sufficient('staff'))
			CoreUtils::noPerm();

		CSRFProtection::protect();
	}

	public function index(){
		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Admin Area',
			'css' => [true],
			'js' => [true],
		]);
	}

	public function log(){
		$type = Logs::validateRefType('type', true, true);
		/** @noinspection NotOptimalIfConditionsInspection */
		if (isset($_GET['type']) && preg_match(new RegExp('/^[a-z_]+$/'), $_GET['type']) && isset(Logs::$LOG_DESCRIPTION[$_GET['type']]))
			$type = $_GET['type'];

		$ip = null;
		if (!isset($_GET['by']))
			$by = null;
		else {
			$_GET['by'] = strtolower(CoreUtils::trim($_GET['by']));
			switch($_GET['by']){
				case 'me':
				case 'you':
					$initiator = Auth::$user->id;
					$by = 'you';
				break;
				case 'my ip':
				case 'your ip':
					$ip = $_SERVER['REMOTE_ADDR'];
				break;
				case 'web server':
					$initiator = 0;
					$by = 'Web server';
				break;
				default:
					$by = Users::validateName('by', null, true, true);
					if ($by !== null){
						$by = Users::get($by, 'name');
						if (!empty($by)){
							$initiator = $by->id;
							$by = $initiator === Auth::$user->id ? 'me' : $by->name;
						}
						else $by = null;
					}
					else if (!empty($_GET['by'])){
						try {
							$ip = IP::parse($_GET['by']);
						}
						catch (\Throwable $e){
							// If we don1t find a vaild IP adress then we just ignore the parameter
						}
						if ($ip !== null)
							$ip = (string)$ip;
					}
			}
		}

		$title = '';
		$whereArgs = [];
		$q = [];
		if ($type !== null){
			$whereArgs[] = ['reftype', $type];
			$q[] = "type=$type";
			$title .= Logs::$LOG_DESCRIPTION[$type].' entries ';
		}
		else if (isset($q))
			$q[] = 'type='.CoreUtils::FIXPATH_EMPTY;
		if (isset($initiator)){
			$_params = $initiator === 0 ? ['"initiator" IS NULL'] : ['initiator', $initiator];
			$whereArgs[] = $_params;
			if (isset($by)){
				$q[] = "by=$by";
				$title .= ($type === null?'Entries ':'')."by $by ";
			}
		}
		else if (isset($ip)){
			$whereArgs[] = ['ip', \in_array($ip, Logs::LOCALHOST_IPS, true) ? Logs::LOCALHOST_IPS : $ip];
			$q[] = "by=$ip";
			$title .= ($type === null?'Entries ':'')."from $ip ";
		}
		else $q[] = 'by='.CoreUtils::FIXPATH_EMPTY;

		foreach ($whereArgs as $arg)
			DB::$instance->where($arg[0], $arg[1] ?? \PostgresDb::DBNULL);
		$Pagination = new Pagination('/admin/logs', 25, DB::$instance->count('log'));
		$heading = 'Global logs';
		if (!empty($title))
			$title .= '- ';
		$title .= "Page {$Pagination->getPage()} - $heading - Admin Area";

		$path = $Pagination->toURI();
		if (!empty($q))
			foreach ($q as $item)
				$path->append_query_raw($item);
		CoreUtils::fixPath($path);

		foreach ($whereArgs as $arg)
			DB::$instance->where(...$arg);
		$LogItems = DB::$instance
			->setModel(Log::class)
			->orderBy('timestamp','DESC')
			->orderBy('entryid','DESC')
			->get('log', $Pagination->getLimit());

		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => $title,
			'css' => [true],
			'js' => [true, 'paginate'],
			'import' => [
				'Pagination' => $Pagination,
				'LogItems' => $LogItems,
				'type' => $type,
				'by' => $by ?? null,
				'ip' => $ip ?? null,
			],
		]);
	}

	public function logDetail($params){
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (!isset($params['id']) || !is_numeric($params['id']))
			Response::fail('Entry ID is missing or invalid');

		$entry = \intval($params['id'], 10);

		$MainEntry = DB::$instance->where('entryid', $entry)->getOne('log');
		if (empty($MainEntry))
			Response::fail('Log entry does not exist');
		if (empty($MainEntry['refid']))
			Response::fail('There are no details to show', ['unclickable' => true]);

		$Details = DB::$instance->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
		if (empty($Details)){
			CoreUtils::error_log("Could not find details for entry {$MainEntry['reftype']}#{$MainEntry['refid']}, NULL-ing refid of Main#{$MainEntry['entryid']}");
			DB::$instance->where('entryid', $MainEntry['entryid'])->update('log', ['refid' => null]);
			Response::fail('Failed to retrieve details', ['unclickable' => true]);
		}

		Response::done(Logs::formatEntryDetails($MainEntry,$Details));
	}

	/**
	 * @var null|UsefulLink
	 */
	private $usefulLink;
	private function load_useful_link($id){
			if (empty($id))
				CoreUtils::notFound();
			$linkid = \intval($id, 10);
			$this->usefulLink = UsefulLink::find($linkid);
			if (empty($this->usefulLink))
				Response::fail('The specified link does not exist');
	}

	public function usefulLinks(){
		$heading = 'Manage useful links';
		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => "$heading - Admin Area",
			'view' => [true],
			'js' => ['Sortable',true],
			'css' => [true],
		]);
	}

	public function usefulLinksApi($params){
		$action = $_SERVER['REQUEST_METHOD'];
		$creating = $action === 'POST';

		if (!$creating)
			$this->load_useful_link($params['id']);

		switch ($action){
			case 'GET':
				Response::done([
					'label' => $this->usefulLink->label,
					'url' => $this->usefulLink->url,
					'title' => $this->usefulLink->title,
					'minrole' => $this->usefulLink->minrole,
				]);
			case 'DELETE':
				if (!DB::$instance->where('id', $this->usefulLink->id)->delete('useful_links'))
					Response::dbError();

				Response::done();
			case 'POST':
			case 'PUT':
				$data = [];

				$label = (new Input('label','string', [
					Input::IN_RANGE => [3,35],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Link label is missing',
						Input::ERROR_RANGE => 'Link label must be between @min and @max characters long',
					]
				]))->out();
				if ($creating || $this->usefulLink->label !== $label){
					CoreUtils::checkStringValidity($label, 'Link label', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['label'] = $label;
				}

				$url = (new Input('url','url', [
					Input::IN_RANGE => [3,255],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Link URL is missing',
						Input::ERROR_RANGE => 'Link URL must be between @min and @max characters long',
					]
				]))->out();
				if ($creating || $this->usefulLink->url !== $url)
					$data['url'] = $url;

				$title = (new Input('title','string', [
					Input::IS_OPTIONAL => true,
					Input::IN_RANGE => [3,255],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_RANGE => 'Link title must be between @min and @max characters long',
					]
				]))->out();
				if (!isset($title))
					$data['title'] = '';
				else if ($creating || $this->usefulLink->title !== $title){
					CoreUtils::checkStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['title'] = $title;
				}

				$minrole = (new Input('minrole',function($value){
					if (empty(Permission::ROLES_ASSOC[$value]) || !Permission::sufficient('user', $value))
						Response::fail();
				}, [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Minimum role is missing',
						Input::ERROR_INVALID => 'Minimum role (@value) is invalid',
					]
				]))->out();
				if ($creating || $this->usefulLink->minrole !== $minrole)
					$data['minrole'] = $minrole;

				if (empty($data))
					Response::fail('Nothing was changed');
				$query = $creating
					? UsefulLink::create($data)
					: $this->usefulLink->update_attributes($data);
				if (!$query)
					Response::dbError();

				Response::done();
			break;
			default: CoreUtils::notAllowed();
		}
	}

	public function reorderUsefulLinks(){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		$list = (new Input('list','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
				Input::ERROR_MISSING => 'Missing ordering information',
			]
		]))->out();
		$order = 1;
		foreach ($list as $id){
			if (!UsefulLink::find($id)->update_attributes(['order' => $order++]))
				Response::fail("Updating link #$id failed, process halted");
		}

		Response::done();
	}

	public function massApprove(){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		$ids = (new Input('ids','int[]', [
			Input::CUSTOM_ERROR_MESSAGES => [
			    Input::ERROR_MISSING => 'List of deviation IDs is missing',
			    Input::ERROR_INVALID => 'List of deviation IDs (@value) is invalid',
			]
		]))->out();

		$list = '';
		foreach ($ids as $id)
			$list .= "'d".base_convert($id, 10, 36)."',";
		$list = rtrim($list, ',');

		$Posts = DB::$instance->query(
			"SELECT 'request' as type, id, deviation_id FROM requests WHERE deviation_id IN ($list) AND lock = false
			UNION ALL
			SELECT 'reservation' as type, id, deviation_id FROM reservations WHERE deviation_id IN ($list) AND lock = false"
		);

		if (empty($Posts))
			Response::success('There were no posts in need of marking as approved');

		$approved = 0;
		$notInCLub = 0;
		foreach ($Posts as $p){
			if (CoreUtils::isDeviationInClub($p['deviation_id']) !== true){
				$notInCLub++;
				continue;
			}

			Posts::approve($p['type'], $p['id']);
			$approved++;
		}

		if ($approved === 0){
			if ($notInCLub === 0)
				Response::success('All identified posts have already been approved');
			else Response::fail('None of the posts have been added to the gallery yet');
		}

		Response::success('Marked '.CoreUtils::makePlural('post', $approved, PREPEND_NUMBER).' as approved. To see which ones, check the <a href="/admin/logs/1?type=post_lock&by=you">list of posts you\'ve approved</a>.', ['html' => Posts::getMostRecentList(NOWRAP)]);
	}

	public function wsdiag(){
		if ($this->action !== 'POST')
			CoreUtils::notAllowed();

		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();

		$heading = 'WebSocket Server Diagnostics';
		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => "$heading - Admin Area",
			'js' => [true],
			'css' => [true],
			'import' => ['nav_wsdiag' => true],
		]);
	}

	public function wshello(){
		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();

		CoreUtils::detectUnexpectedJSON();

		$clientid = $_POST['clientid'] ?? null;
		if (!preg_match(new RegExp('^[a-zA-Z\d_-]+$'), $clientid))
			Response::fail('Invalid client ID');

		try {
			CoreUtils::socketEvent('hello', [
				'clientid' => $clientid,
				'priv' => $_POST['priv'],
			]);
		}
		catch (\Throwable $e){
			$errmsg = 'Failed to send hello: '.$e->getMessage();
			CoreUtils::error_log($errmsg."\n".$e->getTraceAsString());
			Response::fail($errmsg);
		}

		Response::done();
	}

	private function _setupPcgAppearances():\PostgresDb {
		return DB::$instance->where('owner_id IS NOT NULL');
	}

	public function pcgAppearances(){
		$Pagination = new Pagination('/admin/pcg-appearances', 10, $this->_setupPcgAppearances()->count(Appearance::$table_name));

		CoreUtils::fixPath($Pagination->toURI());
		$heading = 'All PCG Appearances';
		$title = "Page {$Pagination->getPage()} - $heading - Color Guide";

		$Appearances = $this->_setupPcgAppearances()->orderBy('added','DESC')->get(Appearance::$table_name, $Pagination->getLimit());

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => ['paginate'],
			'import' => [
				'Appearances' => $Appearances,
				'Pagination' => $Pagination,
			],
		]);
	}

	public function notices(){
		$ItemsPerPage = 25;
		$Pagination = new Pagination('/admin/notices', $ItemsPerPage, Notice::count());
		[$offset, $limit] = $Pagination->getLimit();

		$notices = Notice::find('all', [
			'limit' => $limit,
			'offset' => $offset,
		]);

		$heading = 'Manage notices';
		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => "Page {$Pagination->getPage()} - $heading - Admin Area",
			'view' => [true],
			#'js' => [true],
			'css' => [true],
			'import' => [
				'Pagination' => $Pagination,
				'notices' => $notices,
			],
		]);
	}
	/** @var Notice|null */
	private $_notice;
	private function load_notice($params){
		$this->_notice = Notice::find($params['id']);

		if (!$this->creating && empty($this->_notice))
			Response::fail('The specified notice does not exist');
	}
	public function noticesApi($params){
		# TODO Implement notice editing on the client side
		CoreUtils::notFound();

		$this->load_notice($params);

		switch ($this->action){
			case 'GET':
				Response::done($this->_notice->to_array());
			break;
			case 'POST':
			case 'PUT':
				if ($this->creating){
					$this->_notice = new Notice([
						'posted_by' => Auth::$user->id,
					]);
				}

				$message_html = (new Input('message_html','string', [
					Input::IN_RANGE => [null, 500],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Message is missing',
						Input::ERROR_INVALID => 'Message is invalid',
						Input::ERROR_RANGE => 'Message cannot be longer than @max chars',
					],
				]))->out();
				CoreUtils::checkStringValidity($message_html, INVERSE_PRINTABLE_ASCII_PATTERN, 'Message');
				$this->_notice->message_html = $message_html;

				$hide_after = (new Input('hide_after', 'timestamp', [
					Input::IN_RANGE => [time(), null],
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Hide after date is missing',
						Input::ERROR_INVALID => 'Hide after date is invalid',
						Input::ERROR_RANGE => 'Hide after date cannot be in the past',
					],
				]))->out();
				$this->_notice->hide_after = $hide_after;

				# TODO Validate notice type
				$this->_notice->type = (new Input('type','string'))->out();

				$this->_notice->save();
				Response::done(['notice' => $this->_notice->to_array()]);
			break;
			case 'DELETE':
				$this->_notice->delete();

				Response::done();
			break;
			default: CoreUtils::notAllowed();
		}
	}
}
