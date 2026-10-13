// --------------------------------------------------
// GRAFIK 1 – Entwicklung nach Kanton
// --------------------------------------------------


// Holt alle vorhandenen Jahre aus den Daten
function yearsOf(rows) {
    return [...new Set(
        rows.map((row) => row.year)
    )].sort((a, b) => a - b);
}


// Holt alle vorhandenen Kantonsnamen aus den Daten
function cantonNamesOf(rows) {
    return [...new Set(
        rows.map((row) => row.canton)
    )].sort();
}


// Erstellt eine einzelne Linie für einen ausgewählten Kanton
function datasetFor(rows, canton, index) {

    const cantonRows = rows
        .filter((row) => row.canton === canton)
        .sort((a, b) => a.year - b.year);


    // Farben aus dem CSS holen
    const styles =
        getComputedStyle(document.documentElement);

    const colors = [
        styles.getPropertyValue('--bar-green').trim(),
        styles.getPropertyValue('--bar-blue').trim(),
        styles.getPropertyValue('--bar-brown').trim()
    ];


    return {
        label: canton,

        data: cantonRows.map(
            (row) => row.bed_occupancy
        ),

        // Linienfarben:
        // grün, blau, braun, dann wieder von vorne
        borderColor: colors[index % colors.length],

        // Linien etwas dicker machen
        borderWidth: 4,

        // Keine Punkte auf der Linie anzeigen
        pointRadius: 0,

        // Linie leicht glätten
        tension: 0.2
    };
}


// Lädt und erstellt Grafik 1
async function loadHotelChart() {

    // Daten aus dem PHP-Endpunkt laden
    const response = await fetch('PeHaPe/unload.php');


    // Fehler anzeigen, falls der Endpunkt nicht funktioniert
    if (!response.ok) {
        throw new Error(
            `Der Endpunkt antwortet mit Status ${response.status}.`
        );
    }


    // JSON-Daten lesen
    const hotels = await response.json();


    // Nur Daten von 2005 bis 2025 verwenden
    const chartData = hotels.filter(
        (row) => row.year >= 2005 && row.year <= 2025
    );


    // Jahre und Kantone aus den Daten holen
    const years = yearsOf(chartData);
    const cantons = cantonNamesOf(chartData);


    // Dropdown auswählen
    const cantonSelect =
        document.querySelector('#cantonSelect');


    // Bereich auswählen, in dem die ausgewählten Kantone stehen
    const selectedCantonsContainer =
        document.querySelector('#selectedCantons');


    // Hier werden die ausgewählten Kantone gespeichert
    let selectedCantons = [];


    // Alle Kantone ins Dropdown schreiben
    cantons.forEach((canton) => {

        const option = document.createElement('option');

        option.value = canton;
        option.textContent = canton;

        cantonSelect.appendChild(option);
    });


    // Chart einmal erstellen
    const chart = new Chart(
        document.querySelector('#hotelChart'),
        {
            type: 'line',

            data: {
                labels: years,
                datasets: []
            },

            options: {
                responsive: true,
                maintainAspectRatio: false,


                // Beim Hover nur die nächstgelegene Linie anzeigen
                interaction: {
                    mode: 'nearest',
                    intersect: false
                },


                plugins: {

                    // Keine automatische Legende anzeigen
                    legend: {
                        display: false
                    },


                    // Tooltip beim Darüberfahren
                    tooltip: {
                        mode: 'nearest',
                        intersect: false,

                        callbacks: {

                            // Zeigt Kanton und Wert an
                            label: function(context) {

                                return context.dataset.label
                                    + ': '
                                    + context.parsed.y
                                    + '%';
                            }
                        }
                    }
                },


                scales: {

                    // X-Achse = Jahre
                    x: {
                        title: {
                            display: true,
                            text: 'Jahr'
                        }
                    },


                    // Y-Achse = Bettenauslastung
                    y: {
                        min: 10,
                        max: 60,

                        title: {
                            display: true,
                            text: 'Bettenauslastung (%)'
                        }
                    }
                }
            }
        }
    );


    // Aktualisiert Linien und Kantons-Tags
    function render() {

        // Für jeden ausgewählten Kanton eine Linie erstellen
        // index bestimmt die Farbe der Linie
        chart.data.datasets = selectedCantons.map(
            (canton, index) =>
                datasetFor(chartData, canton, index)
        );


        // Grafik aktualisieren
        chart.update();


        // Alte Tags entfernen
        selectedCantonsContainer.innerHTML = '';


        // Neue Tags für ausgewählte Kantone erstellen
        selectedCantons.forEach((canton) => {

            const tag =
                document.createElement('div');

            tag.classList.add('canton-tag');


            // Kantonsname
            const name =
                document.createElement('span');

            name.textContent = canton;


            // X-Button zum Entfernen
            const removeButton =
                document.createElement('button');

            removeButton.type = 'button';
            removeButton.textContent = '×';


            // Kanton wieder entfernen
            removeButton.addEventListener(
                'click',
                function() {

                    selectedCantons =
                        selectedCantons.filter(
                            (item) => item !== canton
                        );

                    render();
                }
            );


            // Name und X-Button in den Tag einfügen
            tag.appendChild(name);
            tag.appendChild(removeButton);


            // Tag unter der Grafik anzeigen
            selectedCantonsContainer.appendChild(tag);
        });
    }


    // Wenn im Dropdown ein Kanton gewählt wird
    cantonSelect.addEventListener(
        'change',
        function() {

            const selectedCanton =
                cantonSelect.value;


            // Nur hinzufügen, wenn noch nicht ausgewählt
            if (
                selectedCanton !== ''
                && !selectedCantons.includes(selectedCanton)
            ) {
                selectedCantons.push(selectedCanton);
            }


            // Dropdown wieder auf Anfang stellen
            cantonSelect.value = '';


            // Grafik neu zeichnen
            render();
        }
    );


    // Grafik beim Laden einmal starten
    render();
}


// Grafik 1 starten
loadHotelChart();



// --------------------------------------------------
// GRAFIK 2 – Corona 2019 bis 2023
// --------------------------------------------------


// Lädt und erstellt Grafik 2
async function loadCoronaChart() {

    // Daten aus dem PHP-Endpunkt laden
    const response = await fetch('PeHaPe/unload.php');


    // Fehler anzeigen, falls der Endpunkt nicht funktioniert
    if (!response.ok) {
        throw new Error(
            `Der Endpunkt antwortet mit Status ${response.status}.`
        );
    }


    // JSON-Daten lesen
    const hotels = await response.json();


    // Jahre, die in dieser Grafik gezeigt werden
    const years = [
        2019,
        2020,
        2021,
        2022,
        2023
    ];


    // Startjahr:
    // Index 0 bedeutet 2019
    let currentYearIndex = 0;


    // Überschrift mit aktuellem Jahr
    const yearTitle =
        document.querySelector('#currentYear');


    // Linker Pfeil
    const previousButton =
        document.querySelector('#previousYear');


    // Rechter Pfeil
    const nextButton =
        document.querySelector('#nextYear');


    // Farben aus dem CSS holen
    const styles =
        getComputedStyle(document.documentElement);


    const green =
        styles.getPropertyValue('--bar-green').trim();


    const blue =
        styles.getPropertyValue('--bar-blue').trim();


    const brown =
        styles.getPropertyValue('--bar-brown').trim();


    // Farben für die Balken
    const colors = [
        green,
        blue,
        brown
    ];


    // Balkendiagramm erstellen
    const chart = new Chart(
        document.querySelector('#coronaChart'),
        {
            type: 'bar',

            data: {
                labels: [],

                datasets: [
                    {
                        label: 'Bettenauslastung',
                        data: [],
                        backgroundColor: []
                    }
                ]
            },


            options: {
                responsive: true,
                maintainAspectRatio: false,


                plugins: {

                    // Keine Legende anzeigen
                    legend: {
                        display: false
                    },


                    // Tooltip beim Darüberfahren
                    tooltip: {

                        callbacks: {

                            // Zeigt Kanton und Prozentwert an
                            label: function(context) {

                                return context.label
                                    + ': '
                                    + context.parsed.y
                                    + '%';
                            }
                        }
                    }
                },


                scales: {

                    // Y-Achse von 0 bis 100 Prozent
                    y: {
                        min: 0,
                        max: 60,

                        ticks: {

                            // Prozentzeichen anzeigen
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },


                    // X-Achse mit Kantonsnamen
                    x: {

                        ticks: {
                            minRotation: 90,
                            maxRotation: 90
                        },


                        // Keine vertikalen Gitternetzlinien
                        grid: {
                            display: false
                        }
                    }
                }
            }
        }
    );


    // Zeigt die Daten für das aktuelle Jahr
    function showYear() {

        // Aktuelles Jahr bestimmen
        const year =
            years[currentYearIndex];


        // Nur Daten des ausgewählten Jahres nehmen
        const yearData = hotels
            .filter((row) => row.year === year)
            .sort((a, b) =>
                a.canton.localeCompare(b.canton)
            );


        // Kantonsnamen für die X-Achse
        const cantons =
            yearData.map(
                (row) => row.canton
            );


        // Bettenauslastung für die Balkenhöhe
        const values =
            yearData.map(
                (row) => row.bed_occupancy
            );


        // Balkenfarben abwechseln:
        // grün, blau, braun
        const barColors =
            yearData.map(
                (row, index) =>
                    colors[index % colors.length]
            );


        // Kantonsnamen einsetzen
        chart.data.labels =
            cantons;


        // Werte einsetzen
        chart.data.datasets[0].data =
            values;


        // Farben einsetzen
        chart.data.datasets[0].backgroundColor =
            barColors;


        // Grafik aktualisieren
        chart.update();


        // Jahr oben anzeigen
        yearTitle.textContent =
            year;


        // Linken Pfeil deaktivieren bei 2019
        previousButton.disabled =
            currentYearIndex === 0;


        // Rechten Pfeil deaktivieren bei 2023
        nextButton.disabled =
            currentYearIndex === years.length - 1;
    }


    // Klick auf linken Pfeil
    previousButton.addEventListener(
        'click',
        function() {

            if (currentYearIndex > 0) {

                currentYearIndex--;

                showYear();
            }
        }
    );


    // Klick auf rechten Pfeil
    nextButton.addEventListener(
        'click',
        function() {

            if (
                currentYearIndex
                < years.length - 1
            ) {

                currentYearIndex++;

                showYear();
            }
        }
    );


    // Grafik beim Laden anzeigen
    showYear();
}


// Grafik 2 starten
loadCoronaChart();