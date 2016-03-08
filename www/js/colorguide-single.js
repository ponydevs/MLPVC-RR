/* global DocReady */
DocReady.push(function ColorguideSingle(){
	"use strict";
	window.copyHashToggler();

	var $adminBtns = $('.admin').children();
	$adminBtns.filter('.edit-cg').on('click',function(){
		$.ctxmenu.triggerItem($(this).parents('.ctxmenu-bound'), 1);
	});
	var $colors = $('#colors');
	$adminBtns.filter('.reorder-cgs').on('click',function(){
		$.ctxmenu.triggerItem($colors, 1);
	});
	$adminBtns.filter('.create-cg').on('click',function(){
		$.ctxmenu.triggerItem($colors, 2);
	});
});
