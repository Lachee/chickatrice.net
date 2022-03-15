async function createCharts() {

    // Load the charts
    const loadPromise = google.charts.load('current', {packages: ['corechart', 'line']});
    const dataPromise = (async () => {
        const response = await fetch('/api/status/uptime?deep');
        return (await response.json()).data;
    })();

    const [ _, data ] = await Promise.all([loadPromise, dataPromise]);
    
    // Build the user chart
    let tables = {
        'users_count':  [ ['Time (UTC)', 'Users Online' ] ],
        'games_count':  [ ['Time (UTC)', 'Active Games' ] ],
        'uptime':       [ ['Time (UTC)', 'Uptime (Seconds)' ] ],
    };

    for(let entry of data) {
        for(let tableName in tables) {
            const epoch = parseInt(entry['epoch']) * 1000;
            if (entry[tableName]) {
                tables[tableName].push([
                    new Date(epoch),
                    parseInt(entry[tableName])
                ]);
            }
        }
    }

    console.log(tables);
    for(let tableName in tables) {
        const elm = document.getElementById(tableName);
        if (!elm) continue;

        const chart = new google.visualization.LineChart(elm);
        const options = { hAxis: { title: tables[tableName][0][0] }, vAxis: { title: tables[tableName][0][1] }, legend: { position: 'none' } };
        const data = new google.visualization.arrayToDataTable(tables[tableName]);
        
        console.log('Loading Chart', tableName, elm, tables[tableName]);
        chart.draw(data, options);
    }
}


createCharts();

