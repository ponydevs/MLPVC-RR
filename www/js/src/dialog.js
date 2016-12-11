/* globals $body,Key,$w,Time */
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
			wait: 'Sending request',
			request: 'The request did not require any additional info.',
			confirm: 'Are you sure?',
			info: 'No message provided.',
		},
		closeAction = () => { $.Dialog.close() };

	class DialogButton {
		constructor(label, options){
			this.label = label;
			$.each(options, (k,v)=>this[k]=v);
		}

		setLabel(newlabel){
			this.label = newlabel;
			return this;
		}

		setFormId(formid){
			this.formid = formid;
			return this;
		}
	}

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
			this._CloseButton = new DialogButton('Close', { action: closeAction });
			this._$focusedElement = undefined;
		}

		isOpen(){ return typeof this._open === 'object' }

		_display(options){
			if (typeof options.type !== 'string' || typeof colors[options.type] === 'undefined')
				throw new TypeError('Invalid dialog type: '+typeof options.type);

			if (!options.content)
				options.content = defaultContent[options.type];
			let params = $.extend({
				content: defaultContent[options.type],
			},options);
			params.color =  colors[options.type];

			let append = Boolean(this._open),
				$contentAdd = $.mk('div').append(params.content),
				appendingToRequest = append && this._open.type === 'request' && ['fail','wait'].includes(params.type) && !params.forceNew,
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
				this.$dialogHeader = $.mk('div','dialogHeader');
				if (typeof params.title === 'string')
					this.$dialogHeader.text(params.title);
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

			if (!appendingToRequest && params.buttons) $.each(params.buttons, (_, obj) => {
				let $button = $.mk('input').attr({
						'type': 'button',
						'class': params.color+'-bg'
					});
				if (obj.form){
					$requestContentDiv = $(`#${obj.form}`);
					if ($requestContentDiv.length === 1){
						$button.on('click', function(){
							$requestContentDiv.find('input[type=submit]').first().trigger('click');
						});
						$requestContentDiv.prepend($.mk('input').attr('type','submit').hide());
					}
				}
				$button.val(obj.label).on('keydown', (e) => {
					if ([Key.Enter, Key.Space].includes(e.keyCode)){
						e.preventDefault();

						$button.trigger('click');
					}
					else if ([Key.Tab, Key.LeftArrow, Key.RightArrow].includes(e.keyCode)){
						e.preventDefault();

						let $dBc = this.$dialogButtons.children(),
							$focused = $dBc.filter(':focus'),
							$inputs = this.$dialogContent.find(':input');

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
				this.$dialogButtons.append($button);
			});
			this._setFocus();
			$w.trigger('dialog-opened');
			Time.Update();

			$.callCallback(params.callback, [$requestContentDiv]);
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
		/**
		 * Display a dialog asking for user input
		 *
		 * @param {string}        title
		 * @param {string|jQuery} content
		 * @param {bool}          forceNew
		 */
		fail(title = defaultTitles.fail, content = defaultContent.fail, forceNew = false){
			this._display({
				type: 'fail',
				title,
				content,
				buttons: [this._CloseButton],
				forceNew
			});
		}
		/**
		 * Display a dialog asking for user input
		 *
		 * @param {string}        title
		 * @param {string|jQuery} content
		 * @param {bool}          closeBtn
		 * @param {function}      callback
		 */
		success(title = defaultTitles.success, content = defaultContent.success, closeBtn = false, callback = undefined){
			this._display({
				type: 'success',
				title,
				content,
				buttons: (closeBtn ? [this._CloseButton] : undefined),
				callback,
			});
		}
		/**
		 * Display a dialog informing the user of an action in progress
		 *
		 * @param {string}        title
		 * @param {string|jQuery} content
		 * @param {bool}          forceNew
		 */
		wait(title = defaultTitles.wait, content = defaultContent.wait, forceNew = false){
			this._display({
				type: 'wait',
				title,
				content: $.capitalize(content)+'&hellip;',
				forceNew,
			});
		}
		/**
		 * Display a dialog asking for user input
		 *
		 * @param {string}          title
		 * @param {string|jQuery}   content
		 * @param {string|function} confirmBtn
		 * @param {function}        callback
		 */
		request(title = defaultTitles.request, content = defaultContent.request, confirmBtn = 'Submit', callback = undefined){
			if (typeof confirmBtn === 'function' && typeof callback === 'undefined'){
				callback = confirmBtn;
				confirmBtn = undefined;
			}
			let buttons = [],
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
					buttons.push(new DialogButton(confirmBtn, {
						submit: true,
						form: formid,
					}));
				buttons.push(new DialogButton('Cancel', { action: closeAction }));
			}
			else buttons.push(new DialogButton('Close', { formid }));

			this._display({
				type: 'request',
				title,
				content,
				buttons,
				callback
			});
		}
		/**
		 * Display a dialog asking for confirmation regarding an action
		 *
		 * @param {string}            title
		 * @param {string|jQuery}     content
		 * @param {string[]|function} btnTextArray
		 * @param {function}          handlerFunc
		 */
		confirm(title = defaultTitles.confirm, content = defaultContent.confirm, btnTextArray = ['Eeyup','Nope'], handlerFunc = undefined){
			if (typeof handlerFunc === 'undefined')
				handlerFunc = typeof btnTextArray === 'function' ? btnTextArray : closeAction;

			if (!$.isArray(btnTextArray))
				btnTextArray = ['Eeyup','Nope'];

			let buttons = [
				new DialogButton(btnTextArray[0], {
					action: () => { handlerFunc(true) }
				}),
				new DialogButton(btnTextArray[1], {
					action: () => { handlerFunc(false); this._CloseButton.action() }
				})
			];
			this._display({
				type: 'confirm',
				title,
				content,
				buttons
			});
		}
		info(title = defaultTitles.info, content = defaultContent.info, callback = undefined){
			this._display({
				type: 'info',
				title,
				content,
				buttons: [this._CloseButton],
				callback,
			});
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
				if ($notice.hasClass('info'))
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
