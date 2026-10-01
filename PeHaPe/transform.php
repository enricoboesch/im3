<?php
// Rohdaten holen
$rows = include __DIR__ . '/extract.php';

$header = array_shift($rows); // erste Zeile = Spaltennamen

// 2006/2007 stehen einige Kantone auf Französisch/Italienisch -> auf Deutsch vereinheitlichen
$cantonAliases = [
    'Fribourg'  => 'Freiburg',
    'Genève'    => 'Genf',
    'Neuchâtel' => 'Neuenburg',
    'Ticino'    => 'Tessin',
    'Valais'    => 'Wallis',
    'Vaud'      => 'Waadt',
];

$data = [];

foreach ($rows as $row) {
    $item = array_combine($header, $row);

    // "Schweiz" ist eine Summenzeile, kein Kanton
    if ($item['canton'] === 'Schweiz') {
        continue;
    }

    $data[] = [
        'year'              => (int) $item['year'],
        'canton'            => $cantonAliases[$item['canton']] ?? $item['canton'],
        'registered_hotels' => (int) $item['registered_hotels'],
        'available_beds'    => (int) $item['available_beds'],
        'bed_occupancy'     => (float) $item['bed_occupancy'],
    ];
}

return $data;