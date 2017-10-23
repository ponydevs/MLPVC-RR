<?php

namespace App;

$router = new \AltoRouter();
$router->addMatchTypes([
	'un' => USERNAME_PATTERN,
	'au' => '[A-Za-z_]+',
	'ad' => '[A-Za-z\-]+',
	'adi' => '[A-Za-z\d\-]+',
	'epid' => EPISODE_ID_PATTERN,
	'rr' => '(req|res)',
	'rrl' => '(request|reservation)',
	'rrsl' => '(request|reservation)s?',
	'cgimg' => '[spfc]',
	'cgext' => '(png|svg|json|gpl)',
	'eqg' => 'eqg',
	'favme' => 'd[a-z\d]{6}',
	'gsd' => '([gs]et|del)',
	'make' => 'make',
	'cg' => '(c(olou?r)?g(uide)?)',
	'user' => 'u(ser)?',
	'v' => '(v|appearance)',
	'uuid' => '([0-9a-fA-F]{32}|[0-9a-fA-F-]{36})',
	'ip' => '([\d.]{7,15}|[\da-fA-F:]{3,46})',
]);

// Pages
$router->map('GET', '/',                               'EpisodeController#index');
$router->map('GET', '/footer-git',                     'FooterController#git');
$router->map('GET', '/about',                          'AboutController#index');
$router->map('GET', '/about/browser/[i:session]?',     'AboutController#browser');
$router->map('GET', '/browser/[i:session]?',           'AboutController#browser');
$router->map('GET', '/admin',                          'AdminController#index');
$router->map('GET', '/logs/[i]?',                      'AdminController#log');
$router->map('GET', '/admin/logs/[i]?',                'AdminController#log');
$router->map('GET', '/admin/discord',                  'AdminController#discord');
$router->map('GET', '/admin/usefullinks',              'AdminController#usefulLinks');
$router->map('GET', '/admin/wsdiag',                   'AdminController#wsdiag');
$router->map('GET', '/admin/ip/[ip:ip]',               'AdminController#ip');
$router->map('GET', '/blending',                       'ColorGuideController#blending');
$router->map('GET', '/components',                     'ComponentsController#index');
$router->map('GET',      '/[cg]/blending',                                      'ColorGuideController#blending');
$router->map('GET',      '/[cg]/blending-reverse',                              'ColorGuideController#blendingReverse');
$router->map('GET',      '/[cg]/picker',                                        'ColorGuideController#picker');
$router->map('GET',      '/[cg]/picker/frame',                                  'ColorGuideController#pickerFrame');
$router->map('GET',      '/[cg]/[eqg:eqg]?/[i]?',                               'ColorGuideController#guide');
$router->map('GET',      '/[cg]/[eqg:eqg]?/full',                               'ColorGuideController#fullList');
$router->map('GET',      '/[cg]/[eqg:eqg]?/tags/[i]?',                          'ColorGuideController#tagList');
$router->map('GET',      '/[cg]/[eqg:eqg]?/changes/[i]?',                       'ColorGuideController#changeList');
$router->map('GET',      '/[cg]/[eqg:eqg]?/[v]/[i:id]',                         'ColorGuideController#appearance');
$router->map('GET',      '/[cg]/[eqg:eqg]?/[v]/[i:id]-[adi]',                   'ColorGuideController#appearance');
$router->map('GET',      '/[cg]/[eqg:eqg]?/[v]/[adi]-[i:id]',                   'ColorGuideController#appearance');
$router->map('GET',      '/[cg]/[eqg:eqg]?/[v]/[i:id][cgimg:type]?.[cgext:ext]', 'ColorGuideController#appearanceAsFile');
$router->map('GET',      '/[cg]/[eqg:eqg]?/sprite(-colors)?/[i:id][adi]?',      'ColorGuideController#sprite');
$router->map('GET|POST', '/[cg]/get-tags',                                      'ColorGuideController#getTags');
$router->map('GET',      '/[cg]/cutiemark/[i:id].svg',                          'ColorGuideController#cutiemarkView');
$router->map('GET',      '/[cg]/cutiemark/download/[i:id][adi]?',               'ColorGuideController#cutiemarkDownload');
$router->map('GET', '/da-auth',                        'AuthController#auth');
$router->map('GET', '/episode/[epid:id]',              'EpisodeController#page');
$router->map('GET', '/episodes/[i]?',                  'EpisodeController#episodeList');
$router->map('GET', '/movies/[i]?',                    'EpisodeController#movieList');
$router->map('GET', '/eqg/[i:id]',                     'EQGController#redirectInt');
$router->map('GET', '/eqg/[adi:id]',                   'EQGController#redirectStr');
$router->map('GET', '/events/[i]?',                    'EventController#list');
$router->map('GET', '/event/[i:id][adi]?',             'EventController#index');
$router->map('GET', '/movie/[i:id][adi]?',             'MovieController#pageID');
$router->map('GET', '/movie/[adi:title]',              'MovieController#pageTitle');
$router->map('GET', '/logs/[i]',                       'AdminController#logs');
$router->map('GET', '/muffin-rating',                  'MuffinRatingController#image');
$router->map('GET', '/s/[rr:thing]/[i:id]',            'PostController#share');
$router->map('GET', '/users',                          'UserController#list');
$router->map('GET', '/@[un:name]',                     'UserController#profile');
$router->map('GET', '/u/[un:name]?',                   'UserController#profile');
$router->map('GET', '/u/[uuid:uuid]',                  'UserController#profileByUuid');
$router->map('GET', '/@[un:name]/contrib/[ad:type]/[i]?', 'UserController#contrib');
$router->map('GET', '/@[un:name]/[cg]/[i]?',                               'ColorGuideController#personalGuide');
$router->map('GET', '/@[un:name]/[cg]/[v]/[i:id]',                         'ColorGuideController#personalAppearancePage');
$router->map('GET', '/@[un:name]/[cg]/[v]/[i:id]-[adi]',                   'ColorGuideController#personalAppearancePage');
$router->map('GET', '/@[un:name]/[cg]/[v]/[adi]-[i:id]',                   'ColorGuideController#personalAppearancePage');
$router->map('GET', '/@[un:name]/[cg]/[v]/[i:id][cgimg:type].[cgext:ext]', 'ColorGuideController#personalAppearanceAsFile');
$router->map('GET', '/@[un:name]/[cg]/[eqg:eqg]?/sprite(-colors)?/[i:id][adi]?', 'ColorGuideController#sprite');
$router->map('GET', '/manifest',                       'ManifestController#json');
$router->map('GET', '/user/contrib/lazyload/[favme:favme]',     'UserController#contribLazyload');
$router->map('GET', '/post/lazyload/[rrl:thing]/[i:id]', 'PostController#lazyload');
$router->map('GET', '/event/entry/lazyload/[i:id]',    'EventController#lazyloadEntry');

// "API" Endpoints
$router->map('POST', '/about/stats',                         'AboutController#stats');
$router->map('POST', '/admin/logs/details/[i:id]',           'AdminController#logDetail');
$router->map('POST', '/admin/usefullinks',                   'AdminController#usefulLinks');
$router->map('POST', '/admin/usefullinks/reorder',           'AdminController#reorderUsefulLinks');
$router->map('POST', '/admin/mass-approve',                  'AdminController#massApprove');
$router->map('POST', '/admin/discord/member-list',           'AdminController#discordMemberList');
$router->map('POST', '/admin/discord/member-link/get/[i:id]', 'AdminController#discordMemberLinkGet');
$router->map('POST', '/admin/discord/member-link/set/[i:id]', 'AdminController#discordMemberLinkSet');
$router->map('POST', '/admin/discord/member-link/del/[i:id]', 'AdminController#discordMemberLinkDel');
$router->map('POST', '/cg/full/reorder',                     'ColorGuideController#reorderFullList');
$router->map('POST', '/cg/export',                           'ColorGuideController#export');
$router->map('POST', '/cg/appearance/[ad:action]/[i:id]',    'ColorGuideController#appearanceAction');
$router->map('POST', '/cg/appearance/[make:action]',         'ColorGuideController#appearanceAction');
$router->map('POST', '/cg/reindex',                          'ColorGuideController#reindex');
$router->map('POST', '/cg/sprite-color-checkup',             'ColorGuideController#spriteColorCheckup');
$router->map('POST', '/cg/tag/[ad:action]/[i:id]',           'ColorGuideController#tagAction');
$router->map('POST', '/cg/tag/[make:action]',                'ColorGuideController#tagAction');
$router->map('POST', '/cg/tags/recount-uses',                'ColorGuideController#recountTagUses');
$router->map('POST', '/cg/colorgroup/[gsd:action]/[i:id]',   'ColorGuideController#colorGroupAction');
$router->map('POST', '/cg/colorgroup/[make:action]',         'ColorGuideController#colorGroupAction');
$router->map('POST', '/cg/colorgroup/appearance-list',       'ColorGuideController#colorGroupAppearanceList');
$router->map('POST', '/cg/colorgroup/list/[i:id]',           'ColorGuideController#colorGroupList');
$router->map('POST', '/cg/get-sprite-colors/[i:id]',         'ColorGuideController#getSpriteColors');
$router->map('POST', '/cg/sanitizesvg/[i:id]',               'ColorGuideController#sanitizeSvg');
$router->map('POST', '/@[un:name]/cg/slot-check',            'UserController#checkCGSlots');
$router->map('POST', '/@[un:name]/cg/appearance/[ad:action]/[i:id]',  'ColorGuideController#appearanceAction');
$router->map('POST', '/@[un:name]/cg/appearance/[make:action]',       'ColorGuideController#appearanceAction');
$router->map('POST', '/@[un:name]/cg/colorgroup/[gsd:action]/[i:id]', 'ColorGuideController#colorGroupAction');
$router->map('POST', '/@[un:name]/cg/colorgroup/[make:action]',       'ColorGuideController#colorGroupAction');
$router->map('POST', '/da-auth/signout',                     'AuthController#signout');
$router->map('POST', '/episode/postlist/[epid:id]',          'EpisodeController#postList');
$router->map('POST', '/episode/get/[epid:id]',               'EpisodeController#get');
$router->map('POST', '/episode/delete/[epid:id]',            'EpisodeController#delete');
$router->map('POST', '/episode/set/[epid:id]',               'EpisodeController#set');
$router->map('POST', '/episode/add',                         'EpisodeController#add');
$router->map('POST', '/episode/vote/[epid:id]',              'EpisodeController#vote');
$router->map('POST', '/episode/video-embeds/[epid:id]',      'EpisodeController#getVideoEmbeds');
$router->map('POST', '/episode/video-data/[epid:id]',        'EpisodeController#videoData');
$router->map('POST', '/episode/guide-relations/[epid:id]',   'EpisodeController#guideRelations');
$router->map('POST', '/episode/broken-videos/[epid:id]',     'EpisodeController#brokenVideos');
$router->map('POST', '/episode/nextup',                      'EpisodeController#nextup');
$router->map('POST', '/episode/prefill',                     'EpisodeController#prefill');
$router->map('POST', '/event/get/[i:id]',                    'EventController#get');
$router->map('POST', '/event/del/[i:id]',                    'EventController#delete');
$router->map('POST', '/event/set/[i:id]',                    'EventController#set');
$router->map('POST', '/event/add',                           'EventController#add');
$router->map('POST', '/event/finalize/[i:id]',               'EventController#finalize');
$router->map('POST', '/event/check-entries/[i:id]',          'EventController#checkEntries');
$router->map('POST', '/event/entry/add/[i:id]',              'EventController#addEntry');
$router->map('POST', '/event/entry/get/[i:entryid]',         'EventController#getEntry');
$router->map('POST', '/event/entry/set/[i:entryid]',         'EventController#setEntry');
$router->map('POST', '/event/entry/del/[i:entryid]',         'EventController#delEntry');
$router->map('POST', '/event/entry/vote/[i:entryid]',        'EventController#voteEntry');
$router->map('POST', '/event/entry/unvote/[i:entryid]',      'EventController#unvoteEntry');
$router->map('POST', '/event/entry/getvote/[i:entryid]',     'EventController#getvoteEntry');
$router->map('POST', '/notifications/get',                   'NotificationsController#get');
$router->map('POST', '/notifications/mark-read/[i:id]',      'NotificationsController#markRead');
$router->map('POST', '/ping',                                'PingController#ping');
$router->map('POST', '/post/reload/[rrl:thing]/[i:id]',      'PostController#reload');
$router->map('POST', '/post/transfer/[rrl:thing]/[i:id]',    'PostController#queryTransfer');
$router->map('POST', '/post/[a:action]/[rrsl:thing]/[i:id]', 'PostController#action');
$router->map('POST', '/post/add',                            'PostController#add');
$router->map('POST', '/post/set-image/[rrl:thing]/[i:id]',   'PostController#setImage');
$router->map('POST', '/post/check-image',                    'PostController#checkImage');
$router->map('POST', '/post/fix-stash/[rrl:thing]/[i:id]',   'PostController#fixStash');
$router->map('POST', '/post/add-reservation',                'PostController#addReservation');
$router->map('POST', '/post/delete-request/[i:id]',          'PostController#deleteRequest');
$router->map('POST', '/post/locate/[rrl:thing]/[i:id]',      'PostController#locate');
$router->map('POST', '/preference/set/[au:key]',             'PreferenceController#set');
$router->map('POST', '/preference/get/[au:key]',             'PreferenceController#get');
$router->map('POST', '/setting/set/[au:key]',                'SettingController#set');
$router->map('POST', '/setting/get/[au:key]',                'SettingController#get');
$router->map('POST', '/user/suggestion',                     'UserController#suggestion');
$router->map('POST', '/user/sessiondel/[i:id]',              'UserController#sessionDel');
$router->map('POST', '/user/setgroup/[un:name]',             'UserController#setGroup');
$router->map('POST', '/user/banish/[un:name]',               'UserController#banish');
$router->map('POST', '/user/un-banish/[un:name]',            'UserController#unbanish');
$router->map('POST', '/user/awaiting-approval/[un:name]',    'UserController#awaitingApproval');
$router->map('POST', '/user/avatar-wrap/[un:name]',          'UserController#avatarWrap');
$router->map('POST', '/user/known-ips/[un:name]',            'UserController#knownIps');
