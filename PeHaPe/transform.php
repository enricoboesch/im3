<?php
/**
 * Hotel-Daten transformieren – Lösung
 *
 * Die Kommentare erklären, was jeder Block tut und warum er so geschrieben ist.
 * Die TODO-Nummern markieren die Blöcke, die man im Unterricht der Reihe nach
 * bespricht.
 *
 * Datenfluss dieser Datei:
 *
 *   hotel_daten.csv, eine Zeile pro Kanton und Jahr (plus eine Schweiz-Zeile)
 *     -> Zeilen mit vereinheitlichten Kantonsnamen und echten Zahlen
 *       -> nur Kantonszeilen ($transformedRows), nur für vollständige Jahre
 *
 * Die Schweiz-Zeile wird verworfen. Landeswerte lassen sich bei Bedarf aus den
 * Kantonen berechnen (siehe Hinweis beim Rückgabewert).
 *
 * Anders als beim Hitzesommer wird hier NICHT aggregiert: Die Untersuchungseinheit
 * «ein Kanton in einem Jahr» ist schon in der Rohdatei so angelegt. Die Arbeit
 * liegt darin, die Zeilen vergleichbar zu machen.
 */

// ---------------------------------------------------------------------------
// TODO 1: Regeln festlegen
// ---------------------------------------------------------------------------
//
// Alle Entscheide stehen als benannte Variablen zuoberst und nicht als nackte
// Zahlen im Code. Wer eine Regel ändert, sieht sofort, wie sich die Daten ändern.

$csvPath = __DIR__ . 'Datenbank/hotel_daten.csv';

// Genau diese Spalten in genau dieser Reihenfolge erwartet der Code. Weicht der
// Header ab, stimmen alle Indizes unten nicht mehr.
$expectedHeader = ['year', 'canton', 'registered_hotels', 'available_beds', 'bed_occupancy'];

// Die Gesamtzeile heisst in der Datei "Schweiz". Sie ist kein 27. Kanton, sondern
// die Summe der Kantone. Sie wird verworfen, damit keine Summe und kein
// Durchschnitt über alle Zeilen doppelt zählt.
$totalRowName = 'Schweiz';

// In den Jahren 2006 und 2007 stehen sechs Kantone mit französischem oder
// italienischem Namen in der Datei, ab 2008 mit deutschem. Ohne Vereinheitlichung
// würde "Genève" 2007 aufhören und "Genf" 2008 neu beginnen – ein Namenswechsel,
// der im Chart aussieht wie zwei verschiedene Kantone.
$cantonAliases = [
    'Fribourg' => 'Freiburg',
    'Genève' => 'Genf',
    'Neuchâtel' => 'Neuenburg',
    'Ticino' => 'Tessin',
    'Valais' => 'Wallis',
    'Vaud' => 'Waadt',
];

// Die Schweiz hat 26 Kantone. Ein Jahr mit weniger Kantonszeilen ist unvollständig
// und würde den Vergleich zwischen Jahren verzerren.
$expectedCantonsPerYear = 26;

// ANNAHME: Die Datei enthält Werte für 2026. Ein ganzes Jahr 2026 kann es zum
// Zeitpunkt der Erstellung (September 2026) noch nicht geben. Wahrscheinlich
// sind das Werte für das laufende Jahr bis zu einem Stichmonat. Deshalb gilt
// 2025 als letztes vollständiges Jahr. Vor dem Einsatz an der Datenquelle (BFS)
// prüfen und gegebenenfalls anpassen.
$lastCompleteYear = 2025;

// Eine Auslastung ist ein Prozentwert und muss zwischen 0 und 100 liegen.
$occupancyMinPercent = 0.0;
$occupancyMaxPercent = 100.0;

// Diese Zähler machen Datenverluste sichtbar.
//
// input_rows ist der Anfang, output_rows das Ende. excluded_total_rows,
// invalid_values und incomplete_years zählen Weggeworfenes. renamed_cantons
// zählt keinen Verlust, sondern eine Veränderung.
//
// Bilanz: input_rows = excluded_total_rows + invalid_values
//                      + incomplete_years + output_rows
$audit = [
    'input_rows' => 0,
    'excluded_total_rows' => 0,
    'invalid_values' => 0,
    'renamed_cantons' => 0,
    'incomplete_years' => 0,
    'output_rows' => 0,
];

// ---------------------------------------------------------------------------
// TODO 2: Datei öffnen und Header prüfen
// ---------------------------------------------------------------------------

$handle = fopen($csvPath, 'r');
if ($handle === false) {
    throw new RuntimeException("Die Datei {$csvPath} konnte nicht geöffnet werden.");
}

// fgetcsv liest eine Zeile und zerlegt sie am Komma. Das letzte Argument ''
// schaltet das Escape-Zeichen ab.
$header = fgetcsv($handle, 0, ',', '"', '');

// BOM (unsichtbares Zeichen am Dateianfang, das Excel gerne schreibt) entfernen.
if ($header !== false && isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}

if ($header !== $expectedHeader) {
    fclose($handle);
    throw new RuntimeException('Der Header der CSV entspricht nicht dem erwarteten Aufbau.');
}

// ---------------------------------------------------------------------------
// TODO 3 bis 5: Zeilen einlesen, bereinigen und prüfen
// ---------------------------------------------------------------------------

// Zwischenspeicher, sortiert nach Jahr. So lässt sich in TODO 6 pro Jahr
// prüfen, ob alle Kantone da sind.
$cantonRowsByYear = [];

// Merkt sich, welche Kombination aus Jahr und Kanton schon vorkam.
$seenKeys = [];

while (($fields = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    // Eine leere Zeile liefert [null]. Sie ist keine Datenzeile.
    if ($fields === [null]) {
        continue;
    }

    $audit['input_rows']++;

    // TODO 3: Falsche Feldanzahl -> Abbruch statt Raten.
    if (count($fields) !== count($expectedHeader)) {
        fclose($handle);
        throw new RuntimeException("Zeile {$audit['input_rows']} hat nicht " . count($expectedHeader) . ' Felder.');
    }

    $row = array_combine($expectedHeader, $fields);

    // Die Schweiz-Zeile wird sofort verworfen – noch vor der Prüfung der
    // Zahlen. So landet sie nie unter invalid_values, sondern nur hier.
    $cantonOriginal = trim($row['canton']);
    if ($cantonOriginal === $totalRowName) {
        $audit['excluded_total_rows']++;
        continue;
    }

    // TODO 4: Zahlen bereinigen. Das Apostroph ist das Schweizer
    // Tausendertrennzeichen (3'961); (int) "3'961" ergäbe still 3.
    $yearText = trim($row['year']);
    $hotelsText = str_replace("'", '', trim($row['registered_hotels']));
    $bedsText = str_replace("'", '', trim($row['available_beds']));
    $occupancyText = str_replace(["'", '%'], '', trim($row['bed_occupancy']));

    // Leere oder kaputte Werte sind NICHT 0, sondern eine Lücke.
    if (
        !ctype_digit($yearText)
        || !ctype_digit($hotelsText)
        || !ctype_digit($bedsText)
        || !is_numeric($occupancyText)
    ) {
        $audit['invalid_values']++;
        continue;
    }

    $occupancy = (float) $occupancyText;

    if ($occupancy < $occupancyMinPercent || $occupancy > $occupancyMaxPercent) {
        $audit['invalid_values']++;
        continue;
    }

    // TODO 5: Kantonsnamen vereinheitlichen.
    $canton = $cantonAliases[$cantonOriginal] ?? $cantonOriginal;
    if ($canton !== $cantonOriginal) {
        $audit['renamed_cantons']++;
    }

    $year = (int) $yearText;

    // Doppelte Kombination Jahr/Kanton ist ein Strukturfehler.
    $key = $canton . '-' . $year;
    if (isset($seenKeys[$key])) {
        fclose($handle);
        throw new RuntimeException("Die Kombination {$key} kommt mehrfach vor.");
    }
    $seenKeys[$key] = true;

    $cantonRowsByYear[$year][] = [
        'year' => $year,
        'canton' => $canton,
        'registered_hotels' => (int) $hotelsText,
        'available_beds' => (int) $bedsText,
        'bed_occupancy_percent' => $occupancy,
    ];
}

fclose($handle);

// ---------------------------------------------------------------------------
// TODO 6: unvollständige Jahre entfernen
// ---------------------------------------------------------------------------
//
// Ein Jahr kommt nur in die Ausgabe, wenn es (a) nicht nach dem letzten
// vollständigen Jahr liegt und (b) exakt alle 26 Kantone hat.

$transformedRows = [];

foreach ($cantonRowsByYear as $year => $rows) {
    if ($year > $lastCompleteYear || count($rows) !== $expectedCantonsPerYear) {
        $audit['incomplete_years'] += count($rows);
        continue;
    }

    foreach ($rows as $row) {
        $transformedRows[] = $row;
    }
}

// ---------------------------------------------------------------------------
// TODO 7: sortieren
// ---------------------------------------------------------------------------

usort($transformedRows, function (array $a, array $b): int {
    return [$a['year'], $a['canton']] <=> [$b['year'], $b['canton']];
});

$audit['output_rows'] = count($transformedRows);

// Der Rückgabewert ist der Datenvertrag dieses Schritts: ein PHP-Array, kein JSON.
//
// Landeswerte aus den Kantonen berechnen:
// - registered_hotels und available_beds: Summe über alle Kantone eines Jahres.
// - bed_occupancy_percent: NICHT der einfache Durchschnitt der Kantone, weil
//   Graubünden mit rund 39'000 Betten sonst gleich viel zählt wie Glarus mit
//   rund 1'500. Näherung: nach available_beds gewichteter Durchschnitt.
//   Die BFS-Landeswerte können davon leicht abweichen (Rundung, saisonal
//   geschlossene Betriebe).
return [
    // ANNAHME: Beispielfrage, weil im Auftrag keine Frage eingesetzt war.
    'question' => 'Wie hat sich die Bettenauslastung pro Kanton über die Jahre verändert?',
    'rules' => [
        'excluded_total_row_name' => $totalRowName,
        'canton_aliases' => $cantonAliases,
        'expected_cantons_per_year' => $expectedCantonsPerYear,
        'last_complete_year' => $lastCompleteYear,
        'occupancy_range_percent' => [$occupancyMinPercent, $occupancyMaxPercent],
    ],
    'data' => $transformedRows,
    'audit' => $audit,
];