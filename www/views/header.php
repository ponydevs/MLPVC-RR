<!DOCTYPE html>
<html lang="en">
<head>
	<title><?=isset($title)?$title.' - ':''?>Vector Club Requests & Reservations</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
<?php if (isset($norobots)){ ?>
	<meta name="robots" content="noindex, nofollow">
<?php } ?>
	<link rel="shortcut icon" href="/favicon.ico">
<?php if (isset($customCSS)) foreach ($customCSS as $css){ ?>
	<link rel="stylesheet" href="/css/<?=$css?>.css?<?=LATEST_COMMIT_ID?>">
<?php } ?>
	<script src="/js/prefixfree.min.js" id="prefixfree"></script>
	<script src="//code.jquery.com/jquery-2.1.4.min.js"></script>
	<script>window.jQuery||document.write('\x3Cscript src="/js/jquery-2.1.4.min.js">\x3C/script>')</script>
	<script>(function(w,d,u){w.RELPATH='<?=RELPATH?>';$.ajaxPrefilter(function(e){var t,n=d.cookie.split("; ");$.each(n,function(e,n){n=n.split("=");if(n[0]==="CSRF_TOKEN"){t=n[1];return false}});if(typeof t!=="undefined"){if(typeof e.data==="undefined")e.data="";if(typeof e.data==="string"){var r=e.data.length>0?e.data.split("&"):[];r.push("CSRF_TOKEN="+t);e.data=r.join("&")}else e.data.CSRF_TOKEN=t}});$.ajaxSetup({statusCode:{401:function(){$.Dialog.fail(u,"Cross-site Request Forgery attack detected. Please notify the site administartors.")},500:function(){$.Dialog.fail(false,'The request failed due to an internal server error.<br>If this persists, please <a href="<?=GITHUB_URL?>/issues" target="_blank">open an issue on GitHub</a>!')}}})})(window,document);</script>
</head>
<body>

	<header>
		<div id="topbar">
			<h1><a <?=$do==='index'?'class=active':'href=/'?>>MLP<span class=short>-VC</span><span class=long> Vector Club</span> Requests & Reservations</a></h1>
		</div>
		<nav><ul><?php
	$HeaderItems = array(
		array('/','<span>Home</span>','home'),
		'eps' => array('/episodes','Episodes'),
	);
	if ($do === 'episode' && !empty($CurrentEpisode))
		$HeaderItems['eps']['subitem'] = array($_SERVER['REQUEST_URI'], $title);
	if ($signedIn)
		$HeaderItems['u'] = array("/u/{$currentUser['name']}",'Account');
	if ($do === 'user' && !$sameUser)
		$HeaderItems[] = array($_SERVER['REQUEST_URI'], $title);
	if (PERM('inspector'))
		$HeaderItems[] = array('/config','Site configuration');
	if (PERM('logs.view')){
		$HeaderItems['logs'] = array('/logs', 'Logs');
		if ($do === 'logs')
			$HeaderItems['logs']['subitem'] = array($_SERVER['REQUEST_URI'], "Page $Page");
	}
	$HeaderItems[] = array('/about', 'About');

	$currentSet = false;
	foreach ($HeaderItems as $item){
		$sublink = '';
		if (isset($item['subitem'])){
			list($class, $sublink) = get_header_link($item['subitem']);
			$sublink = " &rsaquo; $sublink";
			$link = get_header_link($item, HTML_ONLY);
		}
		else list($class, $link) = get_header_link($item);
		echo "<li$class>$link$sublink</li>";
	}
	echo '<li><a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a></li>'; ?></ul></nav>
	</header>

	<div id=main>
		<div class="notice warn align-center">
			<p><span class="typcn typcn-warning"></span> The site is currently in the testing phase, <strong>ALL</strong> actions will be reset when we actually start using the site.</p>
		</div>