<!DOCTYPE html>
<html lang="en">
<head>
	<title><?=(isset($title)?$title.' - ':'').SITE_TITLE?></title>
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
		<?=Notice('info','<span class="typcn typcn-info-large"></span> In order to preserve server resources our database was migrated to PostgreSQL. If some of your <em>very recent</em> requests/reservations are no longer visible, please addd them again as they likely got lost in the process. Sorrry for the inconvinience and thank you for your understanding. ~ DJDavid98', true)?>
