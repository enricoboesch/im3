function yearsOf(rows) {
    return [...new Set(
        rows.map((row) => row.year)
    )].sort((a, b) => a - b);
}


function cantonNamesOf(rows) {
    return [...new Set(
        rows.map((row) => row.canton)
    )].sort();
}


function datasetFor(rows, canton) {

    const cantonRows = rows
        .filter((row) => row.canton === canton)
        .sort((a, b) => a.year - b.year);

    return {
        label: canton,

        data: cantonRows.map(
            (row) => row.bed_occupancy
        ),

        borderWidth: 2,
        pointRadius: 0,
        tension: 0.2
    };
}


async function loadHotelChart() {

    const response = await fetch('PeHaPe/unload.php');

    if (!response.ok) {
        throw new Error(
            `Der Endpunkt antwortet mit Status ${response.status}.`
        );
    }

    const hotels = await response.json();


    // Nur Daten von 2006 bis 2025
    const chartData = hotels.filter(
        (row) => row.year >= 2006 && row.year <= 2025
    );


    const years = yearsOf(chartData);
    const cantons = cantonNamesOf(chartData);


    const cantonSelect =
        document.querySelector('#cantonSelect');

    const selectedCantonsContainer =
        document.querySelector('#selectedCantons');


    // Hier werden alle ausgewählten Kantone gespeichert
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

                interaction: {
                    mode: 'nearest',
                    intersect: false
                },

                plugins: {

                    legend: {
                        display: false
                    },

                    tooltip: {
                        mode: 'nearest',
                        intersect: false,

                        callbacks: {

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


    // Diese Funktion aktualisiert Linien und Tags
    function render() {

        // Für jeden ausgewählten Kanton eine Linie erstellen
        chart.data.datasets = selectedCantons.map(
            (canton) => datasetFor(chartData, canton)
        );

        chart.update();


        // Tags leeren
        selectedCantonsContainer.innerHTML = '';


        // Tags neu erstellen
        selectedCantons.forEach((canton) => {

            const tag = document.createElement('div');
            tag.classList.add('canton-tag');


            const name = document.createElement('span');
            name.textContent = canton;


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


            tag.appendChild(name);
            tag.appendChild(removeButton);

            selectedCantonsContainer.appendChild(tag);
        });
    }


    // Wenn im Dropdown ein Kanton gewählt wird
    cantonSelect.addEventListener(
        'change',
        function() {

            const selectedCanton =
                cantonSelect.value;


            // Nur hinzufügen, wenn noch nicht gewählt
            if (
                selectedCanton !== ''
                && !selectedCantons.includes(selectedCanton)
            ) {
                selectedCantons.push(selectedCanton);
            }


            // Dropdown wieder zurücksetzen
            cantonSelect.value = '';


            render();
        }
    );


    render();
}


// Start
loadHotelChart();