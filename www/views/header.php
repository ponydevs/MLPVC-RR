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
	<link rel="stylesheet" href="/css/<?=$css?>.css">
<?php } ?>
	<script src="/js/prefixfree.min.js" id="prefixfree"></script>
	<script src="//code.jquery.com/jquery-1.11.2.min.js"></script>
	<script>window.jQuery||document.write('\x3Cscript src="/js/jquery-1.11.2.min.js">\x3C/script>')</script>
	<script>(function(w,d,u){w.RELPATH='<?=RELPATH?>';$.ajaxPrefilter(function(e){var t,n=d.cookie.split("; ");$.each(n,function(e,n){n=n.split("=");if(n[0]==="CSRF_TOKEN"){t=n[1];return false}});if(typeof t!=="undefined"){if(typeof e.data==="undefined")e.data="";if(typeof e.data==="string"){var r=e.data.length>0?e.data.split("&"):[];r.push("CSRF_TOKEN="+t);e.data=r.join("&")}else e.data.CSRF_TOKEN=t}});$.ajaxSetup({statusCode:{401:function(){$.Dialog.fail(u,"Cross-site Request Forgery attack detected. Please notify the site administartors.")},500:function(){$.Dialog.fail(false,'The request failed due to an internal server error.<br>If this persists, please <a href="<?=GITHUB_URL?>/issues" target="_blank">open an issue on GitHub</a>!')}}})})(window,document);</script>
</head>
<body>

	<header>
		<div id="topbar">
			<h1><a href="/">MLP<span class=short>-VC</span><span class=long> Vector Club</span> Requests & Reservations</a></h1>
		</div>
		<nav><?php
	$HeaderItems = array(
		array('/','<span>Home</span>','home'),
		array('/episodes','Episodes'),
	);
	if ($signedIn){
		$HeaderItems[] = array("/u/{$currentUser['name']}",'Account');
		if (PERM('logs.view'))
			$HeaderItems[] = array($do == 'logs' ? $_SERVER['REQUEST_URI'] : '/logs', 'Logs');
	}
	$HeaderItems[] = array('/about', 'About');
	$currentSet = false;
	foreach ($HeaderItems as $item){
		list($path, $label) = $item;
		$current = !$currentSet && !!preg_match("~^$path($|/)~", $_SERVER['REQUEST_URI']);
		if ($current)
			$currentSet = true;
		$class = trim((!empty($item[2]) ? $item[2] : '').($current ? ' active' : ''));
		if (!empty($class))
			$class = " class='$class'";

		$href = $current ? '' : " href='$path'";

		echo "<a$href$class>$label</a>";
	}
	echo '<a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a>'; ?></nav>
	</header>

	<div id=main>
