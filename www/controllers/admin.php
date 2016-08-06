<?php

	if (!Permission::Sufficient('staff'))
		CoreUtils::NotFound();

	$task = strtok($data, '/');
	$data = regex_replace(new RegExp('^[^/]*?(?:/(.*))?$'), '$1', $data);

	if (POST_REQUEST){
		switch ($task){
			case "logs":
				if (regex_match(new RegExp('^details/(\d+)'), $data, $_match)){
					$EntryID = intval($_match[1], 10);

					$MainEntry = $Database->where('entryid', $EntryID)->getOne('log');
					if (empty($MainEntry)) Response::Fail('Log entry does not exist');
					if (empty($MainEntry['refid'])) Response::Fail('There are no details to show');

					$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
					if (empty($Details)) Response::Fail('Failed to retrieve details');

					Response::Done(Log::FormatEntryDetails($MainEntry,$Details));
				}
				else CoreUtils::NotFound();
			break;
			case "usefullinks":
				if (regex_match(new RegExp('^([gs]et|del|make)(?:/(\d+))?$'), $data, $_match)){
					$action = $_match[1];
					$creating = $action === 'make';

					if (!$creating){
						$Link = $Database->where('id', $_match[2])->getOne('usefullinks');
						if (empty($Link))
							Response::Fail('The specified link does not exist');
					}

					switch ($action){
						case 'get':
							Response::Done(array(
								'label' => $Link['label'],
								'url' => $Link['url'],
								'title' => $Link['title'],
								'minrole' => $Link['minrole'],
							));
						case 'del':
							if (!$Database->where('id', $Link['id'])->delete('usefullinks'))
								Response::DBError();

							Response::Done();
						break;
						case 'make':
						case 'set':
							$data = array();

							$label = (new Input('label','string',array(
								Input::IN_RANGE => [3,35],
								Input::CUSTOM_ERROR_MESSAGES => array(
									Input::ERROR_MISSING => 'Link label is missing',
									Input::ERROR_RANGE => 'Link label must be between @min and @max characters long',
								)
							)))->out();
							if ($creating || $Link['label'] !== $label){
								CoreUtils::CheckStringValidity($label, 'Link label', INVERSE_PRINTABLE_ASCII_PATTERN);
								$data['label'] = $label;
							}

							$url = (new Input('url','url',array(
								Input::IN_RANGE => [3,255],
								Input::CUSTOM_ERROR_MESSAGES => array(
									Input::ERROR_MISSING => 'Link URL is missing',
									Input::ERROR_RANGE => 'Link URL must be between @min and @max characters long',
								)
							)))->out();
							if ($creating || $Link['url'] !== $url)
								$data['url'] = $url;

							$title = (new Input('title','string',array(
								Input::IS_OPTIONAL => true,
								Input::IN_RANGE => [3,255],
								Input::CUSTOM_ERROR_MESSAGES => array(
									Input::ERROR_RANGE => 'Link title must be between @min and @max characters long',
								)
							)))->out();
							if (!isset($title))
								$data['title'] = '';
							else if ($creating || $Link['title'] !== $title){
								CoreUtils::CheckStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_PATTERN);
								$data['title'] = $title;
							}

							$minrole = (new Input('minrole',function($value){
								if (!isset(Permission::$ROLES_ASSOC[$value]) || !Permission::Sufficient('user', $value))
									Response::Fail();
							},array(
								Input::CUSTOM_ERROR_MESSAGES => array(
									Input::ERROR_MISSING => 'Minumum role is missing',
									Input::ERROR_INVALID => 'Minumum role (@value) is invalid',
								)
							)))->out();
							if ($creating || $Link['minrole'] !== $minrole)
								$data['minrole'] = $minrole;

							if (empty($data))
								Response::Fail('Nothing was changed');
							$query = $creating
								? $Database->insert('usefullinks', $data)
								: $Database->where('id', $Link['id'])->update('usefullinks', $data);
							if (!$query)
								Response::DBError();

							Response::Done();
						break;
						default: CoreUtils::NotFound();
					}
				}
				else if ($data === 'reorder'){
					$list = (new Input('list','int[]',array(
						Input::CUSTOM_ERROR_MESSAGES => array(
							Input::ERROR_MISSING => 'Missing ordering information',
						)
					)))->out();
					$order = 1;
					foreach ($list as $id){
						if (!$Database->where('id', $id)->update('usefullinks', array('order' => $order++)))
							Response::Fail("Updating link #$id failed, process halted");
					}

					Response::Done();
				}
				else CoreUtils::NotFound();
			break;
			default:
				CoreUtils::NotFound();
		}
	}

	if (empty($task))
		CoreUtils::LoadPage(array(
			'title' => 'Admin Area',
			'do-css',
			'js' => array('Sortable',$do),
		));

	switch ($task){
		case "logs":

			$type = Log::ValidateRefType('type', true, true);
			if (isset($_GET['type']) && regex_match(new RegExp('/^[a-z_]+$/'), $_GET['type']) && isset(Log::$LOG_DESCRIPTION[$_GET['type']]))
				$type = $_GET['type'];

			if (!isset($_GET['by']))
				$by = null;
			else switch(strtolower(CoreUtils::Trim($_GET['by']))){
				case 'me':
				case 'you':
					$initiator = $currentUser['id'];
					$by = 'you';
				break;
				case 'web server':
					$initiator = 0;
					$by = 'Web server';
				break;
				default:
					$by = User::ValidateName('by', null, true);
					if (isset($by)){
						$by = User::Get($by, 'id,name');
						$initiator = $by['id'];
						$by = $initiator === $currentUser['id'] ? 'me' : $by['name'];
					}
			};

			$title = '';
			function processFilter(&$q = null){
				global $Database, $currentUser, $type, $by, $initiator, $title;

				if (isset($type)){
					$Database->where('reftype', $type);
					if (isset($q)){
						$q[] = "type=$type";
						$title .= Log::$LOG_DESCRIPTION[$type].' entries ';
					}
				}
				else if (isset($q))
					$q[] = 'type='.CoreUtils::FIXPATH_EMPTY;
				if (isset($initiator)){
					$_params = $initiator === 0 ? array('"initiator" IS NULL') : array('initiator',$initiator);
					$Database->where(...$_params);
					if (isset($q) && isset($by)){
						$q[] = "by=$by";
						$title .= (!isset($type)?'Entries ':'')."by $by ";
					}
				}
				else if (isset($q))
					$q[] = 'by='.CoreUtils::FIXPATH_EMPTY;
			}
			$q = array();
			if (isset($_GET['js']))
				$q[] = 'js='.$_GET['js'];
			processFilter($q);

			$Pagination = new Pagination('admin/logs', 20, $Database->count('log'));

			$heading = 'Global logs';
			if (!empty($title))
				$title .= '- ';
			$title .= "Page {$Pagination->page} - $heading";
			CoreUtils::FixPath("/admin/logs/{$Pagination->page}".(!empty($q)?'?'.implode('&',$q):''));

			processFilter();
			$LogItems = $Database
				->orderBy('timestamp')
				->orderBy('entryid')
				->get('log', $Pagination->GetLimit());

			if (isset($_GET['js']))
				$Pagination->Respond(Log::GetTbody($LogItems), '#logs tbody');

			CoreUtils::LoadPage(array(
				'title' => $title,
				'view' => "$do-logs",
				'css' => "$do-logs",
				'js' => array("$do-logs", 'paginate'),
			));
		break;
		default:
			CoreUtils::NotFound();
	}
