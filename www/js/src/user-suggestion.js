/* globals DocReady */
DocReady.push(function(){
	'use strict';

	let $pendingReservations = $('.pending-reservations');
	$pendingReservations.on('click','#suggestion',function(e){
		e.preventDefault();

		let pluginsLoaded = false;

		$.Dialog.info(
			'Request Roulette™',
			`<p>If you feel like making a vector but don’t have any screencap in mind, then you are in the right place.</p>
			<p>With this tool you can get a random request from the site instantly delivered straight to your screen. Club Members can choose to reserve the requests immediately, and everyone can ask for subsequent suggestions. You are not forced to commit to a suggestion, whether you take it or leave it is all up to you.</p>
			<div class="align-center"><button id="suggestion-press" class="btn large orange typcn typcn-lightbulb">Give me a suggestion</button></button>`,
			function(){
				let $btn = $('#dialogContent').find('#suggestion-press'),
					$output = $.mk('ul','suggestion-output').insertAfter($btn),
					$loadNotice = $.mk('div').addClass('notice fail').hide().text('The image failed to load - just click the button again to get a different suggestion.').insertAfter($output),
					$pluginNotice = $.mk('div').addClass('notice info').hide().html('Loading Fluidbox plugin&hellip;').insertAfter($output),
					suggest = function(){
						$.post('/user/suggestion',$.mkAjaxHandler(function(){
							if (!this.status){
								$btn.enable();
								return $.Dialog.fail(false, this.message);
							}

							let $result = $(this.suggestion),
								postID = $result.attr('id');
							$result.find('img').on('load',function(){
								let $this = $(this);
								$this.parents('.image').addClass('loaded');
								$this.parents('a').fluidboxThis();
							}).on('error',function(){
								$loadNotice.show();
								$(this).parents('.image').hide();
							});
							$result.find('.reserve-request').on('click',function(){
								let $this = $(this);
								$.post('/post/reserve/'+(postID.replace('-','/')),{SUGGESTED:true},$.mkAjaxHandler(function(){
									if (!this.status) return $.Dialog.fail(false, this.message);

									$this.replaceWith(this.button);
									$pendingReservations.html($(this.pendingReservations).children());
								}));
							});
							$output.html($result);
							$btn.enable();
						})).fail(function(){
							$btn.enable();
						});
					};
				$btn.on('click',function(e){
					e.preventDefault();

					$btn.disable();
					$loadNotice.hide();

					if (!pluginsLoaded){
						$pluginNotice.show();
						$output.find('.screencap > a').fluidboxThis(function(){
							pluginsLoaded = true;
							$pluginNotice.remove();
							suggest();
						});
					}
					else suggest();
				});
			}
		);
	});
});
