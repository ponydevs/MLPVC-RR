/* global DocReady,moment,HandleNav,$content */
DocReady.push(function EpisodesManage(){
	'use strict';
	var $eptableBody = $('#episodes').children('tbody'),
		SEASON = window.SEASON,
		EPISODE = window.EPISODE;
	Bind.call({init:true});

	/*!
	 * Timezone data string taken from:
	 * http://momentjs.com/downloads/moment-timezone-with-data.js
	 * version 0.4.1 by Tim Wood, licensed MIT
	 */
	moment.tz.add("America/Los_Angeles|PST PDT PWT PPT|80 70 70 70|010102301010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010|-261q0 1nX0 11B0 1nX0 SgN0 8x10 iy0 5Wp0 1Vb0 3dB0 WL0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1o10 11z0 1qN0 WL0 1qN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1cN0 1cL0 1cN0 1cL0 s10 1Vz0 LB0 1BX0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 1cN0 1fz0 1a10 1fz0 1cN0 1cL0 1cN0 1cL0 1cN0 1cL0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 14p0 1lb0 14p0 1lb0 14p0 1nX0 11B0 1nX0 11B0 1nX0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Rd0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0 Op0 1zb0");

	var saturday = moment.tz(new Date(), "America/Los_Angeles").set({
		day: 'Saturday',
		h: 8, m: 30, s: 0,
	}).toDate();
	function parseIntArray(arr){
		$.each(arr,function(i,el){
			arr[i] = parseInt(el, 10);
		});
		return arr;
	}
	function mkDateArray(datestr){
		var s = parseIntArray(datestr.split('-'));
		s[1]--;
		return s;
	}
	function mkTimeArray(timestr){ return parseIntArray(timestr.split(':')) }
	function mkDate(datestr, timestr, utc){
		var dateArr = mkDateArray(datestr),
			timeArr = mkTimeArray(timestr),
			d = new Date(dateArr[0], dateArr[1], dateArr[2], 10);
		d['set'+(utc?'UTC':'')+'Hours'](timeArr[0]);
		d['set'+(utc?'UTC':'')+'Minutes'](timeArr[1]);
		return d;
	}
	Date.prototype.toAirDate = function(){ return this.getFullYear()+'-'+$.pad(this.getMonth()+1)+'-'+$.pad(this.getDate()) };
	Date.prototype.toAirTime = function(){ return $.pad(this.getHours())+':'+$.pad(this.getMinutes()) };
	var date = saturday.toAirDate(), time = saturday.toAirTime();

	var EP_TITLE_REGEX = window.EP_TITLE_REGEX,
		$pageTitle = $content.children('h1').first();

	function EpisodeForm(id){
		var $form = $.mk('form').attr('id', id).append(
			'<div class="label">'+
				'<span>Season &amp; Episode</span>'+
				'<div class=input-group-2>'+
					'<input type="number" min="1" max="8" name="season" placeholder="Season #" required>'+
					'<input type="number" min="1" max="26" name="episode" placeholder="Episode #" required>'+
				'</div>'+
			'</label>',
			$.mk('label').append(
				'<span>Title (5-35 chars.)</span>',
				$.mk('input').attr({
					type: 'text',
					maxlength: 35,
					name: 'title',
					placeholder: 'Title',
					autocomplete: 'off',
					required: true,
				}).patternAttr(EP_TITLE_REGEX)
			),
			'<div class="label">' +
				'<span>Air Date</span>'+
				'<div class="input-group-2">'+
					'<input type="date" name="airdate" placeholder="YYYY-MM-DD" required>'+
					'<input type="time" name="airtime" placeholder="HH:MM" required>'+
				'</div>'+
			'</div>'+
			'<div class="notice info align-center button-here">'+
				'<p>Specify when the episode will air, in <strong>your computer\'s timezone</strong>.</p>'+
			'</div>'+
			'<label><input type="checkbox" name="twoparter"> Has two parts</label>'+
			'<div class="notice info align-center">'+
				'<p>If this is checked, only specify the episode number of the first part</p>'+
			'</div>'
		);

		$.mk('button').text('Set time to '+time+' this Saturday').on('click',function(e){
			e.preventDefault();
			$(this).parent().prev().children().first().val(date).next().val(time);
		}).appendTo($form.children('.button-here'));

		return $form;
	}
	var $addep = new EpisodeForm('addep'),
		$editep = new EpisodeForm('editep');

	$('#add-episode').on('click',function(e){
		e.preventDefault();

		$.Dialog.request('Add Episode',$addep.clone(true, true),'addep','Add',function($form){
			$form.on('submit',function(e){
				e.preventDefault();
				var airdate = $form.find('input[name=airdate]').attr('disabled',true).val(),
					airtime = $form.find('input[name=airtime]').attr('disabled',true).val(),
					airs = mkDate(airdate, airtime).toISOString(),
					data = $(this).mkData({airs:airs});

				$.Dialog.wait(false, 'Adding episode to database');

				$.post('/episode/add', data, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Opening episode page', true);

					$.Navigation.visit('/episode/'+this.epid,function(){
						$.Dialog.close();
					});
				}));
			});
		});
	});

	function Bind(tbody){
		if (typeof tbody === 'string'){
			$eptableBody.html(tbody);
			if ($eptableBody.children('.empty').length)
				$pageTitle.html($pageTitle.data('none')).next().show();
			else $pageTitle.html($pageTitle.data('list')).next().hide();
			$eptableBody.trigger('updatetimes');
		}
		$eptableBody.find('tr[data-epid]').each(function(){
			var $this = $(this),
				epid = $this.attr('data-epid');

			$this.removeAttr('data-epid').data('epid', epid);
		});
		$eptableBody.find('.edit-episode').add('#edit-ep').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				EpisodePage = $this.attr('id') === 'edit-ep',
				epid = EpisodePage ? 'S'+SEASON+'E'+EPISODE : $this.closest('tr').data('epid');

			$.Dialog.wait('Editing '+epid, 'Getting episode details from server');

			$.post("/episode/"+epid, $.mkAjaxHandler(function(){
				if (!this.status) return $.Dialog.fail(false,this.message);

				var $editepWithData = $editep.clone(true, true);

				$editepWithData.find('input[name=twoparter]').prop('checked',!!this.ep.twoparter);
				delete this.ep.twoparter;

				if (!this.caneditid || (EpisodePage && $('#reservations, #requests').find('li').length))
					$editepWithData.find('input').filter('[name="season"],[name="episode"]').disable();

				var d = mkDate(this.ep.airdate, this.ep.airtime, true);
				this.ep.airdate = d.toAirDate();
				this.ep.airtime = d.toAirTime();

				var epid = this.epid;
				delete this.epid;

				$.each(this.ep,function(k,v){
					$editepWithData.find('input[name='+k+']').val(v);
				});

				$.Dialog.request(false, $editepWithData,'editep','Save',function($form){
					$form.on('submit',function(e){
						e.preventDefault();

						var data = $(this).mkData(),
							d = mkDate(data.airdate, data.airtime);
						delete data.airdate;
						delete data.airtime;
						data.airs = d.toISOString();

						$.Dialog.wait(false, 'Saving changes');

						$.post('/episode/edit/'+epid, data, $.mkAjaxHandler(function(){
							if (!this.status) return $.Dialog.fail(false, this.message);

							$.Dialog.wait(false, 'Updating page', true);
							$.Navigation.reload(function(){
								$.Dialog.close();
							});
						}));
					});
				});
			}));
		});

		$eptableBody.find('.delete-episode').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				epid = $this.closest('tr').data('epid');

			$.Dialog.confirm('Deleting '+epid,'<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>video links</li><li>and votes</li></ul>associated with the episode, too.</p><p>Are you sure you want to delete it?</p>',function(sure){
				if (!sure) return;

				$.Dialog.wait(false, 'Removing episode');

				$.post('/episode/delete/'+epid, $.mkAjaxHandler(function(){
					if (!this.status) return $.Dialog.fail(false, this.message);

					$.Dialog.wait(false, 'Reloading page', true);
					$.Navigation.reload(function(){
						$.Dialog.close();
					});
				}));
			});
		});
	}

	$eptableBody.on('page-switch',function(){
		Bind();
	});
});
