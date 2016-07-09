/* global DocReady,Chart,$w,Time,moment */
DocReady.push(function About(){
	'use strict';
	Chart.defaults.global.responsive = true;
	Chart.defaults.global.maintainAspectRatio = false;
	Chart.defaults.global.animation = false;

	let $stats = $('#stats'),
		getPointOptons = rgb => {
			let rgbstr = `${rgb.r},${rgb.g},${rgb.b}`,
				rgbrgbstr = `rgb(${rgbstr})`;
			return ({
				lineTension: 0,
				backgroundColor: `rgba(${rgbstr},0.2)`,
				borderColor: rgbrgbstr,
				borderWidth: 2,
				pointBackgroundColor: rgbrgbstr,
				pointRadius: 3,
				pointHitRadius: 6,
				pointBorderColor: "#fff",
				pointBorderWidth: 2,
				pointHoverBackgroundColor: "#fff",
				pointHoverBorderColor: rgbrgbstr,
			});
		},
		legend = {
			position: 'bottom',
			labels: { boxWidth: 12 },
		},
		xAxes = [{
            type: 'time',
            time: {
                unit: 'day',
                unitStepSize: 1,
                displayFormats: {
                    'day': 'Do MMM',
                },
            },
            ticks: {
				autoSkip: true,
                maxTicksLimit: 15,
            },
        }],
        yAxes = [{
            type: 'linear',
            ticks: {
				autoSkip: true,
                maxTicksLimit: 6,
            },
        }],
        ttformat = 'Do MMMM, YYYY';

	// Post Stats
	let $PostStats = $stats.children('.stats-posts'),
		$PostsTitle = $PostStats.children('h3'),
		$PostStatsLegend = $PostStats.children('.legend'),
		PostsCTX = $PostStats.find('canvas').get(0).getContext("2d"),
		PostsChart,
		PostLegendColors = ["#46ACD3","#5240C3"];
	$.post('/about/stats-posts',$.mkAjaxHandler(function(){
		if (!this.status) return $PostStats.remove();

		let Data = this.data;

		$.mk('p').append('Last updated: ', $.mk('time').attr('datetime', Data.timestamp)).insertAfter($PostsTitle);
		Time.Update();

		if (Data.datasets.length === 0)
			return $PostStatsLegend.html('<strong>No data available</strong>');
		$PostStatsLegend.remove();
		$.each(Data.datasets,function(i,el){
			let rgb = $.hex2rgb(PostLegendColors[el.clrkey]);

			$.extend(Data.datasets[i], getPointOptons(rgb));
		});

		let setChart = function(){ PostsChart = new Chart.Line(PostsCTX, {
			data: Data,
			options: {
				tooltips: {
					mode: 'label',
					callbacks: {
						title: function(tooltipItem){
							return moment(tooltipItem[0].xLabel).format(ttformat);
						},
					},
				},
				legend: legend,
				scales: {
					xAxes: xAxes,
					yAxes: yAxes,
				},
			}
		}) };
		setChart();
		// The chart must be re-created on resize because the .resize() method actually breaks it
		//$w.on('resize', setChart);
		$w.on('resize', function(){ PostsChart.resize() });
	}));

	// Approval Stats
	let $ApprovalStats = $stats.children('.stats-approvals'),
		$ApprovalTitle = $ApprovalStats.children('h3'),
		$ApprovalStatsLegend = $ApprovalStats.children('.legend'),
		ApprovalCTX = $ApprovalStats.find('canvas').get(0).getContext("2d"),
		ApprovalChart,
		ApprovalLegendColor = $.hex2rgb("#4DC742");
	$.post('/about/stats-approvals',$.mkAjaxHandler(function(){
		if (!this.status) return $ApprovalStats.remove();

		let Data = this.data,
			rgb = ApprovalLegendColor;

		$.mk('p').append('Last updated: ', $.mk('time').attr('datetime', Data.timestamp)).insertAfter($ApprovalTitle);
		Time.Update();

		if (Data.datasets.length === 0)
			return $ApprovalStatsLegend.html('<strong>No data available</strong>');
		$ApprovalStatsLegend.remove();
		$.extend(Data.datasets[0], getPointOptons(rgb));

		let setChart = function(){ ApprovalChart = new Chart.Line(ApprovalCTX, {
			data: Data,
			options: {
				tooltips: {
					mode: 'label',
					callbacks: {
						title: function(tooltipItem){
							return moment(tooltipItem[0].xLabel).format(ttformat);
						},
						label: function(tooltipItem){
							let approvedCount = parseInt(tooltipItem.yLabel, 10);
							return `${approvedCount===0?'No':approvedCount} post${approvedCount!==1?'s':''} approved`;
						}
					}
				},
				legend: legend,
				scales: {
					xAxes: xAxes,
					yAxes: yAxes,
				},
			},
		}) };
		setChart();
		// The chart must be re-created on resize because the .resize() method actually breaks it
		//$w.on('resize', setChart);
		$w.on('resize', function(){ ApprovalChart.resize() });
	}));
},function(){
	'use strict';
	delete window.Chart;
	$('script').filter('[src^="/js/Chart.min.js"], [data-src^="/js/Chart.min.js"]').remove();
});
