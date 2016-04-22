<?php
	$Title = (isset($title)?$title.' - ':'').SITE_TITLE;
	$Description = "Handling requests, reservations & the Color Guide since 2015";

	$ThumbImage = "/img/logo.png";
	if (isset($do) && $do === 'colorguide' && !empty($Appearance)){
		$sprite = get_sprite_url($Appearance);
		if ($sprite)
			$ThumbImage = $sprite;

		$Description = 'Show accurate colors for "'.$Appearance['label'].'" from the MLP-VectorClub\'s Official Color Guide';
	}
	$ThumbImage = ABSPATH.ltrim($ThumbImage, '/');

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
			</li><?=CoreUtils::GetNavigation()?></ul></nav>
	</header>

	<div id="sidebar">
<?php include "views/sidebar.php"; ?>
	</div>

	<div id="main">
