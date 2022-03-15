async function createCharts() {

    // Load the charts
    await google.charts.load('current', {packages: ['corechart', 'line', 'geochart']});
    await Promise.all([
        createUptimeCharts(),
        createDailyCharts()
    ]);
}

async function createDailyCharts() {


    const response = await fetch('/api/status/daily');
    const data = (await response.json()).data;
    
    createLineChart('visitor_count', data.users, 'Date', 'Visitors');
    createLineChart('total_games_count', data.games, 'Date', 'Games');
    createGeoChart('country_map', data.countries, 'Country', 'Players');

    function createLineChart(elmId, data, xAxisLabel, yAxisLabel) {   
        const elm = document.getElementById(elmId);
        if (!elm) return;
        
        let entries = [ [ xAxisLabel, yAxisLabel ] ];
        for(let entry of data) {
            // the +dateString converts the string into a number
            const dateString = entry.date;
            const year  = +dateString.substring(0, 4);
            const month = +dateString.substring(4, 6);
            const day   = +dateString.substring(6, 8);

            entries.push([
                new Date(year, month - 1, day),
                +entry.value
            ]);
        }

        const chart = new google.visualization.LineChart(elm);
        const options = { hAxis: { title: entries[0][0] }, vAxis: { title: entries[0][1] }, legend: 'none', series: [ { color: '#ff6666' } ] };
        const table = new google.visualization.arrayToDataTable(entries);
        chart.draw(table, options);
    }

    function createGeoChart(elmId, data, xAxisLabel, yAxisLabel) {         
        const elm = document.getElementById(elmId);
        if (!elm) return;

        let entries = [ [ xAxisLabel, yAxisLabel ] ];
        for(let entry of data) {
            entries.push([
                entry.country,
                +entry.value
            ]);
        }

        const chart = new google.visualization.GeoChart(elm);
        const options = { 
            colorAxis: {
                colors: ['#FFFFFF', '#ff6666']
            },
        };
        const table = new google.visualization.arrayToDataTable(entries);
        chart.draw(table, options);
    }
}

async function createUptimeCharts() {
    const response = await fetch('/api/status/uptime?deep');
    const data = (await response.json()).data;
    
    // Build the user chart
    let tables = {
        'users_count':  [ ['Time (UTC)', 'Users Online' ] ],
        'games_count':  [ ['Time (UTC)', 'Active Games' ] ],
        'uptime':       [ ['Time (UTC)', 'Uptime (Seconds)' ] ],
    };

    for(let entry of data) {
        for(let tableName in tables) {
            const epoch = +entry['epoch'] * 1000;
            if (entry[tableName]) {
                tables[tableName].push([
                    new Date(epoch),
                    +entry[tableName]
                ]);
            }
        }
    }

    console.log(tables);
    for(let tableName in tables) {
        const elm = document.getElementById(tableName);
        if (!elm) continue;

        const chart = new google.visualization.LineChart(elm);
        const options = { hAxis: { title: tables[tableName][0][0] }, vAxis: { title: tables[tableName][0][1] }, legend: 'none', series: [ { color: '#ff6666' } ] };
        const data = new google.visualization.arrayToDataTable(tables[tableName]);
        chart.draw(data, options);
    }
}


createCharts();

