(function(){
	'use strict';

	const { username, userId } = window;

	const $recalcBtn = $('#recalc-button');
	$recalcBtn.on('click',function(e){
		e.preventDefault();

		$.Dialog.confirm(
			'Recalculate PCG slot history',
			`<p>This will wipe all PCG slot history related to ${username} and recalculate the slots based on the currently available data.</p>
			<p>If they had slot gains disabled in the past this will not take that into account.</p>
			<p>Are you sure you want to proceed with the recalculation?</p>`,
			sure => {
				if (!sure) return;

				$.Dialog.wait(false, 'Recalculating');
				$.API.post(`/user/${userId}/pcg/point-history/recalc`, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Navigation.reload(true);
				}));
			}
		);
	});

	const $pendingGiftsBtn = $('#pending-gifts-button');
	$pendingGiftsBtn.on('click', function(e){
		e.preventDefault();

		$.Dialog.wait(`Pending gifts for ${username}`, 'Checking for pending gifts');

		$.API.get(`/user/${userId}/pcg/pending-gifts`, $.mkAjaxHandler(function(){
			if (!this.status) return $.Dialog.fail(false, this.message);

			if (!this.pendingGifts)
				return $.Dialog.info(false, this.message);

			const
				$tbody = $.mk('tbody'),
				$table = $.mk('table').append(
					`<thead>
						<tr>
							<th>From</th>
							<th>Amount</th>
							<th>Sent</th>
							<th class="refund">Refund?</th>
						</tr>
					</thead>`,
					$tbody
				),
				$form = $.mk('form', 'refund-gifts-form').append(
					`<p>Here you can select specific gifts that ${username} hasn't claimed yet and are available to be refunded.</p>`,
					`<p>You should generally avoid refunding anything that's less than 2 weeks old unless you're absolute certain that ${username} won't be able to accept or reject the gift themselves within that timeframe.</p>`,
					$.mk('div').append(
						$table
					)
				),
				mkRefundTd = (gift) =>
					$.mk('td').attr('class','refund unselected').append(
						$.mk('input').attr({
							type: 'checkbox',
							value: gift.id,
						}).on('change click', function(e){
							const $td = $(e.target).closest('td');
							$td.removeClass(e.target.checked ? 'unselected' : 'selected');
							$td.addClass(e.target.checked ? 'selected' : 'unselected');
						})
					).on('click',function(e){
						if (e.target.nodeName.toLowerCase() === 'td')
							e.target.childNodes[0].click();
					});

			$.each(this.pendingGifts, (_, gift) => {
				$tbody.append(
					$.mk('tr').append(
						`<td>${gift.from}</td>
						<td>${gift.amount}</td>
						<td>${gift.sent}</td>`,
						mkRefundTd(gift)
					)
				);
			});
			$.Dialog.request(false, $form, 'Refund selected', $form => {
				$form.on('submit',function(e){
					e.preventDefault();

					const ids = [];
					$form.find('input:checked').each((_, el) => {
						ids.push(el.value);
					});

					if (ids.length === 0)
						return $.Dialog.fail(false, 'You have to select at least one gift');

					$.Dialog.wait(false, 'Refunding selected gifts');

					$.API.post('/user/pcg/refund-gifts', { giftids: ids.join(',') }, $.mkAjaxHandler(function(){
						if (!this.status) return $.Dialog.fail(false, this.message);

						$.Dialog.success(false, this.message, true);
					}));
				});
			});
		}));
	});
})();
