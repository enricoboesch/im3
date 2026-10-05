// Erstellt die Liste der Jahre für die X-Achse
function yearsOf(rows) {
    return [...new Set(
        rows.map((row) => row.year)
    )].sort((a, b) => a - b);
}


// Findet alle Kantone
function cantonNamesOf(rows) {
    return [...new Set(
        rows.map((row) => row.canton)
    )].sort();
}


// Erstellt für einen Kanton eine Linie
function datasetFor(rows, canton) {

    const cantonRows = rows
        .filter((row) => row.canton === canton)
        .sort((a, b) => a.year - b.year);

    return {
        label: canton,

        data: cantonRows.map((row) => row.bed_occupancy),

        borderWidth: 2,
        pointRadius: 0,
        tension: 0.2
    };
}


// Daten laden
async function loadHotelChart() {

    const response = await fetch('PeHaPe/unload.php');


    // Prüfen, ob die Anfrage funktioniert hat
    if (!response.ok) {
        throw new Error(
            `Der Endpunkt antwortet mit Status ${response.status}.`
        );
    }


    // Prüfen, ob wirklich JSON zurückkommt
    const contentType =
        response.headers.get('content-type') ?? '';

    if (!contentType.includes('application/json')) {
        throw new Error('Die Antwort ist kein JSON.');
    }


    // JSON in JavaScript umwandeln
    const hotels = await response.json();


    // Nur Jahre 2006 bis 2025
    const chartData = hotels.filter(
        (row) => row.year >= 2006 && row.year <= 2025
    );


    // Jahre bestimmen
    const years = yearsOf(chartData);


    // Kantone bestimmen
    const cantons = cantonNamesOf(chartData);


    // Für jeden Kanton eine Linie erstellen
    const datasets = cantons.map(
        (canton) => datasetFor(chartData, canton)
    );


    // Chart erstellen
    const chart = new Chart(
        document.querySelector('#hotelChart'),
        {
            type: 'line',

            data: {
                labels: years,
                datasets: datasets
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,

                interaction: {
                    mode: 'index',
                    intersect: false
                },

                plugins: {
                    legend: {
                        display: false
                    }
                },

                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Jahr'
                        }
                    },

                    y: {
                        min: 0,
                        max: 100,

                        title: {
                            display: true,
                            text: 'Bettenauslastung (%)'
                        }
                    }
                }
            }
        }
    );
}


// Funktion starten
loadHotelChart();