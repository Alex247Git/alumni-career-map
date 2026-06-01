/**
 * Charts Module
 * Google Charts - Bar chart of alumni distribution by country
 */

google.charts.load('current', { packages: ['corechart', 'bar'] });
google.charts.setOnLoadCallback(() => {
    // Will be called when data is ready
});

/**
 * Draw the country distribution bar chart
 */
// Map full country names to 2-letter ISO codes (for display on axis)
const COUNTRY_CODES = {
    'Greece': 'GR',
    'United Kingdom': 'UK',
    'Germany': 'DE',
    'Netherlands': 'NL',
    'Cyprus': 'CY',
    'United States': 'US',
    'Sweden': 'SE',
    'France': 'FR',
    'Italy': 'IT',
    'Spain': 'ES',
    'Unknown': '--'
};

function drawCountryChart(alumni) {
    const countryCounts = {};
    
    alumni.forEach(alumnus => {
        const jobs = alumnus.jobs || [];
        const job = jobs.find(j => j.is_current) || jobs[0];
        const country = job ? job.country : 'Unknown';
        countryCounts[country] = (countryCounts[country] || 0) + 1;
    });

    // Data columns: [shortCode, count, {role: 'tooltip'}]
    const data = [[
        { label: 'Country', type: 'string' },
        { label: 'Alumni', type: 'number' },
        { label: 'Tooltip', type: 'string', role: 'tooltip' }
    ]];

    Object.entries(countryCounts)
        .sort((a, b) => b[1] - a[1])
        .forEach(([country, count]) => {
            const code = COUNTRY_CODES[country] || country.substring(0, 2).toUpperCase();
            data.push([code, count, country + ': ' + count + ' alumni']);
        });

    const chartData = google.visualization.arrayToDataTable(data);
    
    const options = {
        title: 'Alumni Distribution by Country',
        titleTextStyle: { color: '#1a237e', fontSize: 14, bold: true },
        chartArea: { width: '70%', height: '60%', left: 50, top: 40 },
        colors: ['#283593'],
        hAxis: {
            title: '',
            slantedText: true,
            slantedTextAngle: 45,
            textStyle: { fontSize: 13, bold: true }
        },
        vAxis: {
            title: 'Alumni',
            minValue: 0,
            format: '0',
            textStyle: { fontSize: 11 }
        },
        legend: { position: 'none' },
        tooltip: { trigger: 'focus' },
        animation: {
            startup: true,
            duration: 500,
            easing: 'out'
        }
    };

    const chart = new google.visualization.ColumnChart(document.getElementById('chart_div'));
    chart.draw(chartData, options);
}
