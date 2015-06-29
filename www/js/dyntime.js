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
			
			var diff = timeDifference(new Date,timestamp),
				timestr = createTimeStr(diff),
				$elapsedHolder = $this.parent().children('.dynt-el'),
				updateHandler = $this.data('dyntime-beforeupdate');

			if (typeof updateHandler === 'function'){
				var result = updateHandler(diff);
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
	function timeDifference(n,e) {
		var substract = n.getTime() - e.getTime(),
			d = { past: substract > 0, time: Math.abs(substract), target: e }
			time = d.time;

		d.day = Math.floor(time/1000/60/60/24);
		time -= d.day*1000*60*60*24;
		
		d.hour = Math.floor(time/1000/60/60);
		time -= d.hour*1000*60*60;
		
		d.minute = Math.floor(time/1000/60);
		time -= d.minute*1000*60;
		
		d.second = Math.floor(time/1000);
		
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
	window.getTimeDiff = function(){
		return timeDifference.apply(this, arguments);
	};
	function createTimeStr(obj){
		if (typeof obj !== 'object' || $.isArray(obj)) return false;
		if (obj.time > 0) delete obj.time;
		
		var keys = Object.keys(obj), returnStr = '';
		for (var i=0,l=keys.length; i<l; i++) if (keys[i] !== 'second' && obj[keys[i]] < 1) delete obj[keys[i]];

		for (var arr = ['year','month','week','day','hour','minute','second'], l  = arr.length, i = 0, el; i<l; i++)
			if (obj[el = arr[i]] > 0){
				if (!obj.past && el !== 'second')
					obj[el]++;
				returnStr = timeparts(el,obj[el]);
				break;
			}

		if (returnStr.length === 0) return startval;
		if (returnStr === '1 day') return obj.past ? 'yesterday' : 'tomorrow';
		else return obj.past ? returnStr+' ago' : 'in '+returnStr;
	}
	update();
	window.updateTimesF = function(){
		update.apply(update,arguments);
	};
	if (window.noAutoUpdateTimes !== true) window.updateTimes = window.setInterval(update,10000);
})(jQuery);