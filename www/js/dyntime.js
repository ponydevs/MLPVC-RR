(function($){
	var startval = window.DT_START || 'just now';
	var timePad = function(i){if(!isNaN(i)){i=parseInt(i);if (i<10&&i>=0)i='0'+i;else if(i<0)i='-0'+Math.abs(i);else i=i.toString()}return i};
	var months = [ undefined,
		"Januaray",
		"February",
		"March",
		"April",
		"May",
		"June",
		"July",
		"August",
		"September",
		"October",
		"November",
		"December"
	];
	var weekdays = [
		"Sunday",
		"Monday",
		"Tuesday",
		"Wednesday",
		"Thursday",
		"Friday",
		"Saturday"
	];
	var dateformat = {
		orderwd: '{{wd}}, {{d}} {{mo}}, {{y}} {{h}}:{{mi}}:{{s}}',
		order: '{{d}} {{mo}} {{y}}, {{h}}:{{mi}}:{{s}}',
		day: function(date){
			var lastDigit = date % 10,
				postfix;
			if (lastDigit == 0 || lastDigit > 3 || date == 11 || date == 12) postfix = 'th';
			else if (lastDigit == 1) postfix = 'st';
			else if (lastDigit == 2) postfix = 'nd';
			else if (lastDigit == 3) postfix = 'rd';
			return date.toString() + postfix;
		},
		weekday: function(wd){ return weekdays[parseInt(wd)] },
		month: function(m){ return months[parseInt(m)] },
		year: function(y){ return y },
	};
	var timeparts = function(unit, num){
		return num+' '+unit+(num>1?'s':'');
	};
	var update = function(){
		$('time').filter(':not(.nodt)').each(function(){
			var $this = $(this),
				date = $this.attr('datetime'),
				timestamp = new Date(date);
			if (typeof date !== 'string') throw new TypeError('Invalid date data type: "'+(typeof date)+'"');
			if (isNaN(timestamp.getTime())){
				console.log(this);
				throw new Error('Invalid date format: "'+date+'"');
			}
				
			if (!$this.hasClass('dynt')) $this.addClass('dynt');
			
			date = {
				d: dateformat.day(timestamp.getDate()),
				y: dateformat.year(timestamp.getFullYear()),
				mo: dateformat.month(timestamp.getMonth()+1),
				wd: dateformat.weekday(timestamp.getDay()),
				h: timePad(timestamp.getHours()),
				mi: timePad(timestamp.getMinutes()),
				s: timePad(timestamp.getSeconds()),
				order: dateformat.order,
				orderwd: dateformat.orderwd,
			};

			function getFullDate(fulldate){
				return fulldate.replace(/\{\{([a-z]{1,2})}}/g,function(_,k){
					return date[k];
				});
			}
			
			var Now = new Date(),
				timestr = createTimeStr(Now,timestamp),
				$elapsedHolder = $this.parent().children('.dynt-el'),
				updateHandler = $this.data('dyntime-beforeupdate');

			if (typeof updateHandler === 'function'){
				var result = updateHandler(timeDifference(Now,timestamp));
				if (result === false) return;
			}

			if ($elapsedHolder.length > 0){
				$this.html(getFullDate(date['order'+(
					typeof $this.data('noweekday') !== 'undefined'
					?''
					:'wd'
				)]));
				$elapsedHolder.html(timestr);
			}
			else {
				$this.attr('title', getFullDate(date.order));
				$this.html(timestr);
			}
		});
	};
	window.getTimeDiff = function(){ return timeDifference.apply(this, arguments) };
	function timeDifference(now, timestamp) {
		var substract = (now.getTime() - timestamp.getTime())/1000,
			d = {
				past: substract > 0,
				time: Math.abs(substract),
				target: timestamp
			},
			time = d.time;

		d.day = Math.floor(time/one.day);
		time -= d.day * one.day;
		
		d.hour = Math.floor(time/one.hour);
		time -= d.hour * one.hour;
		
		d.minute = Math.floor(time/one.minute);
		time -= d.minute * one.minute;
		
		d.second = Math.floor(time);
		
		if (d.day >= 7){
			d.week = Math.floor(d.day/7);
			d.day -= d.week*7;
		}
		if (d.week >= 4){
			d.month = Math.floor(d.week/4);
			d.week -= d.month*4;
		}
		if (d.month >= 12){
			d.year = Math.floor(d.month/12);
			d.month -= d.year*12;
		}
		
		return d;
	}
	var one = {
		'year':   31557600,
		'month':  2592000,
		'week':   604800,
		'day':    86400,
		'hour':   3600,
		'minute': 60,
		'second': 1,
	}, order = ['year', 'month', 'week', 'day', 'hour', 'minute', 'second'], i = 0;
	window.one = (function(one){return one})(one);

	window.createTimeStr = function(){ return createTimeStr.apply(this, arguments) };
	function createTimeStr(now, timestamp){
		var delta = (now.getTime() - timestamp.getTime())/1000,
			past = delta > 0,
			str = false;
		if (!past) delta *= -1;

		$.each(order, function(_, unit){
			var value = one[unit];
			if (delta >= value){
				var left = Math.floor(delta / value);
				delta -= (left * value);
				if (!past && unit === 'minute')
					left++;
				str = left!=1?left+' '+unit+'s':(unit=='hour'?'an':'a')+' '+unit;
				return false;
			}
		});

		if (str === false) return startval;

		if (str === '1 day') return past ? 'yesterday' : 'tomorrow';
		else return past ? str+' ago' : 'in '+str;
	}
	update();
	window.updateTimesF = function(){
		update.apply(update,arguments);
	};
	if (window.noAutoUpdateTimes !== true) window.updateTimes = window.setInterval(update,10000);
})(jQuery);
