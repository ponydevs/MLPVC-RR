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
		<a id="file-menu" class="disabled">File</a>
		<ul class="hidden">
			<li><a id="open-image">Open&hellip;</a></li>
		</ul>
	</li>
	<li>
		<a id="file-menu" class="disabled">Tools</a>
		<ul class="hidden">
			<li><a id="open-image">Options&hellip;</a></li>
		</ul>
	</li>
</ul>
<div id="picker"></div>

<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script>if(!window.jQuery)document.write('\x3Cscript src="/js/min/jquery-3.2.1.js">\x3C/script>');</script>
<script src="<?=\App\CoreUtils::asset('jquery.ba-throttle-debounce','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('shared-utils','js/min','js')?>"></script>
<script src="<?=\App\CoreUtils::asset('colorpicker','js/min','js')?>"></script>
<script>
$('#picker').polyEditor({
	image: 'https://derpicdn.net/img/view/2015/10/31/1013575.jpg'
});
</script>
</body>
</html>
