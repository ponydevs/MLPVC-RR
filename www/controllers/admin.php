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
					if (empty($MainEntry)) CoreUtils::Respond('Log entry does not exist');
					if (empty($MainEntry['refid'])) CoreUtils::Respond('There are no details to show');

					$Details = $Database->where('entryid', $MainEntry['refid'])->getOne("log__{$MainEntry['reftype']}");
					if (empty($Details)) CoreUtils::Respond('Failed to retrieve details');

					CoreUtils::Respond(Log::FormatEntryDetails($MainEntry,$Details));
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
							CoreUtils::Respond('The specified link does not exist');
					}

					switch ($action){
						case 'get':
							CoreUtils::Respond(array(
								'label' => $Link['label'],
								'url' => $Link['url'],
								'title' => $Link['title'],
								'minrole' => $Link['minrole'],
							));
						case 'del':
							if (!$Database->where('id', $Link['id'])->delete('usefullinks'))
								CoreUtils::Respond(ERR_DB_FAIL);

							CoreUtils::Respond(true);
						break;
						case 'make':
						case 'set':
							$data = array();

							$label = (new Input('label','string',array(
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
									CoreUtils::Respond();
							},array(
								Input::CUSTOM_ERROR_MESSAGES => array(
									Input::ERROR_MISSING => 'Minumum role is missing',
									Input::ERROR_INVALID => 'Minumum role (@value) is invalid',
								)
							)))->out();
							if ($creating || $Link['minrole'] !== $minrole)
								$data['minrole'] = $minrole;

							if (empty($data))
								CoreUtils::Respond('Nothing was changed');
							$query = $creating
								? $Database->insert('usefullinks', $data)
								: $Database->where('id', $Link['id'])->update('usefullinks', $data);
							if (!$query)
								CoreUtils::Respond(ERR_DB_FAIL);

							CoreUtils::Respond(true);
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
							CoreUtils::Respond("Updating link #$id failed, process halted");
					}

					CoreUtils::Respond(true);
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
			$Pagination = new Pagination('admin/logs', 20, $Database->count('log'));

			CoreUtils::FixPath("/admin/logs/{$Pagination->page}");
			$heading = 'Logs';
			$title = "Page {$Pagination->page} - $heading";

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
