<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Color Picker - MLPVC-RR</title>
<link rel="stylesheet" href="<?=\App\CoreUtils::cachedAssetLink('colorpicker','scss/min','css')?>">
</head>
<body>

<ul id="menubar">
	<li>
		<a class="dropdown">File</a>
		<ul class="hidden">
			<li><a id="open-image">Open&hellip; <span class="kbd">(Ctrl+O)</span></a></li>
			<li><a id="paste-image">Open from Clipboard&hellip; <span class="kbd">(Ctrl+Shift+O)</span></a></li>
		</ul>
	</li>
	<li>
		<a class="dropdown">Tools</a>
		<ul class="hidden">
			<li><a id="clear-settings">Clear settings</a></li>
			<li class="toggle"><a id="levels-dialog-toggle">levels dialog</a></li>
		</ul>
	</li>
	<li>
		<a id="about-dialog">About</a>
	</li>
</ul>
<ul id="tabbar" class="dragscroll" data-info="If you open a lot of tabs you can drag on them to move around"></ul>
<div id="picker"></div>
<div id="areas-sidebar">
	<span class="label">Picking area list</span>
</div>
<div id="statusbar">
	<div class="info"><span class="fa fa-info"></span> Use the File menu or drag &amp; drop images to open them for color picking</div>
	<ul class="pos">
		<li class="mouse" data-info="Image coordinates under the pointer"></li>
	</ul>
	<div class="colorat" data-info="Color and opacity of the pixel under the pointer">
		<span class="color"></span><span class="opacity"></span>
	</div>
</div>

<div class="hidden" id="about-dialog-template">
	<p>This color picker is meant to serve as an easy way to get accurate color readings from screencaps taken from the show. The interface attempts to mimic the controls and look of Adobe Photoshop, while giving you the ability to use multiple picking points with variable sizes across as many images as your browser can handle.</p>
	<p>While hovering over certain parts of the interface some additional information may be displayed in the bottom left telling you what it does to make familiarizing yourself with the controls easier.</p>
	<p>Huge thanks to <strong>Discorded</strong>, <strong>Masem</strong>, <strong>Pirill</strong> and <strong>Trildar</strong> for helping with the creation of this tool via code, feedback and ideas.</p>
</div>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('jquery.ba-throttle-debounce','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('shared-utils','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('dialog','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('md5','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('dragscroll','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('canvas.hdr','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('nouislider','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('paste','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::cachedAssetLink('colorpicker','js/min','js')?>"></script>
</body>
</html>
