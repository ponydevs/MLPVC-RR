/* globals $body,Key,$w */
(function ($, undefined) {
	'use strict';
	function $makeDiv(id){ return $.mk('div').attr('id', id) }
	var colors = {
			fail: 'red',
			success: 'green',
			wait: 'blue',
			request: 'yellow',
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
		$dialogButtons = $('#dialogButtons');
	
	$.Dialog = (function(){
		var _open = $dialogContent.length,
			Dialog = {
				isOpen: function(){ return typeof _open === 'object' },
			};

		// Pre-defined dialogs
		Dialog.fail = function(title,content,callback){
			Display('fail',title,content,{
				'Close': function(){ Close() }
			},callback);
		};
		Dialog.success = function(title,content,closeBtn,callback){
			Display('success',title,content,(closeBtn === true ? {
				'Close': function(){ Close() }
			} : undefined), callback);
		};
		Dialog.wait = function(title,additional_info,callback){
			if (typeof additional_info === 'function' && callback === 'undefined'){
				callback = additional_info;
			}
			if (typeof additional_info !== 'string' || additional_info.length < 2)
				additional_info = 'Sending request';
			var content = $.capitalize(additional_info)+'&hellip;';
			Display('wait',title,content,callback);
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
			Display('info',title,content,{
				'Close': function(){ Close() }
			},callback);
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
			if ($focus.length > 0) _$focusedElement = $focus.last();
			else _$focusedElement = undefined;
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
		function _controlInputs(disable){
			$dialogContent.children(':not(:last-child)').find('input, select, textarea').attr('disabled',disable);
		}

		// Displaying dialogs
		function Display(type,title,content,buttons,callback) {
			if (typeof type !== 'string' || typeof colors[type] === 'undefined')
				throw new TypeError('Invalid dialog type: '+typeof type);

			if (typeof buttons === 'function' && typeof callback === 'undefined')
				callback = buttons;
			
			if (typeof title === 'undefined') title = defaultTitles[type];
			else if (title === false) title = undefined;
			if (typeof content === 'undefined') content = defaultContent[type];
			var params = {
				type: type,
				title: title,
				content: content||defaultContent[type],
				buttons: buttons,
				color: colors[type]
			};

			var append = Boolean(_open),
				$contentAdd = $makeDiv().addClass(params.color).append(params.content),
				appendingToRequest = append && _open.type === 'request' && ['fail','wait'].includes(params.type),
				$requestContentDiv;
			if (append){
				$dialogOverlay = $('#dialogOverlay');
				$dialogBox = $('#dialogBox');
				$dialogHeader = $('#dialogHeader');
				if (typeof params.title === 'string')
					$dialogHeader.text(params.title);
				$dialogContent = $('#dialogContent');

				if (appendingToRequest){
					$requestContentDiv = $dialogContent.children(':not(#dialogButtons)').last();
					var $ErrorNotice = $requestContentDiv.children('.notice');
					if (!$ErrorNotice.length){
						$ErrorNotice = $.mk('div').append($.mk('p'));
						$requestContentDiv.append($ErrorNotice);
					}
					$ErrorNotice
						.attr('class','notice '+noticeClasses[params.type])
						.children('p').html(params.content);
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

				$dialogContent.append($contentAdd);
				$dialogButtons = $makeDiv('dialogButtons').appendTo($dialogContent);
				$dialogBox.append($dialogHeader).append($dialogContent);
				$dialogOverlay.append($dialogBox).appendTo($body);

				$body.addClass('dialog-open');
			}

			if (!appendingToRequest)
				$dialogHeader.attr('class',params.color+'-bg');

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

			Dialog.center();
			_setFocus();

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
		Dialog.center = function(){
			if (typeof _open === 'undefined') return;

			var overlay = {w: $dialogOverlay.width(), h: $dialogOverlay.height()},
				dialog = {w: $dialogBox.outerWidth(true), h: $dialogBox.outerHeight(true)};
			$dialogBox.css({
				top: Math.max((overlay.h - dialog.h) / 2, 0),
				left: Math.max((overlay.w - dialog.w) / 2, 0),
			});
		};
		return Dialog;
	})();

	$w.on('resize', $.Dialog.center);
	$body.on('keydown',function(e){
		if (e.keyCode === 9 && $.Dialog.isOpen()){
			var $inputs = $dialogContent.find(':input'),
				idx = $inputs.index(e.target);

			if (e.shiftKey && idx === 0){
				e.preventDefault();
				$dialogButtons.find(':last').focus();
			}
			else if ($inputs.filter(':focus').length !== 1){
				e.preventDefault();
				$inputs.first().focus();
			}
			return true;
		}
	});
})(jQuery);
