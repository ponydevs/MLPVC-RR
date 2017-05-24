<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Color Picker - MLPVC-RR</title>
<link rel="stylesheet" href="<?=\App\CoreUtils::asset('colorpicker','scss/min','css')?>">
<script>if(parent===window){alert("You aren't supposed to open this file directly! You will be redirected after you click OK.");window.location.href='/cg/picker'}else var $ = parent.$;</script>
</head>
<body>

<ul id="menubar">
	<li>
		<a>File</a>
		<ul class="hidden">
			<li><a id="open-image">Open local file&hellip;</a></li>
			<li><a id="clear-image" class="disabled">Clear current image</a></li>
		</ul>
	</li>
</ul>
<div id="picker"></div>
<div id="statusbar">
	<div class="info"><span class="typcn typcn-info-large"></span> Use the File menu to open an image in the color picker</div>
	<ul class="pos">
		<li class="mouse"                 data-info="Image coordinates under the pointer"></li>
		<li class="debug image-top-left"  data-info="Image top left corner"></li>
		<li class="debug image-center"    data-info="Image center"></li>
		<li class="debug picker-center"   data-info="Picker center"></li>
	</ul>
	<div class="colorat"><span></span></div>
</div>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/min/jquery-3.2.1.js">\x3C/script>');</script>
<script src="<?=\App\CoreUtils::asset('jquery.ba-throttle-debounce','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('shared-utils','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('dialog','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('colorpicker','js/min','js')?>"></script>
</body>
</html>
