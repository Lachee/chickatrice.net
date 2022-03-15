console.log('IM INDEX.JS!');

import Chart from 'chart.js/auto';
import 'chartjs-adapter-moment';

/** converts milliseconds to readable time. https://stackoverflow.com/a/8212878/5010271 */
function millisecondsToStr (milliseconds) {
    // TIP: to find current time in milliseconds, use:
    // var  current_time_milliseconds = new Date().getTime();

    function numberEnding (number) {
        return (number > 1) ? 's' : '';
    }

    var temp = Math.floor(milliseconds / 1000);
    var years = Math.floor(temp / 31536000);
    if (years) {
        return years + ' year' + numberEnding(years);
    }
    //TODO: Months! Maybe weeks? 
    var days = Math.floor((temp %= 31536000) / 86400);
    if (days) {
        return days + ' day' + numberEnding(days);
    }
    var hours = Math.floor((temp %= 86400) / 3600);
    if (hours) {
        return hours + ' hour' + numberEnding(hours);
    }
    var minutes = Math.floor((temp %= 3600) / 60);
    if (minutes) {
        return minutes + ' minute' + numberEnding(minutes);
    }
    var seconds = temp % 60;
    if (seconds) {
        return seconds + ' second' + numberEnding(seconds);
    }
    return 'less than a second'; //'just now' //or other string you like;
}


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
                elements: { 
                    point: {
                        radius: 0
                    }
                },
                legend: { labels: { fontColor: 'white' } },
                scales: {
                    x: {
                        type: 'time',
                        display: true,
                        time: {
                            unit: 'minute',
                            displayFormats: {
                            },
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
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            fontColor: "white",
                        }
                    },
                    y_alt: {
                        gridLines: { drawBorder: false, display: false, color: "#333447" },
                        position: 'right',
                        beginAtZero: true,
                        ticks: {
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
        for(let stat of message.data.history) {
            const time = stat.timest;
            for(let name in this._dataset_mapping) {
                if (stat[name]) {
                    this.push(name, stat[name], time);
                }
            }
        }

        return message.data;
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

const STATUS_ENDPOINT = '/api/status/uptime';
const uptime = document.getElementById('chart-uptime');
const chart = new UptimeChart(uptime, {
    'games_count': {
        label: 'Games',
        backgroundColor: '#009100',
        borderColor: '#007200',
    },
    'users_count': {
        label: 'Users',
        backgroundColor: '#ff6666',
        borderColor: '#db1f1f',
    },
});



async function pollStatus() {
    // Update server time
    const timeElm = document.getElementById("server-time");
    if (timeElm) {
        timeElm.textContent = new Intl.DateTimeFormat('en-AU', {
            timeZone: 'Australia/Sydney',
            weekday: 'long',
            hour: 'numeric', 
            minute: 'numeric',
        }).format();
    }

    // Update chart
    const status = await chart.fetch(STATUS_ENDPOINT);

    // Update offlien detector
    const uptimeElm = document.getElementById("uptime");
    if (uptimeElm) {
        const uptime = status.uptime;
        console.log(status, uptime);
        if (uptime <= 0) {
            uptimeElm.textContent = "Currently Offline";
        } else {
            uptimeElm.textContent = "Online for " + millisecondsToStr(uptime * 1000);
        }
    }
}

//chart.startPolling(STATUS_ENDPOINT,  60 * 1000);
pollStatus();
setInterval(() => pollStatus(), 15 * 1000);