/* global moment */
(function($){
	'use strict';
	var dateformat = { order: 'Do MMMM YYYY, H:mm:ss' };
	dateformat.orderwd = 'dddd, '+dateformat.order;

	// http://stackoverflow.com/q/783818/1344955#comment44922952_17891099
	function DateFormatError(message, element){
		var error = Error.call(this, message);

        this.name = 'DateFormatError';
		this.message = error.message;
        this.stack = error.stack;
		this.element = element;
	}
	DateFormatError.prototype = Object.create(Error.prototype);
	DateFormatError.prototype.constructor = DateFormatError;

	function TimeUpdate(){
		$('time[datetime]:not(.nodt)').addClass('dynt').each(function(){
			var $this = $(this),
				date = $this.attr('datetime');
			if (typeof date !== 'string') throw new TypeError('Invalid date data type: "'+(typeof date)+'"');

			var Timestamp = moment(date);
			if (!Timestamp.isValid())
				throw new DateFormatError('Invalid date format: "'+date+'"', this);

			var Now = moment(),
				showDayOfWeek = !$this.attr('data-noweekday'),
				timeAgoStr = Timestamp.from(Now),
				$elapsedHolder = $this.parent().children('.dynt-el'),
				updateHandler = $this.data('dyntime-beforeupdate');

			if (typeof updateHandler === 'function'){
				var result = updateHandler(timeDifference(Now.toDate(), Timestamp.toDate()));
				if (result === false) return;
			}

			if ($elapsedHolder.length > 0){
				$this.html(Timestamp.format(showDayOfWeek ? dateformat.orderwd : dateformat.order));
				$elapsedHolder.html(timeAgoStr);
			}
			else $this.attr('title', Timestamp.format(dateformat.order)).html(timeAgoStr);
		});
	}

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
	window.getTimeDiff = function(){ return timeDifference.apply(this, arguments) };

	var one = {
		'year':   31557600,
		'month':  2592000,
		'week':   604800,
		'day':    86400,
		'hour':   3600,
		'minute': 60,
		'second': 1,
	};
	window.one = (function(){return one})();

	TimeUpdate();
	window.updateTimes = function(){ TimeUpdate.apply(this, arguments) };
	window.timeUpdateInterval = window.setInterval(TimeUpdate, 10e3);
})(jQuery);
