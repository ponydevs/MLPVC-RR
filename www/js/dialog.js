/* globals $body,Key,$w */
(function ($, undefined) {
	'use strict';
	function $makeDiv(id){ return $.mk('div').attr('id', id) }
	var colors = {
			fail: 'red',
			success: 'green',
			wait: 'blue',
			request: '',
			confirm: 'orange',
			info: 'darkblue'
		},
		noticeClasses = {
			fail: 'fail',
			success: 'success',
			wait: 'info',
			request: 'warn',
			confirm: 'caution',
			info: 'info',
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
		$dialogOverlay = $('#dialogOverlay'),
		$dialogContent = $('#dialogContent'),
		$dialogHeader = $('#dialogHeader'),
		$dialogBox = $('#dialogBox'),
		$dialogWrap = $('#dialogWrap'),
		$dialogScroll = $('#dialogScroll'),
		$dialogButtons = $('#dialogButtons');

	$.Dialog = (function(){
		var _open = $dialogContent.length ? {} : undefined,
			Dialog = {
				isOpen: function(){ return typeof _open === 'object' },
			},
			CloseButton = { Close: function(){ Close() } };

		// Dialog defintions
		Dialog.fail = function(title,content,force_new){
			Display('fail',title,content,CloseButton,force_new === true);
		};
		Dialog.success = function(title,content,closeBtn,callback){
			Display('success',title,content, (closeBtn === true ? CloseButton : undefined), callback);
		};
		Dialog.wait = function(title,additional_info,force_new){
			if (typeof additional_info === 'boolean' && typeof force_new === 'undefined'){
				force_new = additional_info;
				additional_info = undefined;
			}
			if (typeof additional_info !== 'string')
				additional_info = 'Sending request';
			Display('wait',title,$.capitalize(additional_info)+'&hellip;',force_new === true);
		};
		Dialog.request = function(title,content,formid,confirmBtn,callback){
			if (typeof confirmBtn === 'function' && typeof callback === 'undefined'){
				callback = confirmBtn;
				confirmBtn = undefined;
			}
			var buttons = {};
			if (formid)
				buttons[confirmBtn||'Submit'] = {
					'submit': true,
					'form': formid,
				};
			buttons.Cancel = function(){ Close() };

			Display('request',title,content,buttons,callback);
		};
		Dialog.confirm = function(title,content,btnTextArray,handlerFunc){
			if (typeof btnTextArray === 'function' && typeof handlerFunc === 'undefined')
				handlerFunc = btnTextArray;
			
			if (typeof handlerFunc !== 'function')
				handlerFunc = function(){ Close() };
			
			if (!$.isArray(btnTextArray)) btnTextArray = ['Eeyup','Nope'];
			var buttons = {};
			buttons[btnTextArray[0]] = function(){ handlerFunc(true) };
			buttons[btnTextArray[1]] = function(){ handlerFunc(false); Close.call() };
			Display('confirm',title,content,buttons);
		};
		Dialog.info = function(title,content,callback){
			Display('info',title,content,CloseButton,callback);
		};

		// Storing and restoring focus
		var _$focusedElement;
		Dialog.setFocusedElement = function($el){
			if ($el instanceof jQuery)
				_$focusedElement = $el;
		};
		function _storeFocus(){
			if (typeof _$focusedElement !== 'undefined' && _$focusedElement instanceof jQuery)
				return;
			var $focus = $(':focus');
			_$focusedElement = $focus.length > 0 ? $focus.last() : undefined;
		}
		function _restoreFocus(){
			if (typeof _$focusedElement !== 'undefined' && _$focusedElement instanceof jQuery){
				_$focusedElement.focus();
				_$focusedElement = undefined;
			}
		}
		function _setFocus(){
			var $inputs = $('#dialogContent').find('input,select,textarea').filter(':visible'),
				$actions = $('#dialogButtons').children();
			if ($inputs.length > 0) $inputs.first().focus();
			else if ($actions.length > 0) $actions.first().focus();
		}

		var DISABLE = true,
			ENABLE = false;
		function _controlInputs(action){
			var $inputs = $dialogContent
				.children(':not(#dialogButtons)')
				.last()
				.add($dialogButtons)
				.find('input, button, select, textarea');

			if (action === DISABLE)
				$inputs.filter(':not(:disabled)').addClass('temp-disable').attr('disabled',DISABLE);
			else $inputs.filter('.temp-disable').removeClass('temp-disable').attr('disabled',ENABLE);
		}

		// Displaying dialogs
		function Display(type,title,content,buttons,callback) {
			if (typeof type !== 'string' || typeof colors[type] === 'undefined')
				throw new TypeError('Invalid dialog type: '+typeof type);

			if (typeof buttons === 'function' && typeof callback !== 'function'){
				callback = buttons;
				buttons = undefined;
			}
			var force_new = false;
			if (typeof callback === 'boolean'){
				force_new = callback;
				callback = undefined;
			}
			else if (typeof buttons === 'boolean' && typeof callback === 'undefined'){
				force_new = buttons;
				buttons = undefined;
			}
			
			if (typeof title === 'undefined')
				title = defaultTitles[type];
			else if (title === false)
				title = undefined;
			if (!content)
				content = defaultContent[type];
			var params = {
				type: type,
				title: title,
				content: content||defaultContent[type],
				buttons: buttons,
				color: colors[type]
			};

			var append = Boolean(_open),
				$contentAdd = $makeDiv().append(params.content),
				appendingToRequest = append && _open.type === 'request' && ['fail','wait'].includes(params.type),
				$requestContentDiv;

			if (params.color.length)
				$contentAdd.addClass(params.color);
			if (append){
				$dialogOverlay = $('#dialogOverlay');
				$dialogBox = $('#dialogBox');
				$dialogHeader = $('#dialogHeader');
				if (typeof params.title === 'string')
					$dialogHeader.text(params.title);
				$dialogContent = $('#dialogContent');

				if (appendingToRequest && !force_new){
					$requestContentDiv = $dialogContent.children(':not(#dialogButtons)').last();
					var $ErrorNotice = $requestContentDiv.children('.notice');
					if (!$ErrorNotice.length){
						$ErrorNotice = $.mk('div').append($.mk('p'));
						$requestContentDiv.append($ErrorNotice);
					}
					$ErrorNotice
						.attr('class','notice '+noticeClasses[params.type])
						.children('p').html(params.content).show();
					_controlInputs(params.type === 'wait' ? DISABLE : ENABLE);
				}
				else {
					_open = params;
					$dialogButtons = $('#dialogButtons').empty();
					_controlInputs(DISABLE);
					$dialogContent.append($contentAdd);

					if (params.buttons){
						if ($dialogButtons.length === 0)
							$dialogButtons = $makeDiv('dialogButtons');
						$dialogButtons.appendTo($dialogContent);
					}
				}
			}
			else {
				_storeFocus();
				_open = params;

				$dialogOverlay = $makeDiv('dialogOverlay');
				$dialogHeader = $makeDiv('dialogHeader').text(params.title||defaultTitles[type]);
				$dialogContent = $makeDiv('dialogContent');
				$dialogBox = $makeDiv('dialogBox');
				$dialogScroll = $makeDiv('dialogScroll');
				$dialogWrap = $makeDiv('dialogWrap');

				$dialogContent.append($contentAdd);
				$dialogButtons = $makeDiv('dialogButtons').appendTo($dialogContent);
				$dialogBox.append($dialogHeader).append($dialogContent);
				$dialogOverlay.append(
					$dialogScroll.append(
						$dialogWrap.append($dialogBox)
					)
				).appendTo($body);

				$body.addClass('dialog-open');
			}

			if (!appendingToRequest){
				$dialogHeader.attr('class',params.color+'-bg');
				$dialogContent.attr('class',params.color+'-border');
			}

			if (!appendingToRequest && params.buttons) $.each(params.buttons, function (name, obj) {
				var $button = $.mk('input').attr({
					'type': 'button',
					'class': params.color+'-bg'
				});
				if (typeof obj === 'function')
					obj = {action: obj};
				else if (obj.form){
					$requestContentDiv = $('#'+obj.form);
					if ($requestContentDiv.length === 1){
						$button.on('click', function(){
							$requestContentDiv.find('input[type=submit]').trigger('click');
						});
						$requestContentDiv.prepend($.mk('input').attr('type','submit').hide());
					}
				}
				$button.val(name).on('keydown', function (e) {
					if ([Key.Enter, Key.Space].indexOf(e.keyCode) !== -1){
						e.preventDefault();

						$button.trigger('click');
					}
					else if ([Key.Tab, Key.LeftArrow, Key.RightArrow].includes(e.keyCode)){
						e.preventDefault();

						var $dBc = $dialogButtons.children(),
							$focused = $dBc.filter(':focus'),
							$inputs = $dialogContent.find(':input');

						if ($.isKey(Key.LeftArrow, e))
							e.shiftKey = true;

						if ($focused.length){
							if (!e.shiftKey){
								if ($focused.next().length) $focused.next().focus();
								else if ($.isKey(Key.Tab, e)) $inputs.add($dBc).filter(':visible').first().focus();
							}
							else {
								if ($focused.prev().length) $focused.prev().focus();
								else if ($.isKey(Key.Tab, e)) ($inputs.length > 0 ? $inputs : $dBc).filter(':visible').last().focus();
							}
						}
						else $inputs.add($dBc)[!e.shiftKey ? 'first' : 'last']().focus();
					}
				}).on('click', function (e) {
					e.preventDefault();

					$.callCallback(obj.action, [e]);
				});
				$dialogButtons.append($button);
			});
			_setFocus();
			$w.trigger('dialog-opened');

			$.callCallback(callback, [$requestContentDiv]);
		}

		// Close dialog
		function Close(callback) {
			if (!Dialog.isOpen())
				return $.callCallback(callback, false);

			$dialogOverlay.remove();
			_open = undefined;
			_restoreFocus();
			$.callCallback(callback);

			$body.removeClass('dialog-open');
		}
		Dialog.close = function(){ Close.apply(Dialog, arguments) };
		Dialog.clearNotice = function(regexp){
			var $notice = $dialogContent.find('.notice');
			if (!$notice.length)
				return false;

			if (typeof regexp === 'undefined'){
				$notice.hide();
				return true;
			}

			if (regexp.test($notice.html())){
				$notice.hide();
				return true;
			}
			return false;
		};
		return Dialog;
	})();

	$body.on('keydown',function(e){
		if (!$.Dialog.isOpen() || e.keyCode !== Key.Tab)
			return true;

		var $inputs = $dialogContent.find(':input'),
			$focused = $inputs.filter(e.target),
			idx = $inputs.index($focused);

		if ($focused.length === 0){
			e.preventDefault();
			$inputs.first().focus();
		}
		else if (e.shiftKey){
			if (idx === 0){
				e.preventDefault();
				$dialogButtons.find(':last').focus();
			}
			else {
				var $parent = $focused.parent();
				if (!$parent.is($dialogButtons))
					return true;
				if ($parent.children().first().is($focused)){
					e.preventDefault();
					$inputs.eq($inputs.index($focused)-1).focus();
				}
			}
		}
	});
})(jQuery);
