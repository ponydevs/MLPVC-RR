<?php

	if (!Permission::Sufficient('inspector'))
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

					CoreUtils::Respond(Log::FormatEntryDetails($MainEntry['reftype'],$Details));
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

							if (empty($_POST['label']))
								CoreUtils::Respond('Link label is missing');
							$label = CoreUtils::Trim($_POST['label']);
							if ($creating || $Link['label'] !== $label){
								$ll = strlen($label);
								if ($ll < 3 || $ll > 40)
									CoreUtils::Respond('Link label must be between 3 and 40 characters long');
								CoreUtils::CheckStringValidity($label, 'Link label', INVERSE_PRINTABLE_ASCII_REGEX);
								$data['label'] = $label;
							}

							if (empty($_POST['url']))
								CoreUtils::Respond('Link URL is missing');
							$url = CoreUtils::Trim($_POST['url']);
							if ($creating || $Link['url'] !== $url){
								$ul = strlen($url);
								if (stripos($url, ABSPATH) === 0)
									$url = substr($url, strlen(ABSPATH)-1);
								if (!regex_match($REWRITE_REGEX,$url) && !regex_match(new RegExp('^#[a-z\-]+$'),$url)){
									if ($ul < 3 || $ul > 255)
										CoreUtils::Respond('Link URL must be between 3 and 255 characters long');
									if (!regex_match(new RegExp('^https?:\/\/.+$'), $url))
										CoreUtils::Respond('Link URL does not appear to be a valid link');
								}
								$data['url'] = $url;
							}

							if (!empty($_POST['title'])){
								$title = CoreUtils::Trim($_POST['title']);
								if ($creating || $Link['title'] !== $title){
									$tl = strlen($title);
									if ($tl < 3 || $tl > 255)
										CoreUtils::Respond('Link title must be between 3 and 255 characters long');
									CoreUtils::CheckStringValidity($title, 'Link title', INVERSE_PRINTABLE_ASCII_REGEX);
									$data['title'] = CoreUtils::Trim($title);
								}
							}
							else $data['title'] = '';

							if (empty($_POST['minrole']))
								CoreUtils::Respond('Minimum role is missing');
							$minrole = CoreUtils::Trim($_POST['minrole']);
							if ($creating || $Link['minrole'] !== $minrole){
								if (!isset(Permission::$ROLES_ASSOC[$minrole]) || !Permission::Sufficient('user', $minrole))
									CoreUtils::Respond('Minumum role is invalid');
								$data['minrole'] = $minrole;
							}

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
					if (!isset($_POST['list']))
						CoreUtils::Respond('Missing ordering information');

					$list = explode(',',regex_replace(new RegExp('[^\d,]'),'',CoreUtils::Trim($_POST['list'])));
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
