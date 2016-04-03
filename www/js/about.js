/* global DocReady,Chart,$w */
DocReady.push(function About(){
	'use strict';
	Chart.defaults.global.responsive = true;
	Chart.defaults.global.maintainAspectRatio = false;
	Chart.defaults.global.animation = false;

	var $stats = $('#stats');

	// Post Stats
	var $PostStats = $stats.children('.stats-posts'),
		$PostsTitle = $PostStats.children('h3'),
		$PostStatsLegend = $PostStats.children('.legend'),
		PostsCTX = $PostStats.find('canvas').get(0).getContext("2d"),
		PostsChart,
		PostLegendColors = ["#46ACD3","#4262C7"];
	$.post('/about/stats-posts',$.mkAjaxHandler(function(){
		if (!this.status) return $PostStats.remove();

		var Data = this.data;

		$.mk('p').append('Last updated: ', $.mk('time').attr('datetime', Data.timestamp)).insertAfter($PostsTitle);
		window.updateTimes();

		if (Data.datasets.length === 0)
			return $PostStatsLegend.html('<strong>No data available</strong>');
		$.each(Data.datasets,function(i,el){
			var rgb = $.hex2rgb(PostLegendColors[el.clrkey]),
				rgbstr = rgb.r+','+rgb.g+','+rgb.b;
			$.extend(Data.datasets[i], {
				fillColor: 'rgba('+rgbstr+',0.2)',
				strokeColor: 'rgb('+rgbstr+')',
				pointColor: 'rgb('+rgbstr+')',
				pointStrokeColor: "#fff",
				pointHighlightFill: "#fff",
				pointHighlightStroke: 'rgb('+rgbstr+')',
			});
			$PostStatsLegend.append("<span><span class='sq' style='background-color:rgb("+rgbstr+")'></span><span>"+el.label+"</span></span>");
		});

		var setChart = function(){ PostsChart = new Chart(PostsCTX).Line(Data) };
		setChart();
		// The chart must be re-created on resize because the .resize() method actually breaks it
		$w.on('resize', setChart);
		//$w.on('resize', function(){ PostsChart.resize() });
	}));

	// Approval Stats
	var $ApprovalStats = $stats.children('.stats-approvals'),
		$ApprovalTitle = $ApprovalStats.children('h3'),
		$ApprovalStatsLegend = $ApprovalStats.children('.legend'),
		ApprovalCTX = $ApprovalStats.find('canvas').get(0).getContext("2d"),
		ApprovalChart,
		ApprovalLegendColor = $.hex2rgb("#4DC742");
	$.post('/about/stats-approvals',$.mkAjaxHandler(function(){
		if (!this.status) return $ApprovalStats.remove();

		var Data = this.data,
			rgb = ApprovalLegendColor,
			rgbstr = rgb.r+','+rgb.g+','+rgb.b;

		$.mk('p').append('Last updated: ', $.mk('time').attr('datetime', Data.timestamp)).insertAfter($ApprovalTitle);
		window.updateTimes();

		if (Data.datasets.length === 0)
			return $ApprovalStatsLegend.html('<strong>No data available</strong>');
		$.extend(Data.datasets[0], {
			fillColor: 'rgba('+rgbstr+',0.2)',
			strokeColor: 'rgb('+rgbstr+')',
			pointColor: 'rgb('+rgbstr+')',
			pointStrokeColor: "#fff",
			pointHighlightFill: "#fff",
			pointHighlightStroke: 'rgb('+rgbstr+')',
		});
		$ApprovalStatsLegend.append("<span><span class='sq' style='background-color:rgb("+rgbstr+")'></span><span>"+Data.datasets[0].label+"</span></span>");

		var setChart = function(){ ApprovalChart = new Chart(ApprovalCTX).Line(Data,{ showTooltips: false }) };
		setChart();
		// The chart must be re-created on resize because the .resize() method actually breaks it
		$w.on('resize', setChart);
		//$w.on('resize', function(){ ApprovalChart.resize() });
	}));
},function(){
	'use strict';
	delete window.Chart;
	$('script').filter('[src^="/js/Chart.js"], [data-src^="/js/Chart.js"]').remove();
});
