<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace App;

global $router;

// Pages
# AboutController
$router->map('GET', '/about', 'AboutController#index');
$router->map('GET', '/about/browser/[i:session]?', 'AboutController#browser');
$router->map('GET', '/browser/[i:session]?', 'AboutController#browser');
$router->map('GET', '/about/privacy', 'AboutController#privacy');
# AdminController
$router->map('GET', '/admin', 'AdminController#index');
$router->map('GET', '/logs/[i]?', 'AdminController#log');
$router->map('GET', '/logs/[i]', 'AdminController#log');
$router->map('GET', '/admin/logs/[i]?', 'AdminController#log');
$router->map('GET', '/admin/discord', 'AdminController#discord');
$router->map('GET', '/admin/usefullinks', 'AdminController#usefulLinks');
$router->map('GET', '/admin/wsdiag', 'AdminController#wsdiag');
$router->map('GET', '/admin/pcg-appearances/[i]?', 'AdminController#pcgAppearances');
$router->map('GET', '/admin/notices', 'AdminController#notices');
# ColorGuideController
$router->map('GET', '/blending', 'ColorGuideController#blending');
$router->map('GET', '/[cg]/blending', 'ColorGuideController#blending');
$router->map('GET', '/[cg]/blending-reverse', 'ColorGuideController#blendingReverse');
$router->map('GET', '/[cg]/picker', 'ColorGuideController#picker');
$router->map('GET', '/[cg]/picker/frame', 'ColorGuideController#pickerFrame');
$router->map('GET', '/[cg]/[guide:guide]?/[i]?', 'ColorGuideController#guide');
$router->map('GET', '/[cg]/[guide:guide]?/full', 'ColorGuideController#fullList');
$router->map('GET', '/[cg]/[guide:guide]?/changes/[i]?', 'ColorGuideController#changeList');
$router->map('GET', '/[cg]/[guide:guide]?/[v]', 'ColorGuideController#guide');
# AppearanceController
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[i:id]-?', 'AppearanceController#view');
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[i:id]-[adi]', 'AppearanceController#view');
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[adi]-[i:id]', 'AppearanceController#view');
$router->map('GET', '/[cg]/[guide:guide]?/[v]/[i:id][cgimg:type]?.[cgext:ext]', 'AppearanceController#asFile');
$router->map('GET', '/[cg]/[guide:guide]?/sprite(-colors)?/[i:id][adi]?', 'AppearanceController#sprite');
$router->map('GET', '/[cg]/[guide:guide]?/tag-changes/[i:id][adi]?', 'AppearanceController#tagChanges');
$router->map('GET', '/users/[i:user_id]/[cg]/[guide:guide]?/[v]/[i:id]-?', 'AppearanceController#view');
$router->map('GET', '/users/[i:user_id]/[cg]/[guide:guide]?/[v]/[i:id]-[adi]', 'AppearanceController#view');
$router->map('GET', '/users/[i:user_id]/[cg]/[guide:guide]?/[v]/[adi]-[i:id]', 'AppearanceController#view');
$router->map('GET', '/users/[i:user_id]/[cg]/[guide:guide]?/[v]/[i:id][cgimg:type]?.[cgext:ext]', 'AppearanceController#asFile');
$router->map('GET', '/users/[i:user_id]/[cg]/[guide:guide]?/sprite(-colors)?/[i:id][adi]?', 'AppearanceController#sprite');
# ComponentsController
$router->map('GET', '/components', 'ComponentsController#index');
# DocsController
$router->map('GET', '/docs', 'DocsController#index');
# TagController
$router->map('GET', '/[cg]/[guide:guide]?/tags/[i]?', 'TagController#list');
# CutiemarkController
$router->map('GET', '/[cg]/cutiemark/[i:id].svg', 'CutiemarkController#view');
$router->map('GET', '/[cg]/cutiemark/download/[i:id][adi]?', 'CutiemarkController#download');
# AuthController
$router->map('GET', '/da-auth', 'AuthController#softEnd');
$router->map('GET', '/da-auth/begin', 'AuthController#begin');
$router->map('GET', '/da-auth/end', 'AuthController#end');
# DiscordAuthController
$router->map('GET', '/discord-connect/begin', 'DiscordAuthController#begin');
$router->map('GET', '/discord-connect/end', 'DiscordAuthController#end');
# ShowController
$router->map('GET', '/episode/[epid:id]', 'ShowController#viewEpisode');
$router->map('GET', '/episode/latest', 'ShowController#latest');
$router->map('GET', '/episodes/[i]?', 'ShowController#index');
$router->map('GET', '/[st]/[i:id][adi]?', 'ShowController#viewById');
$router->map('GET', '/movies/[i]?', 'ShowController#index');
$router->map('GET', '/show', 'ShowController#index');
# EQGController
$router->map('GET', '/eqg/[i:id]', 'EQGController#redirectInt');
$router->map('GET', '/eqg/[adi:id]', 'EQGController#redirectStr');
# EventController
$router->map('GET', '/events/[i]?', 'EventController#list');
$router->map('GET', '/event/[i:id][adi]?', 'EventController#view');
# MuffinRatingController
$router->map('GET', '/muffin-rating', 'MuffinRatingController#image');
# PostController
$router->map('GET', '/s/[rr:thing]?/[ai:id]', 'PostController#share');
# UserController
$router->map('GET', '/', 'UserController#homepage');
$router->map('GET', '/users', 'UserController#list');
$router->map('GET', '/users/[i:user_id]', 'UserController#profile');
$router->map('GET', '/users/[i:user_id]-[uc]?', 'UserController#profile');
$router->map('GET', '/u', 'UserController#profile');
$router->map('GET', '/u/[uuid:uuid]', 'UserController#profileByUuid');
$router->map('GET', '/users/[i:user_id]/contrib/[ad:type]/[i]?', 'UserController#contrib');
$router->map('GET', '/user/contrib/lazyload/[favme:favme]', 'UserController#contribLazyload');
// Forced redirects from the old URL structure
$router->map('GET', '/@[un:name]', 'UserController#forceRedirect');
$router->map('GET', '/u/[un:name]?', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/contrib/[ad:type]/[i]?', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[i]?', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/slot-history/[i]?', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/point-history/[i]?', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[i:id]-?', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[i:id]-[adi]', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[adi]-[i:id]', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/[v]/[i:id][cgimg:type]?.[cgext:ext]', 'UserController#forceRedirect');
$router->map('GET', '/@[un:name]/[cg]/[guide:guide]?/sprite(-colors)?/[i:id][adi]?', 'UserController#forceRedirect');
# PersonalGuideController
$router->map('GET', '/users/[i:user_id]/[cg]/[guide:guide]?/[v]', 'PersonalGuideController#list');
$router->map('GET', '/users/[i:user_id]/[cg]/[i]?', 'PersonalGuideController#list');
$router->map('GET', '/users/[i:user_id]/[cg]/slot-history/[i]?', 'PersonalGuideController#pointHistory');
$router->map('GET', '/users/[i:user_id]/[cg]/point-history/[i]?', 'PersonalGuideController#pointHistory');
# ManifestController
$router->map('GET', '/manifest', 'ManifestController#json');
# DiagnoseController
$router->map('GET', '/diagnose/ex/[a:type]', 'DiagnoseController#exception');
$router->map('GET', '/diagnose/lt/[i:time]', 'DiagnoseController#loadtime');
