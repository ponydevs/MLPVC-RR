<?php
	$Title = (isset($title)?$title.' - ':'').SITE_TITLE;
	$Description = "Handling requests, reservations & the Color Guide since 2015";

	$ThumbImage = "/img/logo.png";
	if (isset($do) && $do === 'colorguide' && !empty($Appearance)){
		$sprite = \CG\Appearances::GetSpriteURL($Appearance['id']);
		if ($sprite)
			$ThumbImage = $sprite;

		$Description = 'Show accurate colors for "'.$Appearance['label'].'" from the MLP-VectorClub\'s Official Color Guide';
	}
	$ThumbImage = ABSPATH.ltrim($ThumbImage, '/');
	$Title = CoreUtils::EscapeHTML($Title);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title><?=$Title?></title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta property="og:type" content="website">
	<meta property="og:image" content="<?=$ThumbImage?>">
	<meta property="og:title" content="<?=$Title?>">
	<meta property="og:url" content="<?=ABSPATH.ltrim($_SERVER['REQUEST_URI'], '/')?>">
	<meta property="og:description" content='<?=CoreUtils::AposEncode($Description)?>'>
	<meta name="description" content='<?=CoreUtils::AposEncode($Description)?>'>
	<meta name="format-detection" content="telephone=no">
	<meta name="theme-color" content="#2C73B1">
	<link rel="image_src" href="<?=$ThumbImage?>">
	
	<link rel="apple-touch-icon" sizes="57x57" href="/img/favicons-v1/apple-touch-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="/img/favicons-v1/apple-touch-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="/img/favicons-v1/apple-touch-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="/img/favicons-v1/apple-touch-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="/img/favicons-v1/apple-touch-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="/img/favicons-v1/apple-touch-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="/img/favicons-v1/apple-touch-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="/img/favicons-v1/apple-touch-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="/img/favicons-v1/apple-touch-icon-180x180.png">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-32x32.png" sizes="32x32">
	<link rel="icon" type="image/png" href="/img/favicons-v1/android-chrome-192x192.png" sizes="192x192">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-96x96.png" sizes="96x96">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-16x16.png" sizes="16x16">
	<link rel="manifest" href="/img/favicons-v1/manifest.json">
	<link rel="mask-icon" href="/img/favicons-v1/safari-pinned-tab.svg" color="#2c73b1">
	<meta name="apple-mobile-web-app-title" content="MLP-VectorClub">
	<meta name="application-name" content="MLP-VectorClub">
	<meta name="msapplication-TileColor" content="#2c73b1">
	<meta name="msapplication-TileImage" content="/img/favicons-v1/mstile-144x144.png">
	<meta name="msapplication-config" content="/img/favicons-v1/browserconfig.xml">

	<link rel="shortcut icon" href="/favicon.ico">
<?php
	if (isset($norobots))
		echo'<meta name="robots" content="noindex, nofollow">';
	if (isset($customCSS)){
		foreach ($customCSS as $css)
			echo "<link rel='stylesheet' href='$css'>\n";
	}
	if (!empty(GA_TRACKING_CODE) && Permission::Insufficient('developer')){ ?>
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create','<?=GA_TRACKING_CODE?>','auto');
<?php   if ($signedIn && !UserPrefs::Get('p_disable_ga')){ ?>
ga('set', 'userId', '<?=$currentUser->id?>');
<?php   } ?>
ga('require','displayfeatures');
ga('send','pageview');
</script>
<?php } ?>
</head>
<body class="loading">

	<header>
		<nav><ul>
			<li class="sidebar-toggle">
				<svg class="loading-indicator" viewBox="0 0 45 45" version="1.1" xmlns="http://www.w3.org/2000/svg">
					<circle r="20" cx="22.5" cy="22.5" class="loading-circle"></circle>
				</svg>
				<div class="loader"></div>
	            <img class="avatar" src="<?=$signedIn?$currentUser->avatar_url:GUEST_AVATAR?>" alt='<?=($signedIn?CoreUtils::AposEncode(CoreUtils::Posess($currentUser->name)):'Guest').' avatar'?>'>
			</li><?=CoreUtils::GetNavigationHTML(isset($view) && $view === 'fatalerr')?></ul></nav>
	</header>

	<div id="sidebar">
<?php include "views/sidebar.php"; ?>
	</div>

	<div id="main">
