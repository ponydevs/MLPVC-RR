(function ($, undefined) {
	function paramMake(c,d,o,cb){return{color:c,draggable:typeof d==="undefined"?false:d,overlay: typeof o==="undefined"?true:o,closeButton:typeof cb==="undefined"?false:cb}}
	function $makeDiv(){ return $(document.createElement('div')) }
	var $html = $('html'),
		globalparams = {
			fail: paramMake('red'),
			success: paramMake('green'),
			wait: paramMake('blue'),
			request: paramMake('yellow',true),
			confirm: paramMake('orange'),
			info: paramMake('darkblue')
		},
		defaultTitles = {
			fail: 'Error',
			success: 'Success',
			wait: 'Sending request',
			request: 'Input required',
			confirm: 'Confirmation',
			info: 'Info',
		},
		defaultContent = {
			fail: 'There was an issue while processing the request.',
			success: 'Whatever you just did, it was completed successfully.',
			request: 'The request did not require any additional info.',
			confirm: 'Are you sure?',
			info: 'No message provided.',
		},
		xtraCSS = {
			fail: { color: '#E11' },
			success: { color: '#128023' },
			wait: { color: '#4390df' },
			request: { color: '#c29008' },
			confirm: { color: '#bf5a15' },
			info: { color: '#16499a' },
		},
		$w = $(window), $dialogOverlay, $dialogContent, $dialogHeader, $dialogBox, $dialogButtons;
	
	$.Dialog = {
		open: undefined,
		fail: function(title,content,callback){
			$.Dialog.display('fail',title,content,{
				'Close': {
					'action': function(){
						$.Dialog.close();
					}
				}
			},callback);
		},
		success: function(title,content,closeBtn,callback){
			$.Dialog.display('success',title,content,(closeBtn === true ? {
				'Close': {
					'action': function(){
						$.Dialog.close();
					}
				}
			} : undefined), callback);
		},
		wait: function(title,additional_info,callback){
			if (typeof additional_info === 'function' && callback === 'undefined'){
				callback = additional_info;
			}
			if (typeof additional_info !== 'string' || additional_info.length < 2) additional_info = 'Sending request';
			$.Dialog.display('wait',title,additional_info[0].toUpperCase()+additional_info.substring(1)+'...',callback);
		},
		request: function(title,content,formid,caption,callback){
			if (typeof caption === 'function' && typeof callback === 'undefined'){
				callback = caption;
				caption = 'Elküld';
			}
			var obj = {};
			obj[caption] = {
				'submit': true,
				'form': formid,
			};
			obj['Cancel'] = {
				'action': function(){
					$.Dialog.close();
				},
			};

			$.Dialog.display('request',title,content,obj, callback);
		},
		confirm: function(title,content,btnTextArray,handlerFunc){
			if (typeof btnTextArray === 'function' && typeof handlerFunc === 'undefined')
				handlerFunc = btnTextArray;
			
			if (typeof handlerFunc !== 'function') handlerFunc = new Function();
			
			if (!$.isArray(btnTextArray)) btnTextArray = ['Eeyup','Nope'];
			var buttonsObj = {};
			buttonsObj[btnTextArray[0]] = {'action': function(){ handlerFunc(true) }};
			buttonsObj[btnTextArray[1]] = {'action': function(){ handlerFunc(false); $.Dialog.close() }};
			$.Dialog.display('confirm',title,content,buttonsObj);
		},
		info: function(title,content,callback){
			$.Dialog.display('info',title,content,{
				'Close': {
					'action': function(){
						$.Dialog.close();
					}
				}
			},callback);
		},
		display: function (type,title,content,buttons,params,callback) {
			if (typeof type !== 'string' || typeof globalparams[type] === 'undefined') throw new TypeError('Invalid dialog type: '+typeof type);
			
			function setFocus(){
				var $focus = $(':focus');
				if ($focus.length > 0) window._focusedElement = $focus.last();
				else window._focusedElement = undefined;
				var $inputs = $('#dialogContent').find('input,select,textarea').filter(':visible'),
					$actions = $('#dialogButtons').children();
				if ($inputs.length > 0) $inputs.first().focus();
				else if ($actions.length > 0) $actions.first().focus();
			}
			
			function run(norender){
				var $contentAdd = $makeDiv();
				if (norender === true){
					$dialogOverlay = $('#dialogOverlay');
					$dialogBox = $('#dialogBox');
					$dialogHeader = $('#dialogHeader');
					$dialogContent = $('#dialogContent');
					$dialogButtons = $('#dialogButtons');
					if (typeof params.title === 'string')
						$dialogHeader.text(params.title);
					$dialogContent.find('input, select, textarea').attr('disabled',true);
					if (typeof xtraCSS[type] === 'object')
						$contentAdd.css(xtraCSS[type]);
					$dialogContent.append($contentAdd.html(params.content));
					if (params.buttons && $dialogButtons.length === 0)
						$dialogButtons = $makeDiv().attr('id','dialogButtons').insertAfter($dialogContent);
					else if (!params.buttons && $dialogButtons.length !== 0)
						$dialogButtons.remove();
					else $dialogButtons.empty();

					$dialogBox.stop();
					setTimeout(function(){
						$dialogBox.css({
							left: ($w.width() - $dialogBox.outerWidth()) / 2,
							top: ($w.height() - $dialogBox.outerHeight()) / 2,
							opacity: 1,
						});
					},10);
				}
				else {
					$dialogOverlay = $makeDiv().attr('id','dialogOverlay');
					$dialogHeader = $makeDiv().attr('id','dialogHeader').text(params.title||defaultTitles[type]);
					$dialogContent = $makeDiv().attr('id','dialogContent');
					$dialogBox = $makeDiv().attr('id','dialogBox');

					$contentAdd.html(params.content);
					if (typeof xtraCSS[type] === 'object') $contentAdd.css(xtraCSS[type]);
					$dialogContent.append($contentAdd);

					$dialogBox.append($dialogHeader).append($dialogContent);
					if (params.buttons)
						$dialogButtons = $makeDiv().attr('id','dialogButtons').insertAfter($dialogContent);
					$dialogOverlay.appendTo(document.body).css('opacity', .5).show();

					$dialogBox.css('opacity', 0).appendTo($dialogOverlay);

					var whdBoH = $w.height() - $dialogBox.outerHeight();

					setTimeout(function(){
						if ($dialogBox.is(':animated')) return;
						$dialogBox.css({
							top: whdBoH / 3,
							left: whdBoH / 2,
						}).animate({
							top: whdBoH / 2,
							opacity: 1,
						}, 350, setFocus);
						$dialogOverlay.fadeTo(350, 1);
					}, 100);

					var hOf = $html.css('overflow');
					$html.attr('data-overflow',hOf).css('overflow','hidden');
				}
				
				$dialogHeader.attr('class',params.color+'-bg');
				
				if (params.buttons) $.each(params.buttons, function (name, obj) {
					var $button = $(document.createElement('input'));
					if (obj.submit) $button.attr('type','submit');
					else $button.attr('type','button');
					$button.attr('class',params.color+'-bg');
					if (obj.form){
						var $form = $('#'+obj.form);
						if ($form.length == 1){
							$button.click(function(){
								$form.find('input[type=submit]').trigger('click');
							});
							$form.prepend($(document.createElement('input')).attr('type','submit').hide());
						}
					}
					$button.val(name).on('keydown', function (e) {
						if ([13, 32].indexOf(e.keyCode) !== -1){
							e.preventDefault();
							e.stopPropagation();
							
							$button.trigger('click');
						}
						else if (e.keyCode === 9){
							e.preventDefault();
							e.stopPropagation();
							
							var $dBc = $dialogButtons.children(),
								$focused = $dBc.filter(':focus'),
								$inputs = $dialogContent.find(':input');
								
							if ($focused.length){
								if (!e.shiftKey){
									if ($focused.next().length) $focused.next().focus();
									else $inputs.add($dBc).first().focus();
								}
								else {
									if ($focused.prev().length) $focused.prev().focus();
									else ($inputs.length > 0 ? $inputs : $dBc).last().focus();
								}
							}
							else $inputs.add($dBc)[!e.shiftKey ? 'first' : 'last']().focus();
						}
					}).on('click', function (e) {
						e.preventDefault();
						e.stopPropagation();
						
						if (typeof obj.action === 'function') obj.action(e);

						if (obj.type === 'close') $.Dialog.close(typeof obj.callback === 'function' ? obj.callback : undefined);
					});
					$dialogButtons.append($button);
				});

				setTimeout(function(){
					$dialogBox.css({
						top: ($w.height() - $dialogBox.outerHeight()) / 2,
						left: ($w.width() - $dialogBox.outerWidth()) / 2,
					});
				},100);
				
				if (params.draggable){
					$dialogHeader.css('cursor', 'move').on('mousedown',function (e) {
						e.preventDefault();
						e.stopPropagation();

						var drg_h = $dialogBox.outerHeight(),
							drg_w = $dialogBox.outerWidth(),
							pos_y = $dialogBox.offset().top + drg_h - e.pageY,
							pos_x = $dialogBox.offset().left + drg_w - e.pageX;

						$dialogOverlay.on("mousemove", function (e) {
							var t = (e.pageY > 0) ? (e.pageY + pos_y - drg_h) : (0);
							var l = (e.pageX > 0) ? (e.pageX + pos_x - drg_w) : (0);

							if (t >= 0 && t <= window.innerHeight + e.pageY) {
								$dialogBox.offset({
									top: t
								});
							}
							if (l >= 0 && l <= window.innerWidth + e.pageY) {
								$dialogBox.offset({
									left: l
								});
							}
						});
					}).on('click', function(e){
						e.preventDefault();
						e.stopPropagation();

						$dialogOverlay.off("mousemove");
					});
				}

				setFocus();

				if (typeof callback === 'function') callback();
			}
			
			if (typeof buttons == "function" && typeof params == "undefined" && typeof callback == 'undefined')
				callback = buttons;
			else if (typeof buttons == "object" && typeof params == "function" && typeof callback == 'undefined')
				callback = params;
			if (typeof params == "undefined") params = {};
			
			if (typeof title === 'undefined') title = defaultTitles[type];
			else if (title === false) title = undefined;
			if (typeof content === 'undefined') content =defaultContent[type];
			params = $.extend(true, {
				type: type,
				title: title,
				content: content,
				buttons: buttons,
			}, globalparams[type], params);

			if (typeof $.Dialog.open == "undefined"){
				$.Dialog.open = params;
				run();
			}
			else run(!!$.Dialog.open);
		},
		close: function (callback) {
			if (typeof $.Dialog.open === "undefined") return (typeof callback == 'function' ? callback() : false);

			$dialogOverlay.fadeOut(350, function(){
				$.Dialog.open = void(0);
				$(this).remove();
				if (window._focusedElement instanceof jQuery) window._focusedElement.focus();
				if (typeof callback == 'function') callback();
			});
			$dialogBox.animate({
				top: ($w.height() - $dialogBox.outerHeight()) / 3 * 2,
				opacity: 0,
			}, 350);
			var hOf = $html.attr('data-overflow');
			if (typeof hOf !== 'undefined'){
				$html.css('overflow',hOf);
				$html.removeAttr('data-overflow');
			}
		}
	};

	$w.on('resize',function(){
		if (typeof $.Dialog.open !== 'undefined') {
			$dialogBox.css("top", ($w.height() - $dialogBox.outerHeight()) / 2);
			$dialogBox.css("left", ($w.width() - $dialogBox.outerWidth()) / 2);
		}
	}).on('ajaxerror',function(){
		$.Dialog.fail(false,'There was an error while processing your request. You may find additional details in the browser\'s console.');
	});
	$(document.body).on('keydown',function(e){
		if (e.keyCode === 9 && typeof $.Dialog.open !== 'undefined'){
			var $this = $(e.target),
				$inputs = $('#dialogContent').find(':input'),
				idx = $this.index('#dialogContent :input');

			if (e.shiftKey && idx === 0){
				e.preventDefault();
				$('#dialogButtons').find(':last').focus();
			}
			else if ($inputs.filter(':focus').length !== 1){
				e.preventDefault();
				$inputs.first().focus();
			}
		}
	});
})(jQuery);