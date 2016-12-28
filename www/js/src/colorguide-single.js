/* global DocReady */
DocReady.push(function(){
	"use strict";

	window.copyHashToggler();

	let $colors = $('#colors');
	$colors.on('click','button.edit-cg',function(){
		$.ctxmenu.triggerItem($(this).parents('.ctxmenu-bound'), 1);
	});
	$colors.on('click','.reorder-cgs',function(){
		$.ctxmenu.triggerItem($colors, 1);
	});
	$colors.on('click','.create-cg',function(){
		$.ctxmenu.triggerItem($colors, 2);
	});
});
