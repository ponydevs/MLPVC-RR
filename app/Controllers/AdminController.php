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
use App\Models\Post;
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

		if (Permission::insufficient('staff'))
			CoreUtils::noPerm();
	}

	public function index(){
		if (Permission::sufficient('developer')){
			try {
				$client = CoreUtils::elasticClient();
				$client->ping();
				$indices = $client->cat()->indices(['v' => true]);
				$nodes = $client->cat()->nodes(['v' => true]);

				$usedIndexes = ['appearances'];
				$index_list = '';
				foreach ($indices as $no => $index){
					if (!\in_array($index['index'], $usedIndexes, true))
						continue;

					$index_list .= "#$no ";
					foreach ($index as $key => $value){
						if (empty($value))
							continue;
						$index_list .= "$key:$value ";
					}
					$index_list .= "\n";
				}
				$node_list = '';
				foreach ($nodes as $no => $node){
					$node_list .= "#$no ";
					foreach ($node as $key => $value){
						if (empty($value))
							continue;
						$node_list .= "$key:$value ";
					}
				}
				$elastic_down = false;
			}
			catch (\Elasticsearch\Common\Exceptions\NoNodesAvailableException $e){
				$elastic_down = true;
			}
		}

		CoreUtils::loadPage(__METHOD__, [
			'title' => 'Admin Area',
			'css' => [true],
			'js' => [true],
			'import' => [
				'elastic_down' => $elastic_down ?? true,
				'index_list' => $index_list ?? null,
				'node_list' => $node_list ?? null,
				'recent_posts' => Posts::getRecentPosts(),
			],
		]);
	}

	public function log(){
		$type = Logs::validateRefType('type', true, true);
		/** @noinspection NotOptimalIfConditionsInspection */
		if (isset($_GET['type']) && preg_match(new RegExp('/^[a-z_]+$/'), $_GET['type']) && isset(Logs::LOG_DESCRIPTION[$_GET['type']]))
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
							// If we don't find a valid IP address then we just ignore the parameter
						}
						if ($ip !== null)
							$ip = (string)$ip;
					}
			}
		}

		$title = '';
		$where_args = [];
		$q = [];
		$remove_params = [];
		if ($type !== null){
			$where_args[] = ['reftype', $type];
			$q[] = "type=$type";
			$title .= Logs::LOG_DESCRIPTION[$type].' entries ';
		}
		else if (isset($q))
			$remove_params[] = 'type';
		if (isset($initiator)){
			$_params = $initiator === 0 ? ['"initiator" IS NULL'] : ['initiator', $initiator];
			$where_args[] = $_params;
			if (isset($by)){
				$q[] = "by=$by";
				$title .= ($type === null?'Entries ':'')."by $by ";
			}
		}
		else if (isset($ip)){
			$where_args[] = ['ip', \in_array($ip, Logs::LOCALHOST_IPS, true) ? Logs::LOCALHOST_IPS : $ip];
			$q[] = "by=$ip";
			$title .= ($type === null?'Entries ':'')."from $ip ";
		}
		else $remove_params[] = 'by';

		foreach ($where_args as $arg)
			DB::$instance->where($arg[0], $arg[1] ?? \SeinopSys\PostgresDb::DBNULL);
		$pagination = new Pagination('/admin/logs', 25, DB::$instance->count('log'));
		$heading = 'Global logs';
		if (!empty($title))
			$title .= '- ';
		$title .= "Page {$pagination->getPage()} - $heading - Admin Area";

		$path = $pagination->toURI();
		if (!empty($q))
			foreach ($q as $item)
				$path->append_query_raw($item);
		CoreUtils::fixPath($path, $remove_params);

		foreach ($where_args as $arg)
			DB::$instance->where(...$arg);
		$log_items = DB::$instance
			->setModel(Log::class)
			->orderBy('timestamp','DESC')
			->orderBy('entryid','DESC')
			->get('log', $pagination->getLimit());

		$entry_types = Logs::LOG_DESCRIPTION;
		asort($entry_types);
		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => $title,
			'css' => [true],
			'js' => [true, 'paginate'],
			'import' => [
				'pagination' => $pagination,
				'log_items' => $log_items,
				'type' => $type,
				'by' => $by ?? null,
				'ip' => $ip ?? null,
				'entry_types' => $entry_types,
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
	private function load_useful_link($params){
		if (empty($params['id']))
			CoreUtils::notFound();
		$linkid = \intval($params['id'], 10);
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
		if (!$this->creating)
			$this->load_useful_link($params);

		switch ($this->action){
			case 'GET':
				Response::done([
					'label' => $this->usefulLink->label,
					'url' => $this->usefulLink->url,
					'title' => $this->usefulLink->title,
					'minrole' => $this->usefulLink->minrole,
				]);
			break;
			case 'DELETE':
				if (!DB::$instance->where('id', $this->usefulLink->id)->delete('useful_links'))
					Response::dbError();

				Response::done();
			break;
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
				if ($this->creating || $this->usefulLink->label !== $label){
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
				if ($this->creating || $this->usefulLink->url !== $url)
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
				else if ($this->creating || $this->usefulLink->title !== $title){
					CoreUtils::checkStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_PATTERN);
					$data['title'] = $title;
				}

				$minrole = (new Input('minrole',function($value){
					if (empty(Permission::ROLES_ASSOC[$value]) || Permission::insufficient('user', $value))
						Response::fail();
				}, [
					Input::CUSTOM_ERROR_MESSAGES => [
						Input::ERROR_MISSING => 'Minimum role is missing',
						Input::ERROR_INVALID => 'Minimum role (@value) is invalid',
					]
				]))->out();
				if ($this->creating || $this->usefulLink->minrole !== $minrole)
					$data['minrole'] = $minrole;

				if (empty($data))
					Response::fail('Nothing was changed');
				$query = $this->creating
					? UsefulLink::create($data)
					: $this->usefulLink->update_attributes($data);
				if (!$query)
					Response::dbError();

				Response::done();
			break;
			default:
				CoreUtils::notAllowed();
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

		$list = [];
		foreach ($ids as $id)
			$list[] = 'd'.base_convert($id, 10, 36);

		/** @var $Posts Post[] */
		$Posts = DB::$instance->where('deviation_id', $list)->where('lock', false)->get('posts');

		if (empty($Posts))
			Response::success('There were no posts in need of marking as approved');

		$approved = 0;
		$notInCLub = 0;
		foreach ($Posts as $p){
			if (CoreUtils::isDeviationInClub($p->deviation_id) !== true){
				$notInCLub++;
				continue;
			}

			$p->approve();
			$approved++;
		}

		if ($approved === 0){
			if ($notInCLub === 0)
				Response::success('All identified posts have already been approved');
			else Response::fail('None of the posts have been added to the gallery yet');
		}

		Response::success('Marked '.CoreUtils::makePlural('post', $approved, PREPEND_NUMBER).' as approved. To see which ones, check the <a href="/admin/logs?type=post_lock&by=you">list of posts you\'ve approved</a>.', ['html' => Posts::getMostRecentList(NOWRAP)]);
	}

	public function wsdiag(){
		if ($this->action !== 'GET')
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
		if ($this->action !== 'GET')
			CoreUtils::notAllowed();

		if (Permission::insufficient('developer'))
			CoreUtils::noPerm();

		CoreUtils::detectUnexpectedJSON();

		$clientid = $_REQUEST['clientid'] ?? null;
		if (!preg_match(new RegExp('^[a-zA-Z\d_-]+$'), $clientid))
			Response::fail('Invalid client ID');

		try {
			CoreUtils::socketEvent('hello', [
				'clientid' => $clientid,
				'priv' => $_REQUEST['priv'],
			]);
		}
		catch (\Throwable $e){
			$errmsg = 'Failed to send hello: '.$e->getMessage();
			CoreUtils::error_log($errmsg."\n".$e->getTraceAsString());
			Response::fail($errmsg);
		}

		Response::done();
	}

	private function _setupPcgAppearances():\SeinopSys\PostgresDb {
		return DB::$instance->where('owner_id IS NOT NULL');
	}

	public function pcgAppearances(){
		$pagination = new Pagination('/admin/pcg-appearances', 10, $this->_setupPcgAppearances()->count(Appearance::$table_name));

		CoreUtils::fixPath($pagination->toURI());
		$heading = 'All PCG appearances';
		$title = "Page {$pagination->getPage()} - $heading - Color Guide";

		$appearances = $this->_setupPcgAppearances()->orderBy('added','DESC')->get(Appearance::$table_name, $pagination->getLimit());

		CoreUtils::loadPage(__METHOD__, [
			'title' => $title,
			'heading' => $heading,
			'css' => [true],
			'js' => ['paginate'],
			'import' => [
				'appearances' => $appearances,
				'pagination' => $pagination,
			],
		]);
	}

	public function notices(){
		$ItemsPerPage = 25;
		$pagination = new Pagination('/admin/notices', $ItemsPerPage, Notice::count());
		[$offset, $limit] = $pagination->getLimit();

		$notices = Notice::find('all', [
			'limit' => $limit,
			'offset' => $offset,
		]);

		$heading = 'Manage notices';
		CoreUtils::loadPage(__METHOD__, [
			'heading' => $heading,
			'title' => "Page {$pagination->getPage()} - $heading - Admin Area",
			'view' => [true],
			#'js' => [true],
			'css' => [true],
			'import' => [
				'pagination' => $pagination,
				'notices' => $notices,
			],
		]);
	}
	/** @var Notice|null */
	private $_notice;
	private function load_notice($params){
		if (empty($params['id']))
			CoreUtils::notFound();
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
			default:
				CoreUtils::notAllowed();
		}
	}
}
