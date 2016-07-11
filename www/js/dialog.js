/* globals $body,Key,$w */
(function ($, undefined) {
	'use strict';
	let colors = {
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
		};

	class Dialog {
		constructor(){
			this.$dialogOverlay = $('#dialogOverlay');
			this.$dialogContent = $('#dialogContent');
			this.$dialogHeader = $('#dialogHeader');
			this.$dialogBox = $('#dialogBox');
			this.$dialogWrap = $('#dialogWrap');
			this.$dialogScroll = $('#dialogScroll');
			this.$dialogButtons = $('#dialogButtons');
			this._open = this.$dialogContent.length ? {} : undefined;
			this._CloseButton = { Close: function(){ $.Dialog.close() } };
			this._$focusedElement = undefined;
		}

		isOpen(){ return typeof this._open === 'object' }

		_display(type,title,content,buttons,callback){
			if (typeof type !== 'string' || typeof colors[type] === 'undefined')
				throw new TypeError('Invalid dialog type: '+typeof type);

			if (typeof buttons === 'function' && typeof callback !== 'function'){
				callback = buttons;
				buttons = undefined;
			}
			let force_new = false;
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
			let params = {
				type: type,
				title: title,
				content: content||defaultContent[type],
				buttons: buttons,
				color: colors[type]
			};

			let append = Boolean(this._open),
				$contentAdd = $.mk('div').append(params.content),
				appendingToRequest = append && this._open.type === 'request' && ['fail','wait'].includes(params.type) && !force_new,
				$requestContentDiv;

			if (params.color.length)
				$contentAdd.addClass(params.color);
			if (append){
				this.$dialogOverlay = $('#dialogOverlay');
				this.$dialogBox = $('#dialogBox');
				this.$dialogHeader = $('#dialogHeader');
				if (typeof params.title === 'string')
					this.$dialogHeader.text(params.title);
				this.$dialogContent = $('#dialogContent');

				if (appendingToRequest){
					$requestContentDiv = this.$dialogContent.children(':not(#dialogButtons)').last();
					let $ErrorNotice = $requestContentDiv.children('.notice:last-child');
					if (!$ErrorNotice.length){
						$ErrorNotice = $.mk('div').append($.mk('p'));
						$requestContentDiv.append($ErrorNotice);
					}
					else $ErrorNotice.show();
					$ErrorNotice
						.attr('class','notice '+noticeClasses[params.type])
						.children('p').html(params.content).show();
					this._controlInputs(params.type === 'wait');
				}
				else {
					this._open = params;
					this.$dialogButtons = $('#dialogButtons').empty();
					this._controlInputs(true);
					this.$dialogContent.append($contentAdd);

					if (params.buttons){
						if (this.$dialogButtons.length === 0)
							this.$dialogButtons = $.mk('div','dialogButtons');
						this.$dialogButtons.appendTo(this.$dialogContent);
					}
				}
			}
			else {
				this._storeFocus();
				this._open = params;

				this.$dialogOverlay = $.mk('div','dialogOverlay');
				this.$dialogHeader = $.mk('div','dialogHeader').text(params.title||defaultTitles[type]);
				this.$dialogContent = $.mk('div','dialogContent');
				this.$dialogBox = $.mk('div','dialogBox');
				this.$dialogScroll = $.mk('div','dialogScroll');
				this.$dialogWrap = $.mk('div','dialogWrap');

				this.$dialogContent.append($contentAdd);
				this.$dialogButtons = $.mk('div','dialogButtons').appendTo(this.$dialogContent);
				this.$dialogBox.append(this.$dialogHeader).append(this.$dialogContent);
				this.$dialogOverlay.append(
					this.$dialogScroll.append(
						this.$dialogWrap.append(this.$dialogBox)
					)
				).appendTo($body);

				$body.addClass('dialog-open');
			}

			if (!appendingToRequest){
				this.$dialogHeader.attr('class',params.color ? `${params.color}-bg` : '');
				this.$dialogContent.attr('class',params.color ? `${params.color}-border` : '');
			}

			let classScope = this;
			if (!appendingToRequest && params.buttons) $.each(params.buttons, (name, obj) => {
				let $button = $.mk('input').attr({
					'type': 'button',
					'class': params.color+'-bg'
				});
				if (typeof obj === 'function')
					obj = {action: obj};
				else if (obj.form){
					$requestContentDiv = $(`#${obj.form}`);
					if ($requestContentDiv.length === 1){
						$button.on('click', function(){
							$requestContentDiv.find('input[type=submit]').first().trigger('click');
						});
						$requestContentDiv.prepend($.mk('input').attr('type','submit').hide());
					}
				}
				$button.val(name).on('keydown', function(e){
					if ([Key.Enter, Key.Space].includes(e.keyCode)){
						e.preventDefault();

						$button.trigger('click');
					}
					else if ([Key.Tab, Key.LeftArrow, Key.RightArrow].includes(e.keyCode)){
						e.preventDefault();

						let $dBc = classScope.$dialogButtons.children(),
							$focused = $dBc.filter(':focus'),
							$inputs = classScope.$dialogContent.find(':input');

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
				classScope.$dialogButtons.append($button);
			});
			this._setFocus();
			$w.trigger('dialog-opened');

			$.callCallback(callback, [$requestContentDiv]);
			if (append){
				let $lastdiv = this.$dialogContent.children(':not(#dialogButtons)').last();
				if (appendingToRequest)
					$lastdiv = $lastdiv.children('.notice').last();
				this.$dialogOverlay.stop().animate(
					{
						scrollTop: '+=' +
						($lastdiv.position().top + parseFloat($lastdiv.css('margin-top'), 10) + parseFloat($lastdiv.css('border-top-width'), 10))
					},
					'fast'
				);
			}

		}
		fail(title,content,force_new){
			this._display('fail',title,content,this._CloseButton,force_new === true);
		}
		success(title,content,closeBtn,callback){
			this._display('success',title,content, (closeBtn === true ? this._CloseButton : undefined), callback);
		}
		wait(title,additional_info,force_new){
			if (typeof additional_info === 'boolean' && typeof force_new === 'undefined'){
				force_new = additional_info;
				additional_info = undefined;
			}
			if (typeof additional_info !== 'string')
				additional_info = 'Sending request';
			this._display('wait',title,$.capitalize(additional_info)+'&hellip;',force_new === true);
		}
		request(title,content,confirmBtn,callback){
			if (typeof confirmBtn === 'function' && typeof callback === 'undefined'){
				callback = confirmBtn;
				confirmBtn = undefined;
			}
			let buttons = {},
				formid;
			if (content instanceof jQuery)
				formid = content.attr('id');
			else if (typeof content === 'string'){
				let match = content.match(/<form\sid=["']([^"']+)["']/);
				if (match)
					formid = match[1];
			}
			if (confirmBtn !== false){
				if (formid)
					buttons[confirmBtn||'Submit'] = {
						submit: true,
						form: formid,
					};
				buttons.Cancel = this._CloseButton.Close;
			}
			else buttons.Close = {
				action: this._CloseButton.Close,
				form: formid,
			};

			this._display('request',title,content,buttons,callback);
		}
		confirm(title,content,btnTextArray,handlerFunc){
			if (typeof btnTextArray === 'function' && typeof handlerFunc === 'undefined')
				handlerFunc = btnTextArray;

			if (typeof handlerFunc !== 'function')
				handlerFunc = this._CloseButton.Close;

			if (!$.isArray(btnTextArray)) btnTextArray = ['Eeyup','Nope'];
			let buttons = {}, classScope = this;
			buttons[btnTextArray[0]] = function(){ handlerFunc(true) };
			buttons[btnTextArray[1]] = function(){ handlerFunc(false); classScope._CloseButton.Close() };
			this._display('confirm',title,content,buttons);
		}
		info(title,content,callback){
			this._display('info',title,content,this._CloseButton,callback);
		}

		setFocusedElement($el){
			if ($el instanceof jQuery)
				this._$focusedElement = $el;
		}
		_storeFocus(){
			if (typeof this._$focusedElement !== 'undefined' && this._$focusedElement instanceof jQuery)
				return;
			let $focus = $(':focus');
			this._$focusedElement = $focus.length > 0 ? $focus.last() : undefined;
		}
		_restoreFocus(){
			if (typeof this._$focusedElement !== 'undefined' && this._$focusedElement instanceof jQuery){
				this._$focusedElement.focus();
				this._$focusedElement = undefined;
			}
		}
		_setFocus(){
			let $inputs = this.$dialogContent.find('input,select,textarea').filter(':visible'),
				$actions = this.$dialogButtons.children();
			if ($inputs.length > 0) $inputs.first().focus();
			else if ($actions.length > 0) $actions.first().focus();
		}
		_controlInputs(disable){
			let $inputs = this.$dialogContent
					.children(':not(#dialogButtons)')
					.last()
					.add(this.$dialogButtons)
					.find('input, button, select, textarea');

			if (disable)
				$inputs.filter(':not(:disabled)').addClass('temp-disable').disable();
			else $inputs.filter('.temp-disable').removeClass('temp-disable').enable();
		}

		close(callback){
			if (!this.isOpen())
				return $.callCallback(callback, false);

			this.$dialogOverlay.remove();
			this._open = undefined;
			this._restoreFocus();
			$.callCallback(callback);

			$body.removeClass('dialog-open');
		}
		clearNotice (regexp){
			let $notice = this.$dialogContent.children(':not(#dialogButtons)').children('.notice:last-child');
			if (!$notice.length)
				return false;

			if (typeof regexp === 'undefined' || regexp.test($notice.html())){
				$notice.hide();
				this._controlInputs(false);
				return true;
			}
			return false;
		}
	}

	$.Dialog = new Dialog();

	$body.on('keydown', function(e){
		if (!$.Dialog.isOpen() || e.keyCode !== Key.Tab)
			return true;

		let $inputs = $.Dialog.$dialogContent.find(':input'),
			$focused = $inputs.filter(e.target),
			idx = $inputs.index($focused);

		if ($focused.length === 0){
			e.preventDefault();
			$inputs.first().focus();
		}
		else if (e.shiftKey){
			if (idx === 0){
				e.preventDefault();
				$.Dialog.$dialogButtons.find(':last').focus();
			}
			else {
				let $parent = $focused.parent();
				if (!$parent.is($.Dialog.$dialogButtons))
					return true;
				if ($parent.children().first().is($focused)){
					e.preventDefault();
					$inputs.eq($inputs.index($focused)-1).focus();
				}
			}
		}
	});
})(jQuery);
