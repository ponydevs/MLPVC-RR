<!DOCTYPE html>
<html lang="en">
<head>
	<title><?=isset($title)?$title.' - ':''?>Vector Club Requests & Reservations</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta property="og:type" content="website" />
	<meta property="og:image" content="<?=ABSPATH?>img/logo.png">
	<meta property="og:title" content="MLP Vector Club Requests &amp; Reservations">
	<meta property="og:url" content="<?=ABSPATH?>">
	<meta property="og:description" content="An automated system for handling requests &amp; reservations, made for MLP-VectorClub">
<?php if (isset($norobots)){ ?>
	<meta name="robots" content="noindex, nofollow">
<?php } ?>
	<link rel="shortcut icon" href="/favicon.ico">
<?php if (isset($customCSS)) foreach ($customCSS as $css){ ?>
	<link rel="stylesheet" href="/css/<?=$css?>.css?<?=filemtime(APPATH."/css/$css.css")?>">
<?php } ?>
</head>
<body>

	<header>
		<div id="topbar">
			<h1<?=date('Y-m-d') === '2015-06-27' ? ' class=pride title="Today, same-sex marriage was legalized in the USA"':''?>><a <?=$do==='index'?'class=active':'href=/'?>>MLP<span class=short>-VC</span><span class=long> Vector Club</span> Requests & Reservations</a></h1>
		</div>
		<nav><ul><?php
	$HeaderItems = array(
		array('/','<span>Home</span>','home'),
		'eps' => array('/episodes','Episodes'),
	);
	if ($do === 'episode' && !empty($CurrentEpisode))
		$HeaderItems['eps']['subitem'] = array($_SERVER['REQUEST_URI'], $title);
	if (PERM('inspector')){
		$HeaderItems['colorguide'] = array("/{$color}guide", "$Color Guide");
		if ($do === 'colorguide')
			$HeaderItems['colorguide']['subitem'] = array($_SERVER['REQUEST_URI'], (isset($Tags) ? 'Tags - ':'')."Page $Page");
	}
	if ($signedIn)
		$HeaderItems['u'] = array("/u/{$currentUser['name']}",'Account');
	if ($do === 'user' && !$sameUser)
		$HeaderItems[] = array($_SERVER['REQUEST_URI'], $title);
	if (PERM('inspector')){
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
