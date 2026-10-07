<?php
// Transform ist der 2. Schritt. Hier räumen wir die Rohdaten auf. Referenzieren das extract hier:
$rows = include __DIR__ . '/extract.php';

$header = array_shift($rows); // Die erste Zeile wird ignoriert, also das sind die Spaltennamen.

// 2006/2007 stehen einige Kantone auf Französisch/Italienisch -> auf Deutsch vereinheitlichen
$cantonAliases = [
    'Fribourg'  => 'Freiburg',
    'Genève'    => 'Genf',
    'Neuchâtel' => 'Neuenburg',
    'Ticino'    => 'Tessin',
    'Valais'    => 'Wallis',
    'Vaud'      => 'Waadt',
];

$data = []; // Ist eine Leere Liste für die aufgeräumten Daten


//Jede Datenzeile wird einmal durchgegangen
foreach ($rows as $row) {
    //array_combine() verbindet spaltennamen und Werte
    //wird also umbenannt item canton schreiben statt row 1
    $item = array_combine($header, $row);

    // "Schweiz" ist eine Summenzeile, kein Kanton
    // continue = diese Zeile überspringen und mit der nächsten weitermachen
    // "Schweiz" ist eine Summenzeile, kein Kanton
    if ($item['canton'] === 'Schweiz') {
        continue;
    }

    // Nur Jahre von 2005 bis 2025 übernehmen, alle anderen überspringen
    $year = (int) $item['year'];
    if ($year < 2005 || $year > 2025) {
        continue;
    }
    // Neue, saubere Zeile zur Liste hinzufügen
    $data[] = [
        'year'              => (int) $item['year'],                                   // (int) macht aus dem Text eine ganze Zahl
        'canton'            => $cantonAliases[$item['canton']] ?? $item['canton'],   // Deutschen Namen nehmen, falls vorhanden, sonst den Originalnamen (?? = "sonst")
        'registered_hotels' => (int) $item['registered_hotels'],                      // Anzahl Hotels als ganze Zahl
        'available_beds'    => (int) $item['available_beds'],                         // Anzahl Betten als ganze Zahl
        'bed_occupancy'     => (float) $item['bed_occupancy'],                        // (float) macht eine Kommazahl daraus (Bettenauslastung)
    ];
}

// Die aufgeräumten Daten zurückgeben (an load.php oder index.php)
return $data;