<?php

namespace App;

$router = new \AltoRouter();
$router->addMatchTypes([
	'un' => USERNAME_PATTERN,
	'au' => '[A-Za-z_]+',
	'ad' => '[A-Za-z\-]+',
	'adi' => '[A-Za-z\d\-]+',
	'ai' => '[A-Za-z\d]+',
	'epid' => EPISODE_ID_PATTERN,
	'rr' => '(req|res)',
	'rrl' => '(request|reservation)',
	'rrsl' => '(request|reservation)s?',
	'cgimg' => '[spfc]',
	'cgext' => '(png|svg|json|gpl)',
	'guide' => '(eqg|pony)',
	'favme' => 'd[a-z\d]{6}',
	'gsd' => '([gs]et|del)',
	'cg' => '(c(olou?r)?g(uide)?)',
	'user' => 'u(ser)?',
	'v' => '(v|appearance)',
	'uuid' => '([0-9a-fA-F]{32}|[0-9a-fA-F-]{36})',
]);

// Pages
# AboutController
$router->map('GET', '/about',                         'AboutController#index');
$router->map('GET', '/about/browser/[uuid:session]?', 'AboutController#browser');
$router->map('GET', '/browser/[uuid:session]?',       'AboutController#browser');
$router->map('GET', '/about/privacy',                 'AboutController#privacy');
# AdminController
$router->map('GET', '/admin',                      'AdminController#index');
$router->map('GET', '/logs/[i]?',                  'AdminController#log');
$router->map('GET', '/logs/[i]',                   'AdminController#log');
$router->map('GET', '/admin/logs/[i]?',            'AdminController#log');
$router->map('GET', '/admin/discord',              'AdminController#discord');
$router->map('GET', '/admin/usefullinks',          'AdminController#usefulLinks');
$router->map('GET', '/admin/wsdiag',               'AdminController#wsdiag');
$router->map('GET', '/admin/pcg-appearances/[i]?', 'AdminController#pcgAppearances');
$router->map('GET', '/admin/notices',              'AdminController#notices');
# ColorGuideController
$router->map('GET', '/blending',                           'ColorGuideController#blending');
$router->map('GET', '/[cg]/blending',                      'ColorGuideController#blending');
$router->map('GET', '/[cg]/blending-reverse',              'ColorGuideController#blendingReverse');
$router->map('GET', '/[cg]/picker',                        'ColorGuideController#picker');
$router->map('GET', '/[cg]/picker/frame',                  'ColorGuideController#pickerFrame');
$router->map('GET', '/[cg]/[guide:guide]?/[i]?',           'ColorGuideController#guide');
$router->map('GET', '/[cg]/[guide:guide]?/full',           'ColorGuideController#fullList');
$router->map('GET', '/[cg]/[guide:guide]?/changes/[i]?',   'ColorGuideController#changeList');
$router->map('GET', '/[cg]/[guide:guide]?/[v]',            'ColorGuideController#guide');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]', 'ColorGuideController#guide');
# AppearanceController
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[i:id]',                                     'AppearanceController#view');
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[i:id]-[adi]',                               'AppearanceController#view');
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[adi]-[i:id]',                               'AppearanceController#view');
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[i:id][cgimg:type]?.[cgext:ext]',            'AppearanceController#asFile');
$router->map('GET', '/[cg]/[guide:guide]?/sprite(-colors)?/[i:id][adi]?',                  'AppearanceController#sprite');
$router->map('GET', '/[cg]/[guide:guide]?/tag-changes/[i:id][adi]?',                       'AppearanceController#tagChanges');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[i:id]',                          'AppearanceController#view');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[i:id]-[adi]',                    'AppearanceController#view');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[adi]-[i:id]',                    'AppearanceController#view');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[i:id][cgimg:type]?.[cgext:ext]', 'AppearanceController#asFile');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/sprite(-colors)?/[i:id][adi]?',       'AppearanceController#sprite');
# ComponentsController
$router->map('GET', '/components', 'ComponentsController#index');
# TagController
$router->map('GET', '/[cg]/[guide:guide]?/tags/[i]?', 'TagController#list');
# CutiemarkController
$router->map('GET', '/[cg]/cutiemark/[i:id].svg',            'CutiemarkController#view');
$router->map('GET', '/[cg]/cutiemark/download/[i:id][adi]?', 'CutiemarkController#download');
# AuthController
$router->map('GET', '/da-auth',       'AuthController#end');
$router->map('GET', '/da-auth/begin', 'AuthController#begin');
$router->map('GET', '/da-auth/end',   'AuthController#end');
# DiscordAuthController
$router->map('GET', '/discord-connect/begin', 'DiscordAuthController#begin');
$router->map('GET', '/discord-connect/end',   'DiscordAuthController#end');
# EpisodeController
$router->map('GET', '/episode/[epid:id]', 'EpisodeController#view');
$router->map('GET', '/episode/latest',    'EpisodeController#latest');
# ShowController
$router->map('GET', '/episodes/[i]?', 'ShowController#index');
$router->map('GET', '/movies/[i]?',   'ShowController#index');
$router->map('GET', '/show',          'ShowController#index');
# EQGController
$router->map('GET', '/eqg/[i:id]',   'EQGController#redirectInt');
$router->map('GET', '/eqg/[adi:id]', 'EQGController#redirectStr');
# EventController
$router->map('GET', '/events/[i]?',        'EventController#list');
$router->map('GET', '/event/[i:id][adi]?', 'EventController#view');
# MovieController
$router->map('GET', '/movie/[i:id][adi]?', 'MovieController#view');
# MuffinRatingController
$router->map('GET', '/muffin-rating', 'MuffinRatingController#image');
# PostController
$router->map('GET', '/s/[rr:thing]?/[ai:id]', 'PostController#share');
# UserController
$router->map('GET', '/',                                    'UserController#homepage');
$router->map('GET', '/users',                               'UserController#list');
$router->map('GET', '/@[un:name]',                          'UserController#profile');
$router->map('GET', '/u/[un:name]?',                        'UserController#profile');
$router->map('GET', '/u/[uuid:uuid]',                       'UserController#profileByUuid');
$router->map('GET', '/@[un:name]/contrib/[ad:type]/[i]?',   'UserController#contrib');
$router->map('GET', '/user/contrib/lazyload/[favme:favme]', 'UserController#contribLazyload');
# PersonalGuideController
$router->map('GET', '/@[un:name]/[cg]/[i]?',               'PersonalGuideController#list');
$router->map('GET', '/@[un:name]/[cg]/slot-history/[i]?',  'PersonalGuideController#pointHistory');
$router->map('GET', '/@[un:name]/[cg]/point-history/[i]?', 'PersonalGuideController#pointHistory');
# ManifestController
$router->map('GET', '/manifest', 'ManifestController#json');

// Proper REST API endpoints (sort of)
// Allowing all request methods lets us reply with HTTP 405 to unsupported methods at the controller level
\define('API_PATH', '/api/private');
$api_endpoint = function($path, $controller) use ($router){
	$router->map('POST|GET|PUT|DELETE', API_PATH.$path, $controller);
};
$api_endpoint('/about/upcoming',                     'AboutController#upcoming');
$api_endpoint('/admin/logs/details/[i:id]',          'AdminController#logDetail');
$api_endpoint('/admin/usefullinks/[i:id]?',          'AdminController#usefulLinksApi');
$api_endpoint('/admin/usefullinks/reorder',          'AdminController#reorderUsefulLinks');
$api_endpoint('/admin/wsdiag/hello',                 'AdminController#wshello');
$api_endpoint('/admin/mass-approve',                 'AdminController#massApprove');
$api_endpoint('/admin/notices/[i:id]?',              'AdminController#noticesApi');
$api_endpoint('/cg/appearances/list',                'AppearanceController#listApi');
$api_endpoint('/cg/appearance/[i:id]?',              'AppearanceController#api');
$api_endpoint('/cg/appearance/[i:id]/colorgroups',   'AppearanceController#colorGroupsApi');
$api_endpoint('/cg/appearance/[i:id]/sprite',        'AppearanceController#spriteApi');
$api_endpoint('/cg/appearance/[i:id]/sprite/check-colors', 'AppearanceController#checkColors');
$api_endpoint('/cg/appearance/[i:id]/relations',     'AppearanceController#relationsApi');
$api_endpoint('/cg/appearance/[i:id]/cutiemarks',    'AppearanceController#cutiemarkApi');
$api_endpoint('/cg/appearance/[i:id]/tagged',        'AppearanceController#taggedApi');
$api_endpoint('/cg/appearance/[i:id]/template',      'AppearanceController#applyTemplate');
$api_endpoint('/cg/appearance/[i:id]/sanitize-svg',  'AppearanceController#sanitizeSvg');
$api_endpoint('/cg/appearance/[i:id]/selective',     'AppearanceController#selectiveClear');
$api_endpoint('/cg/appearance/[i:id]/link-targets',  'AppearanceController#linkTargets');
$api_endpoint('/cg/sprite-color-checkup',            'ColorGuideController#spriteColorCheckup');
$api_endpoint('/cg/full/reorder',                    'ColorGuideController#reorderFullList');
$api_endpoint('/cg/export',                          'ColorGuideController#export');
$api_endpoint('/cg/reindex',                         'ColorGuideController#reindex');
$api_endpoint('/cg/tags',                            'TagController#autocomplete');
$api_endpoint('/cg/tags/recount-uses',               'TagController#recountUses');
$api_endpoint('/cg/tag/[i:id]?',                     'TagController#api');
$api_endpoint('/cg/tag/[i:id]/synonym',              'TagController#synonymApi');
$api_endpoint('/cg/colorgroup/[i:id]?',              'ColorGroupController#api');
$api_endpoint('/da-auth/status',                     'AuthController#sessionStatus');
$api_endpoint('/da-auth/sign-out',                   'AuthController#signOut');
$api_endpoint('/episode/[epid:id]?',                 'EpisodeController#api');
$api_endpoint('/episode/[epid:id]/posts',            'EpisodeController#postList');
$api_endpoint('/episode/[epid:id]/vote',             'EpisodeController#voteApi');
$api_endpoint('/episode/[epid:id]/video-embeds',     'EpisodeController#videoEmbeds');
$api_endpoint('/episode/[epid:id]/video-data',       'EpisodeController#videoDataApi');
$api_endpoint('/episode/[epid:id]/guide-relations',  'EpisodeController#guideRelationsApi');
$api_endpoint('/episode/[epid:id]/broken-videos',    'EpisodeController#brokenVideos');
$api_endpoint('/episode/next',                       'EpisodeController#next');
$api_endpoint('/episode/prefill',                    'EpisodeController#prefill');
$api_endpoint('/event/[i:id]?',                      'EventController#api');
$api_endpoint('/event/[i:id]/finalize',              'EventController#finalize');
$api_endpoint('/event/[i:id]/check-entries',         'EventController#checkEntries');
$api_endpoint('/event/[i:id]/entry',                 'EventEntryController#api');
$api_endpoint('/event/entry/[i:entryid]',            'EventEntryController#api');
$api_endpoint('/event/entry/[i:entryid]/vote',       'EventEntryController#voteApi');
$api_endpoint('/event/entry/[i:entryid]/lazyload',   'EventEntryController#lazyload');
$api_endpoint('/notif',                              'NotificationsController#get');
$api_endpoint('/notif/[i:id]/mark-read',             'NotificationsController#markRead');
$api_endpoint('/post/[i:id]?',                       'PostController#api');
$api_endpoint('/post/[i:id]/lazyload',               'PostController#lazyload');
$api_endpoint('/post/[i:id]/finish',                 'PostController#finish');
$api_endpoint('/post/[i:id]/locate',                 'PostController#locate');
$api_endpoint('/post/[i:id]/reload',                 'PostController#reload');
$api_endpoint('/post/[i:id]/transfer',               'PostController#transfer');
$api_endpoint('/post/[i:id]/unbreak',                'PostController#unbreak');
$api_endpoint('/post/[i:id]/approval',               'PostController#approvalApi');
$api_endpoint('/post/[i:id]/image',                  'PostController#setImage');
$api_endpoint('/post/[i:id]/reservation',            'PostController#reservationApi');
$api_endpoint('/post/check-image',                   'PostController#checkImage');
$api_endpoint('/post/reservation',                   'PostController#addReservation');
$api_endpoint('/post/request/[i:id]',                'PostController#deleteRequest');
$api_endpoint('/post/request/suggestion',            'PostController#suggestRequest');
$api_endpoint('/post/[i:id]/fix-stash',              'PostController#fixStash');
$api_endpoint('/setting/[au:key]',                   'SettingController#api');
$api_endpoint('/user/session/[uuid:id]',             'UserController#sessionApi');
$api_endpoint('/user/[uuid:id]/avatar-wrap',         'UserController#avatarWrap');
$api_endpoint('/user/[uuid:id]/role',                'UserController#roleApi');
$api_endpoint('/user/[uuid:id]/preference/[au:key]', 'PreferenceController#api');
$api_endpoint('/user/pcg/giftable-slots',            'PersonalGuideController#verifyGiftableSlots');
$api_endpoint('/user/pcg/refund-gifts',              'PersonalGuideController#refundSlotGifts');
$api_endpoint('/user/[uuid:id]/pcg/point-history/recalc', 'PersonalGuideController#pointRecalc');
$api_endpoint('/user/[uuid:id]/pcg/points',          'PersonalGuideController#pointsApi');
$api_endpoint('/user/[uuid:id]/pcg/slots',           'PersonalGuideController#slotsApi');
$api_endpoint('/user/[uuid:id]/pcg/pending-gifts',   'PersonalGuideController#getPendingSlotGifts');

// "API" Endpoints
$router->map('POST', '/discord-connect/sync/[un:name]',      'DiscordAuthController#sync');
$router->map('POST', '/discord-connect/unlink/[un:name]',    'DiscordAuthController#unlink');
$router->map('POST', '/discord-connect/bot-update/[i:id]',   'DiscordAuthController#botUpdate');
