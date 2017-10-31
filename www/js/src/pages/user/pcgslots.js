/* global DocReady,$w,IntersectionObserver */
$(function(){
	'use strict';

	const username = window.username;

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
				$.post(`/@${username}/cg/slot-history/recalc`, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Navigation.reload(true);
				}));
			}
		);
	});
});
