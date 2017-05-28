<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Color Picker - MLPVC-RR</title>
<link rel="stylesheet" href="<?=\App\CoreUtils::asset('colorpicker','scss/min','css')?>">
<script>if(parent===window){alert("You aren't supposed to open this file directly! You will be redirected after you click OK.");window.location.href='/cg/picker'}</script>
</head>
<body>

<ul id="menubar">
	<li>
		<a class="dropdown">File</a>
		<ul class="hidden">
			<li><a id="open-image">Open&hellip; <span class="kbd">(Ctrl+K)</span></a></li>
			<li><a id="close-active-tab" class="disabled">Close current tab</a></li>
		</ul>
	</li>
	<li>
		<a id="about-dialog">About</a>
	</li>
</ul>
<ul id="tabbar" class="dragscroll" data-info="If you open a lot of tabs you can drag on them to move around"></ul>
<div id="picker"></div>
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
	<p>Huge thanks to <strong>Masem</strong>, <strong>Pirill</strong> and <strong>Trildar</strong> for helping with the creation of this tool via code, feedback and ideas.</p>
</div>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/min/jquery-3.2.1.js">\x3C/script>');</script>
<script src="<?=\App\CoreUtils::asset('jquery.ba-throttle-debounce','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('shared-utils','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('dialog','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('md5','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('dragscroll','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('colorpicker','js/min','js')?>"></script>
</body>
</html>
