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
		<a>File</a>
		<ul class="hidden">
			<li><a id="open-image">Open&hellip;</a></li>
			<li><a id="close-active-tab" class="disabled">Close current tab</a></li>
		</ul>
	</li>
</ul>
<ul id="tabbar" class="dragscroll" data-info="If you open a lot of tabs you can drag on them to move around"></ul>
<div id="picker"></div>
<div id="statusbar">
	<div class="info"><span class="typcn typcn-info-large"></span> Use the File menu to open an image in the color picker</div>
	<ul class="pos">
		<li class="mouse" data-info="Image coordinates under the pointer"></li>
	</ul>
	<div class="colorat">
		<span class="color"></span><span class="opacity"></span>
	</div>
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
