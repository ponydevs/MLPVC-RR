DocReady.push(function EpisodesManage(){
	var $eptable = $('#episodes'),
		$eptableBody = $eptable.children('tbody');
	Bind.call({init:true});

	var saturday = moment().day("Saturday").toDate();
	saturday.setUTCHours(15);
	saturday.setUTCMinutes(30);
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

	function UpcomingUpdate(ulContent){
		var $uc = $('#upcoming');
		if ($uc.length === 0 && !!ulContent){
			$uc = $.mk('section').attr('id', 'upcoming').insertBefore($('#sidebar').children('.welcome, .login'));
			$uc.append($.mk('h2').text('Upcoming episodes'),$.mk('ul'));
		}

		if (ulContent)
			$uc.children('ul').html(ulContent);
		else $uc.remove();

		window.setCD();
		window.updateTimes();
	}

	var EP_TITLE_REGEX = window.EP_TITLE_REGEX,
		EP_TITLE_HTML_REGEX = EP_TITLE_REGEX.toString().split('/')[1],
		$content = $('#content'),
		$pageTitle = $content.children('h1').first();

	function EpisodeForm(id){
		var $form = $.mk('form').attr('id', id).html(
			'<div class=input-group>'+
				'<input type="number" min="1" max="8" name="season" placeholder="Season #" required>'+
				'<input type="number" min="1" max="26" name="episode" placeholder="Episode #" required>'+
			'</div>\
			<label><input type="text" maxlength="255" name="title" placeholder="Title" pattern="'+EP_TITLE_HTML_REGEX+'" autocomplete="off" required></label>\
			<div class="notice info align-center">\
				<p><strong>Title</strong> must be between 5 and 35 characters.<br>Letters, numbers, and these characters, are allowed:<br>-, apostrophe, !, &, comma.</p>\
			</div>\
			<div class="input-group">'+
				'<input type="date" name="airdate" placeholder="YYYY-MM-DD" required>'+
				'<input type="time" name="airtime" placeholder="HH:MM" required>'+
			'</div>\
			<div class="notice info align-center button-here">\
				<p>Specify when the episode will air, in <strong>your computer\'s timezone</strong>.</p>\
			</div>\
			<label><input type="checkbox" name="twoparter"> Has two parts</label>\
			<div class="notice info align-center">\
				<p>If this is checked, only specify the episode number of the first part</p>\
			</div>');

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

		$.Dialog.request('Add Episode',$addep.clone(true, true),'addep','Add',function(){
			var $form = $('#addep');
			$form.on('submit',function(e){
				e.preventDefault();
				var airdate = $form.find('input[name=airdate]').attr('disabled',true).val(),
					airtime = $form.find('input[name=airtime]').attr('disabled',true).val(),
					airs = mkDate(airdate, airtime).toISOString(),
					data = $(this).mkData({airs:airs});

				$.Dialog.wait(title,'Adding episode to database');

				$.post('/episode/add', data, $.mkAjaxHandler(function(){
					if (this.status){
						Bind(this.tbody);
						UpcomingUpdate(this.upcoming);
						$.Dialog.close();
					}
					else $.Dialog.fail(title,this.message);
				}));
			})
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
		$eptable.find('tr[data-epid]').each(function(){
			var $this = $(this),
				epid = $this.attr('data-epid');

			$this.removeAttr('data-epid').data('epid', epid);
		});
		$eptable.find('.edit-episode').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				epid = $this.closest('tr').data('epid'),
				title = 'Editing '+epid;

			$.Dialog.wait(title, 'Getting episode details from server');

			$.post("/episode/"+epid, $.mkAjaxHandler(function(){
				if (this.status){
					var $editepWithData = $editep.clone(true, true);

					$editepWithData.find('input[name=twoparter]').prop('checked',!!this.ep.twoparter);
					delete this.ep.twoparter;

					var d = mkDate(this.ep.airdate, this.ep.airtime, true);
					this.ep.airdate = d.toAirDate();
					this.ep.airtime = d.toAirTime();

					var epid = this.epid;
					delete this.epid;

					$.each(this.ep,function(k,v){
						$editepWithData.find('input[name='+k+']').val(v);
					});

					$.Dialog.request('Editing '+epid,$editepWithData,'editep','Save',function(){
						$('#editep').on('submit',function(e){
							e.preventDefault();

							var data = $(this).mkData(),
								d = mkDate(data.airdate, data.airtime);
							delete data.airdate;
							delete data.airtime;
							data.airs = d.toISOString();

							$.Dialog.wait(title,'Saving edits');

							$.post('/episode/edit/'+epid, data, $.mkAjaxHandler(function(){
								if (this.status){
									Bind(this.tbody);
									UpcomingUpdate(this.upcoming);
									$.Dialog.close();
								}
								else $.Dialog.fail(title,this.message);
							}));
						})
					});
				}
				else $.Dialog.fail(title,this.message);
			}));
		});

		$eptable.find('.delete-episode').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				epid = $this.closest('tr').data('epid'),
				title = 'Deleting '+epid;

			$.Dialog.confirm(title,'<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>video links</li><li>and votes</li></ul>associated with the episode, too.</p><p>Are you sure you want to delete it?</p>',function(sure){
				if (!sure) return;

				$.Dialog.wait(title);

				$.post('/episode/delete/'+epid, $.mkAjaxHandler(function(){
						if (this.status){
							Bind(this.tbody);
							UpcomingUpdate(this.upcoming);
							$.Dialog.close();
						}
						else $.Dialog.fail(title,this.message);
				}));
			});
		});
	}
});
