<?php
use App\Appearances;
use App\Auth;
use App\DeviantArt;
use App\JSON;
use App\Models\User;
use App\Permission;
use App\Users;
use App\CoreUtils;
use App\View;

/** @var $view App\View */
/** @var $Owner User */
/** @var $User User */
/** @var $scope array */

$Title = (isset($title)?$title.' - ':'').SITE_TITLE;
$Description = "Handling requests, reservations & the Color Guide since 2015";

$ThumbImage = "/img/logo.png";
switch ($do ?? null){
	case "cg":
		if (!empty($Appearance)){
			$sprite = Appearances::getSpriteURL($Appearance['id']);
			if ($sprite)
				$ThumbImage = $sprite;

			$Description = 'Show accurate colors for "'.Appearances::processLabel($Appearance['label']).'" from the MLP-VectorClub’s Official Color Guide';
		}
	break;
	case "u":
		if (!empty($Appearance)){
			$sprite = Appearances::getSpriteURL($Appearance['id']);
			if ($sprite)
				$ThumbImage = $sprite;
			else $ThumbImage = $Owner->avatar_url;

			$Description = 'Colors for "'.Appearances::processLabel($Appearance['label']).'" from '.CoreUtils::posess($Owner->name).' Personal Color Guide on the the MLP-VectorClub’s website';
		}
		else if (!empty($User)){
			$ThumbImage = $User->avatar_url;

			$Description = CoreUtils::posess($User->name).' profile on the MLP-VectorClub’s website';
		}
	break;
	case "s":
		if (!empty($LinkedPost)){
			$_oldTitle = $Title;
			if (!$LinkedPost->isFinished)
				$ThumbImage = $LinkedPost->preview;
			else {
				$finishdeviation = DeviantArt::getCachedDeviation($LinkedPost->deviation_id);
				if (!empty($finishdeviation->preview))
					$ThumbImage  = $finishdeviation->preview;
			}
			$Title = $LinkedPost->label;
			if ($LinkedPost->isRequest)
				$Description = 'A request';
			else {
				$_user = Users::get($LinkedPost->reserved_by,'id','name');
				$Description = 'A reservation'.(!empty($_user->name) ? " by {$_user->name}" : '');
			}
			$Description .= ' on the MLP-VectorClub’s website';
		}
	break;
}
if ($ThumbImage[0] === '/')
	$ThumbImage = ABSPATH.ltrim($ThumbImage, '/');
$Title = CoreUtils::escapeHTML($Title); ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title><?=$Title?></title>
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, maximum-scale=1, user-scalable=no">
	<meta property="og:type" content="website">
	<meta property="og:image" content="<?=$ThumbImage?>">
	<meta property="og:title" content="<?=$Title?>">
	<meta property="og:url" content="<?=ABSPATH.ltrim($_SERVER['REQUEST_URI'], '/')?>">
	<meta property="og:description" content='<?=CoreUtils::aposEncode($Description)?>'>
	<meta name="description" content='<?=CoreUtils::aposEncode($Description)?>'>
	<meta name="format-detection" content="telephone=no">
	<meta name="theme-color" content="#2C73B1">
	<link rel="image_src" href="<?=$ThumbImage?>">

	<link rel="apple-touch-icon" sizes="57x57" href="/apple-touch-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="/apple-touch-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="/apple-touch-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="/apple-touch-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="/apple-touch-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="/apple-touch-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="/apple-touch-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="/apple-touch-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon-180x180.png">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-32x32.png" sizes="32x32">
	<link rel="icon" type="image/png" href="/img/favicons-v1/android-chrome-192x192.png" sizes="192x192">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-96x96.png" sizes="96x96">
	<link rel="icon" type="image/png" href="/img/favicons-v1/favicon-16x16.png" sizes="16x16">
	<link rel="manifest" href="/manifest">
	<link rel="mask-icon" href="/img/favicons-v1/safari-pinned-tab.svg" color="#2c73b1">
	<meta name="apple-mobile-web-app-title" content="MLP-VectorClub">
	<meta name="application-name" content="MLP-VectorClub">
	<meta name="msapplication-TileColor" content="#2c73b1">
	<meta name="msapplication-TileImage" content="/img/favicons-v1/mstile-144x144.png">
	<meta name="msapplication-config" content="/browserconfig.xml">

	<link rel="shortcut icon" href="/favicon.ico">
<?php
	if (isset($norobots))
		echo'<meta name="robots" content="noindex, nofollow">';
	if (isset($redirectto))
		echo'<script>history.replaceState&&history.replaceState(history.state,"",'.JSON::encode($redirectto).')</script>'."\n";
	if (isset($_oldTitle))
		echo '<script>document.title='.JSON::encode($_oldTitle)."</script>\n";
	if (isset($customCSS)){
		foreach ($customCSS as $css)
			echo "<link rel='stylesheet' href='$css'>\n";
	}
	if (!empty(GA_TRACKING_CODE) && Permission::insufficient('developer')){ ?>
<!--suppress CommaExpressionJS -->
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
})(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

ga('create','<?=GA_TRACKING_CODE?>','auto');
ga('require','displayfeatures');
ga('send','pageview');
</script>
<?php } ?>
</head>
<body>

	<header>
		<nav><ul class="dragscroll">
			<li class="sidebar-toggle">
				<svg class="loading-indicator" viewBox="0 0 45 45" version="1.1" xmlns="http://www.w3.org/2000/svg">
					<circle r="20" cx="22.5" cy="22.5" class="loading-circle" transform="rotate(-90 22.5 22.5)"></circle>
				</svg>
				<div class="loader"></div>
			</li><?=CoreUtils::getNavigationHTML(isset($view) && $view === 'fatalerr', $scope)?>
		</ul></nav>
	</header>

	<aside id="sidebar">
<?php include INCPATH."views/_sidebar.php"; ?>
	</aside>

	<div id="main">
<?  if (isset($view) && $view instanceof View)
		require $view;
	else echo $mainContent;
?>
	</div>

	<footer><?=CoreUtils::getFooter(isset($view) && $view === 'fatalerr')?></footer>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/min/jquery-3.2.1.js">\x3C/script>');</script>
<?php
	echo CoreUtils::exportVars([
		'PRINTABLE_ASCII_PATTERN' => PRINTABLE_ASCII_PATTERN,
		'DocReady' => [],
		'signedIn' => Auth::$signed_in,
	]);
	if (isset($customJS)) foreach ($customJS as $js){
		echo "<script src='$js'></script>\n";
	} ?>
</body>
</html>
