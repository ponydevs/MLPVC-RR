$(function(){
	var $eptable = $('#episodes'),
		$eptableBody = $eptable.children('tbody');
	Bind.call({init:true});

	var today = new Date(),
		saturday = new Date(today.getTime());
	saturday.setDate(saturday.getDate() + 6 - saturday.getDay());
	saturday.setUTCHours(15);
	saturday.setUTCMinutes(30);
	function pad(n){return n<10?'0'+n:n}
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
	Date.prototype.toAirDate = function(){ return pad(this.getFullYear())+'-'+pad(this.getMonth()+1)+'-'+pad(this.getDate()) };
	Date.prototype.toAirTime = function(){ return pad(this.getHours())+':'+pad(this.getMinutes()) };
	var date = saturday.toAirDate(), time = saturday.toAirTime();


	var EP_TITLE_REGEX = window.EP_TITLE_REGEX,
		EP_TITLE_HTML_REGEX = EP_TITLE_REGEX.toString().split('/')[1],
		$content = $('#content'),
		$pageTitle = $content.children('h1');

	function EpisodeForm(id){
		var $form = $(document.createElement('form')).attr('id', id).html(
			'<div class=input-group>'+
				'<input type="number" min=1 max=8 name=season placeholder="Season #" required>'+
				'<input type="number" min=1 max=26 name=episode placeholder="Episode #" required>'+
			'</div>\
			<label><input type="text" maxlength=255 name=title placeholder=Title pattern="'+EP_TITLE_HTML_REGEX+'" autocomplete=off required></label>\
			<div class="notice info align-center">\
				<p><strong>Title</strong> must be between 5 and 35 characters.<br>Letters, numbers, and these characters, are allowed:<br>-, apostrophe, !, &, comma.</p>\
			</div>\
			<div class=input-group>'+
				'<input type="date" name=airdate placeholder="YYYY-MM-DD" required>'+
				'<input type="time" name=airtime placeholder="HH:MM" required>'+
			'</div>\
			<div class="notice info align-center button-here">\
				<p>Specify when the episode will air,<br>in <strong>your computer\'s timezone</strong>.</p>\
			</div>\
			<label><input type="checkbox" name=twoparter> Has two parts</label>\
			<div class="notice info align-center">\
				<p>If this is checked, only specify<br>the episode number of the first part</p>\
			</div>');

			$(document.createElement('button')).text('Set time to '+time+' this Sunday').addClass('setsunday').on('click',function(e){
				e.preventDefault();
				$(this).parent().prev().children().first().val(date).next().val(time);
			}).appendTo($form.children('.button-here'));

		return $form;
	}
	var $addep = new EpisodeForm('addep'),
		$editep = new EpisodeForm('editep');

	$('#add-episode').on('click',function(e){
		e.preventDefault();
		var title = 'Add Episode';

		$.Dialog.request(title,$addep.clone(true, true),'addep','Add',function(){
			$('#addep').on('submit',function(e){
				e.preventDefault();
				var data = {};

				data.airs = mkDate($airdate.attr('disabled',true).val(), $airtime.attr('disabled',true).val()).toISOString();
				var tempdata = $(this).serializeArray();
				$.each(tempdata,function(i,el){
					data[el.name] = el.value;
				});

				$.Dialog.wait(title,'Adding episode to database');

				$.ajax({
					method: "POST",
					url: "/episode/add",
					data: data,
					success: function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							Bind(data.tbody);
							$.Dialog.close();
						}
						else $.Dialog.fail(title,data.message);
					}
				});
			})
		});
	});

	function Bind(tbody){
		if (typeof tbody === 'string'){
			$eptableBody.html(tbody);
			if ($eptableBody.children('.empty').length)
				$pageTitle.html($pageTitle.data('none')).next().show();
			else $pageTitle.html($pageTitle.data('list')).next().hide();
		}
		if (typeof tbody === 'string' || this.init === true){
			$eptableBody.children().children(':last-child').children('time.nodt').each(function(){
				this.innerHTML = new Date($(this).attr('datetime')).toLocaleString();
			});
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

			$.ajax({
				method: "POST",
				url: "/episode/"+epid,
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						var $editepWithData = $editep.clone(true, true);

						$editepWithData.find('input[name=twoparter]').prop('checked',!!data.ep.twoparter);
						delete data.ep.twoparter;

						var d = mkDate(data.ep.airdate, data.ep.airtime, true);
						data.ep.airdate = d.toAirDate();
						data.ep.airtime = d.toAirTime();

						var epid = data.epid;
						delete data.epid;

						$.each(data.ep,function(k,v){
							$editepWithData.find('input[name='+k+']').val(v);
						});

						$.Dialog.request('Editing',$editepWithData,'editep','Save',function(){
							$('#editep').on('submit',function(e){
								e.preventDefault();

								var tempdata = $(this).serializeArray(), data = {};
								$.each(tempdata,function(i,el){
									data[el.name] = el.value;
								});

								var d = mkDate(data.airdate, data.airtime);
								delete data.airdate;
								delete data.airtime;
								data.airs = d.toISOString();

								$.Dialog.wait(title,'Saving edits');

								$.ajax({
									method: "POST",
									url: '/episode/edit/'+epid,
									data: data,
									success: function(data){
										if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

										if (data.status){
											Bind(data.tbody);
											$.Dialog.close();
										}
										else $.Dialog.fail(title,data.message);
									}
								})
							})
						});
					}
					else $.Dialog.fail(title,data.message);
				}
			});
		});

		$eptable.find('.delete-episode').off('click').on('click',function(e){
			e.preventDefault();

			var $this = $(this),
				epid = $this.closest('tr').data('epid'),
				title = 'Deleting '+epid;

			$.Dialog.confirm(title,'<p>This will remove <strong>ALL</strong><ul><li>requests</li><li>reservations</li><li>and votes</li></ul>associated with the episode, too.</p><p>Are you sure you want to delete it?</p>',function(sure){
				if (!sure) return;

				$.Dialog.wait(title);

				$.ajax({
					method: "POST",
					url: '/episode/delete/'+epid,
					success: function(data){
						if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

						if (data.status){
							Bind(data.tbody);
							$.Dialog.close();
						}
						else $.Dialog.fail(title,data.message);
					}
				});
			});
		});
	}
});