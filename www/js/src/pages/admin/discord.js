/* global DocReady */
$(function(){
	'use strict';

	const USERNAME_REGEX = window.USERNAME_REGEX;

	let $searchBar = $('#member-search').children('input'),
		$list = $('.discord-members'),
		$manager = $('#manage-area'),
		$linkOf = $('#linkof-member'),
		$managerTemplate = $.mk('div').append(
			$.mk('div').attr('class','bind-status').html(`<h3>This member is bound to:</h3><div class="boundto"></div>`),
			$.mk('div').attr('class','do-bind').append(
				`<h3>Change binding:</h3>`,
				$.mk('form').append(
					$.mk('input').attr({
						type: 'text',
						placeholder: 'Username',
						required: true,
					}).patternAttr(USERNAME_REGEX),
					`<br>`,
					$.mk('button').attr('class', 'green typcn typcn-refresh').text('Change'),
					$.mk('button').attr('class', 'red typcn typcn-user-delete').text('Remove').on('click',function(e){
						e.preventDefault();

						let title = 'Remove binding',
							discid = $list.find('.selected').attr('id').split('-')[1];
						if (isNaN(discid))
							return $.Dialog.fail(false, 'Cannot find Discord user ID');

						$.Dialog.confirm(title, 'Are you sure you want to remove this binding?', function(sure){
							if (!sure) return;

							$.Dialog.wait(false);

							$.post(`/admin/discord/member-link/del/${discid}`,$.mkAjaxHandler(function(){
								if (!this.status) return $.Dialog.fail(false, this.message);

								$(`#member-${discid}`).removeClass('bound');

								updateManager(discid);
								$.Dialog.close();
							}));
						});
					})
				).on('submit',function(e){
					e.preventDefault();

					let $form = $(this),
						to = $form.find('input').val();

					$.Dialog.confirm('Change binding', `Are you sure you want to bind this member to <strong>${to}</strong>?`, function(sure){
						if (!sure) return;

						let discid = $list.find('.selected').attr('id').split('-')[1];

						$.Dialog.wait(false);

						$.post(`/admin/discord/member-link/set/${discid}`,{ to },$.mkAjaxHandler(function(){
							if (!this.status) return $.Dialog.fail(false, this.message);

							$(`#member-${discid}`).addClass('bound');

							updateManager(discid);
							$.Dialog.close();
						}));

					});
				})
			)
		).children();

	$searchBar.on('keyup change',function(){
		let input = $(this).val().trim().toLowerCase();

		if (input.length === 0)
			$list.children().removeClass('hidden').each(function(){
				$(this).find('.user-data').children().each(function(){
					const $this = $(this);
					$this.html($this.text());
				});
			});
		else {
			$list.children().addClass('hidden').filter(function(){
				let $this = $(this),
					$userData = $this.find('.user-data').children(),
					parts = input.split('#'),
					cond = $this.text().toLowerCase().replace(/\s+#/g,'#').indexOf(input) !== -1;

				let $1stChild = $userData.eq(0),
					$2ndChild = $userData.eq(1);
				$1stChild.html($1stChild.text().replace(new RegExp(`(${$.escapeRegex(parts[0])})`,'i'), '<mark>$1</mark>'));
				$2ndChild.html($2ndChild.text().replace(new RegExp(`(${$.escapeRegex(parts[0])}${typeof parts[1] === 'string'?$.escapeRegex('#'+parts[1]):''})`,'i'), '<mark>$1</mark>'));

				return cond;
			}).removeClass('hidden');
		}
		if ($list.children(':not(.hidden)').length === 0)
			$list.addClass('empty');
		else $list.removeClass('empty');
	});

	function updateManager(discid){
		$manager.addClass('loading');
		$.post(`/admin/discord/member-link/get/${discid}`,$.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail('Loading binding data', this.message);

			let $templ = $managerTemplate.clone(true,true);
			if (typeof this.boundto !== 'undefined')
				$templ.filter('.bind-status').children('.boundto').append(this.boundto);
			$manager.empty().append($templ).removeClass('loading');
		}));
	}
	$list.on('click','li',function(e){
		e.preventDefault();

		let $li = $(this),
			discid = $li.attr('id').split('-')[1],
			displayname = $li.find('.user-data').children().first().text();
		$li.addClass('selected').siblings().removeClass('selected');

		$linkOf.empty().append(' of ',$.mk('span').attr('class','color-blue').text(displayname));
		updateManager(discid);
	});
	$('#rerequest-members').on('click',function(e){
		e.preventDefault();

		$.Dialog.confirm('Re-request member list', 'You are about to update the member list. This will update the all locally stored data about the members except for the bindings. Continue?',function(sure){
			if (!sure) return;

			$.Dialog.wait(false);

			$.post('/admin/discord/member-list',{update:true},$.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false, this.message);

				$list.html(this.list);
				$.Dialog.close();
			}));
		});
	});
	$.post('/admin/discord/member-list',$.mkAjaxHandler(function(){
		if (!this.status) return $.Dialog.fail('Loading member list', this.message);

		$list.html(this.list).removeClass('loading');
	}));
});
