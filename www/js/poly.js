/* global DocReady */
DocReady.push(function Poly(){
	'use strict';
	$._editor = $('#poly-editor').polyEditor({
		image: 'https://derpicdn.net/img/view/2015/10/31/1013575.jpg'
	});
},function(){
	'use strict';
	$._editor.destroy();
});
