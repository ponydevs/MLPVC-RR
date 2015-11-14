/* global Chart */
$(function(){
	'use strict';
	Chart.defaults.global.responsive = true;

	var $stats = $('#stats'),
		PostsCTX = $stats.children('.stats-posts').get(0).getContext("2d"),
		PostsChart;

	$.post('/about/stats-posts',$.mkAjaxHandler(function(){
		if (!this.status) return $stats.closest('section').remove();

		PostsChart = new Chart(PostsCTX).Line(this.data);
	}));
});
