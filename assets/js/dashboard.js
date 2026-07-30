(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var canvas = document.getElementById('bdi-chart-daily');
		var dataEl = document.getElementById('bdi-daily-data');

		if (!canvas || !dataEl || typeof Chart === 'undefined') {
			return;
		}

		var data;
		try {
			data = JSON.parse(dataEl.textContent);
		} catch (e) {
			return;
		}

		new Chart(canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: data.labels,
				datasets: [
					{
						label: 'Downloads',
						data: data.values,
						fill: true,
						tension: 0.3,
						borderWidth: 2,
					},
				],
			},
			options: {
				responsive: true,
				plugins: {
					legend: { display: false },
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: { precision: 0 },
					},
				},
			},
		});
	});
})();
