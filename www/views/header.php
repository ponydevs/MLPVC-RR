<?php
	$Title = (isset($title)?$title.' - ':'').SITE_TITLE;

	$ThumbImage = "/img/logo.png";
	if (isset($do) && $do === 'colorguide' && !empty($Appearance)){
		$sprite = get_sprite_url($Appearance);
		if ($sprite)
			$ThumbImage = $sprite;
	}
	$ThumbImage = ABSPATH.ltrim($ThumbImage, '/');

	$Description = "An automated system for handling requests &amp; reservations, made for MLP-VectorClub";
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
	<meta property="og:description" content="<?=$Description?>">
	<meta name="description" content="<?=$Description?>">
	<meta name="format-detection" content="telephone=no">
	<link rel="image_src" href="<?=$ThumbImage?>">

<?php if (isset($norobots)){ ?>
	<meta name="robots" content="noindex, nofollow">
<?php } ?>
	<link rel="shortcut icon" href="/favicon.ico">
<?php if (isset($customCSS)) foreach ($customCSS as $css){ ?>
	<link rel="stylesheet" href="<?=$css?>">
<?php } ?>
</head>
<body class="loading">

	<header>
		<nav><ul>
			<li class="sidebar-toggle">
				<div class="loader"></div>
	            <img class="avatar" src="<?=$signedIn?$currentUser['avatar_url']:GUEST_AVATAR?>">
			</li><?=get_nav_html()?></ul></nav>
	</header>

	<div id="sidebar">
<?php include "views/sidebar.php"; ?>
	</div>

	<div id="main">
