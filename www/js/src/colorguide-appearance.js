/* global DocReady */
DocReady.push(function(){
	"use strict";

	window.copyHashToggler();

	const $colors = $('#colors');
	$('.color-list').on('click','.reorder-cgs',function(e){
		e.preventDefault();
		$.ctxmenu.triggerItem($colors, 1);
	}).on('click','.create-cg',function(e){
		e.preventDefault();
		$.ctxmenu.triggerItem($colors, 2);
	});
	$colors.on('click','button.edit-cg',function(){
		$.ctxmenu.triggerItem($(this).parents('.ctxmenu-bound'), 1);
	}).on('click','button.delete-cg',function(){
		$.ctxmenu.triggerItem($(this).parents('.ctxmenu-bound'), 2);
	});
});
