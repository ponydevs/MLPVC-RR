<!DOCTYPE html>
<html lang="en">
<head>
	<title><?=isset($title)?$title.' - ':''?>Vector Club Requests & Reservations</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
	<link rel="shortcut icon" href="<?=djpth('favicon.ico').'?'.filemtime(APPATH.'favicon.ico')?>">
<?php if (isset($customCSS)) foreach ($customCSS as $css){ ?>
	<link rel="stylesheet" href="<?=djpth('css>'.(!preg_match('/\.js\.php$/',strtok($css,'?')) ? "$css.css" : $css))?>">
<?php } ?>
	<script src="<?=djpth('js>prefixfree.min.js')?>" id="prefixfree"></script>
	<script src="<?=djpth('js>jquery.min.js')?>"></script>
	<script>(function(w,d){w.RELPATH='<?=RELPATH?>';$.ajaxPrefilter(function(e){var t,n=d.cookie.split("; ");$.each(n,function(e,n){n=n.split("=");if(n[0]==="CSRF_TOKEN"){t=n[1];return false}});if(typeof t!=="undefined"){if(typeof e.data==="undefined")e.data="";if(typeof e.data==="string"){var r=e.data.length>0?e.data.split("&"):[];r.push("CSRF_TOKEN="+t);e.data=r.join("&")}else e.data.CSRF_TOKEN=t}});$.ajaxSetup({statusCode:{401:function(){$.Dialog.fail(void 0,"Cross-site Request Forgery attack detected. Please notify the site administartors.")},500:function(){$.Dialog.fail(false,'The request failed due to an internal server error.<br>If this persists, please <a href="<?=GITHUB_URL?>/issues" target="_blank">open an issue on GitHub</a>!')}}})})(window,document);</script>
</head>
<body>

	<header>
		<div id="topbar">
			<h1>MLP<span class=short>-VC</span><span class=long> Vector Club</span> Requests & Reservations</h1>
		</div>
		<nav>
			<a href="<?=djpth()?>">Home</a>
			<a href="<?=djpth('episodes')?>">Episodes</a>
			<a href="<?=djpth('about')?>">About</a>
			<a href="http://mlp-vectorclub.deviantart.com/">MLP-VectorClub</a>
		</nav>
	</header>

	<div id=main class="clearfix">
		<div class="notice warn align-center">
			<p><strong>Important!</strong> This project has not yet been approved as official. Until that happens, this website is not maintained by nor affiliated with MLP-VectorClub.</p>
		</div>