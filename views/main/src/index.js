console.log('IM INDEX.JS!');

import Chart from 'chart.js/auto';
import 'chartjs-adapter-moment';


class UptimeChart {

    _dataset_mapping;
    _chart;
    _interval;

    constructor(canvas, initialDatasets) {
        // Map the datasets
        let datasets = [];
        let index = 0;
        this._dataset_mapping = {};
        for (let name in initialDatasets) {
            this._dataset_mapping[name] = index;
            const obj = Object.assign({
                label: name,
                data: [],
                fill: true,
                borderWidth: 1
            }, initialDatasets[name]);
            datasets[index++] = obj;
        }


        // Create the charts
        this._chart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: { datasets },
            options: {
                legend: { labels: { fontColor: 'white' } },
                scales: {
                    x: {
                        type: 'time',
                        display: true,
                        time: {
                            unit: 'minute',
                            displayFormats: {
                            }
                        },
                        scaleLabel: { display: false },
                        gridLines: { drawBorder: false, display: false, color: "#333447" },
                        ticks: {
                            beginAtZero: false,
                            fontColor: "white",
                        }
                    },
                    y: {
                        gridLines: { drawBorder: false, display: false, color: "#333447" },
                        ticks: {
                            beginAtZero: true,
                            fontColor: "white",
                        }
                    }
                }
            }
        });
    }

    clear() {
        for(let index in this._chart.data.datasets) {
            this._chart.data.datasets[index].data.length = 0;
        }
    }

    push(name, value, time) {
        let index = this._dataset_mapping[name] ?? -1;
        if (index < 0) { 
            console.error('unkown dataset ', name);
            return false;
        }

        this._chart.data.datasets[index].data.push({
            x: time,
            y: value
        });

        this._chart.update('none');
    }

    async fetch(endpoint) {
        let response = await fetch(endpoint);
        let message = await response.json();

        this.clear();
        for(let stat of message.data) {
            const time = stat.timest;
            for(let name in this._dataset_mapping) {
                if (stat[name]) {
                    this.push(name, stat[name], time);
                }
            }
        }
    }

    startPolling(endpoint, interval) {
        if (this._interval) 
            this.stopPolling();

        this._interval = window.setInterval(async () => {
           await this.fetch(endpoint);
        }, interval);
    }

    stopPolling() {
        if (!this._interval) return;
        window.clearInterval(this._interval);
        this._interval = null;
    }
}

const STATUS_ENDPOINT = '/api/status';
const uptime = document.getElementById('chart-uptime');
const chart = new UptimeChart(uptime, {
    'games_count': {
        label: 'Games',
        backgroundColor: '#009100',
        borderColor: '#007200',
    },
    'users_count': {
        label: 'Users',
        backgroundColor: '#dadab6',
        borderColor: '#8c8c75',
    },
});
chart.startPolling(STATUS_ENDPOINT,  15 * 1000);
chart.fetch(STATUS_ENDPOINT);