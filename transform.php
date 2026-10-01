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
 *   hotel_daten.csv, eine Zeile pro Kanton und Jahr
 *     -> Zeilen mit vereinheitlichten Kantonsnamen und echten Zahlen
 *       -> Kantonszeilen ($transformedRows) und Schweiz-Zeilen ($nationalRows),
 *          nur für vollständige Jahre
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

$csvPath = __DIR__ . '/hotel_daten.csv';

// Genau diese Spalten in genau dieser Reihenfolge erwartet der Code. Weicht der
// Header ab, stimmen alle Indizes unten nicht mehr.
$expectedHeader = ['year', 'canton', 'registered_hotels', 'available_beds', 'bed_occupancy'];

// Die Gesamtzeile heisst in der Datei "Schweiz". Sie ist kein 27. Kanton, sondern
// die Summe der Kantone. Würde sie mit den Kantonen vermischt, wäre jede Summe
// oder jeder Durchschnitt über alle Zeilen doppelt gezählt.
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
// sind das Werte für das laufende Jahr bis zu einem Stichmonat. Das ist das
// gleiche Problem wie der angebrochene Sommer im Hitzesommer-Beispiel: Ein
// Teiljahr neben ganzen Jahren sieht aus wie ein Befund, ist aber ein Datenfehler.
// Deshalb gilt 2025 als letztes vollständiges Jahr. Vor dem Einsatz an der
// Datenquelle (BFS) prüfen und gegebenenfalls anpassen.
$lastCompleteYear = 2025;

// Eine Auslastung ist ein Prozentwert und muss zwischen 0 und 100 liegen.
$occupancyMinPercent = 0.0;
$occupancyMaxPercent = 100.0;

// Diese Zähler machen Datenverluste sichtbar.
//
// Wie beim Hitzesommer messen sie nicht alle dasselbe: input_rows ist der Anfang,
// output_rows das Ende (nur Kantone), national_rows die abgetrennten
// Schweiz-Zeilen. invalid_values und incomplete_years zählen Weggeworfenes.
// renamed_cantons zählt keinen Verlust, sondern eine Veränderung – auch die soll
// sichtbar sein.
$audit = [
    'input_rows' => 0,
    'invalid_values' => 0,
    'renamed_cantons' => 0,
    'incomplete_years' => 0,
    'national_rows' => 0,
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
// schaltet das Escape-Zeichen ab; sonst behandelt PHP einen Backslash im Text
// als Sonderzeichen.
$header = fgetcsv($handle, 0, ',', '"', '');

// Das BOM ist ein unsichtbares Zeichen (drei Bytes) am Dateianfang, das Excel
// gerne schreibt. Bleibt es stehen, heisst die erste Spalte nicht "year",
// sondern "\xEF\xBB\xBFyear" – sie sieht gleich aus, ist aber ein anderer Text.
if ($header !== false && isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}

// Passt der Header nicht, gehört ab hier jeder Wert zur falschen Spalte. Das ist
// wie die ungleich langen Listen im Hitzesommer: kein Verlust, den man zählt,
// sondern ein Abbruch.
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
$nationalRowsByYear = [];

// Merkt sich, welche Kombination aus Jahr und Kanton schon vorkam.
$seenKeys = [];

while (($fields = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    // Eine leere Zeile (etwa am Dateiende) liefert [null]. Sie ist keine
    // Datenzeile und wird deshalb auch nicht als input_row gezählt.
    if ($fields === [null]) {
        continue;
    }

    $audit['input_rows']++;

    // TODO 3: Hat eine Zeile mehr oder weniger Felder als der Header, ist die
    // Zuordnung Spalte–Wert nicht mehr gesichert. Abbruch statt Raten.
    if (count($fields) !== count($expectedHeader)) {
        fclose($handle);
        throw new RuntimeException("Zeile {$audit['input_rows']} hat nicht " . count($expectedHeader) . ' Felder.');
    }

    // array_combine macht aus zwei Listen ein assoziatives Array:
    // ['year' => '2026', 'canton' => 'Zürich', ...]. So kann man unten mit
    // Namen statt mit Indizes arbeiten.
    $row = array_combine($expectedHeader, $fields);

    // TODO 4: Zahlen bereinigen. Das Apostroph ist in der Schweiz das
    // Tausendertrennzeichen (3'961). PHP versteht es nicht als Teil einer
    // Zahl: (int) "3'961" ergäbe 3 – still und falsch. Deshalb wird es vorher
    // entfernt. trim entfernt Leerzeichen am Rand.
    $yearText = trim($row['year']);
    $hotelsText = str_replace("'", '', trim($row['registered_hotels']));
    $bedsText = str_replace("'", '', trim($row['available_beds']));
    $occupancyText = str_replace(["'", '%'], '', trim($row['bed_occupancy']));

    // Leere oder kaputte Werte sind NICHT 0. Ein Kanton mit «0 Hotels» wäre eine
    // Behauptung, ein fehlender Wert ist nur eine Lücke. ctype_digit prüft, dass
    // der Text nur aus Ziffern besteht – ein leerer Text fällt dabei durch.
    // is_numeric lässt bei der Auslastung auch den Dezimalpunkt zu.
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

    // Ein Wert wie 450 wäre zwar eine Zahl, aber keine mögliche Auslastung.
    if ($occupancy < $occupancyMinPercent || $occupancy > $occupancyMaxPercent) {
        $audit['invalid_values']++;
        continue;
    }

    // TODO 5: Kantonsnamen vereinheitlichen. Der Operator ?? liefert den
    // deutschen Namen, falls es einen Eintrag in der Liste gibt, sonst den
    // Namen unverändert.
    $cantonOriginal = trim($row['canton']);
    $canton = $cantonAliases[$cantonOriginal] ?? $cantonOriginal;
    if ($canton !== $cantonOriginal) {
        $audit['renamed_cantons']++;
    }

    $year = (int) $yearText;

    // Gibt es dieselbe Kombination zweimal, weiss niemand, welche Zeile stimmt.
    // Das ist ein Strukturfehler und kein einzelner kaputter Wert.
    $key = $canton . '-' . $year;
    if (isset($seenKeys[$key])) {
        fclose($handle);
        throw new RuntimeException("Die Kombination {$key} kommt mehrfach vor.");
    }
    $seenKeys[$key] = true;

    $cleanRow = [
        'year' => $year,
        'canton' => $canton,
        'registered_hotels' => (int) $hotelsText,
        'available_beds' => (int) $bedsText,
        'bed_occupancy_percent' => $occupancy,
    ];

    // Hier trennt sich die Gesamtzeile von den Kantonen.
    if ($canton === $totalRowName) {
        $nationalRowsByYear[$year] = $cleanRow;
    } else {
        $cantonRowsByYear[$year][] = $cleanRow;
    }
}

fclose($handle);

// ---------------------------------------------------------------------------
// TODO 6: unvollständige Jahre entfernen
// ---------------------------------------------------------------------------
//
// Ein Jahr kommt nur in die Ausgabe, wenn es (a) nicht nach dem letzten
// vollständigen Jahr liegt und (b) exakt alle 26 Kantone hat. Exakt (!==) und
// nicht «mindestens», weil 27 Kantone genauso ein Fehler wären wie 25.
// Gezählt werden die weggeworfenen ZEILEN, damit man im Audit sieht, wie viel
// Material verloren geht.

$transformedRows = [];
$nationalRows = [];

foreach ($cantonRowsByYear as $year => $rows) {
    if ($year > $lastCompleteYear || count($rows) !== $expectedCantonsPerYear) {
        // Die Schweiz-Zeile dieses Jahres fällt mit weg und wird mitgezählt,
        // sonst verschwände sie still aus der Bilanz.
        $audit['incomplete_years'] += count($rows) + (isset($nationalRowsByYear[$year]) ? 1 : 0);
        continue;
    }

    foreach ($rows as $row) {
        $transformedRows[] = $row;
    }

    if (isset($nationalRowsByYear[$year])) {
        $nationalRows[] = $nationalRowsByYear[$year];
    }
}

// ---------------------------------------------------------------------------
// TODO 7: sortieren
// ---------------------------------------------------------------------------
//
// Wie im Hitzesommer: PHP vergleicht zwei Arrays mit <=> elementweise, zuerst
// year, bei Gleichstand canton.

usort($transformedRows, function (array $a, array $b): int {
    return [$a['year'], $a['canton']] <=> [$b['year'], $b['canton']];
});

usort($nationalRows, function (array $a, array $b): int {
    return $a['year'] <=> $b['year'];
});

$audit['national_rows'] = count($nationalRows);
$audit['output_rows'] = count($transformedRows);

// Der Rückgabewert ist der Datenvertrag dieses Schritts: ein PHP-Array, kein JSON.
//
// Zusätzlich zu question, rules, data und audit gibt es den Schlüssel national..
// Dort liegen die Schweiz-Werte getrennt von den Kantonen. So kann ein Chart die
// Landeslinie als Referenz zeigen, ohne dass sie in data mitgezählt wird.
return [
    // ANNAHME: Beispielfrage, weil im Auftrag keine Frage eingesetzt war.
    'question' => 'Wie hat sich die Bettenauslastung pro Kanton über die Jahre verändert?',
    'rules' => [
        'total_row_name' => $totalRowName,
        'canton_aliases' => $cantonAliases,
        'expected_cantons_per_year' => $expectedCantonsPerYear,
        'last_complete_year' => $lastCompleteYear,
        'occupancy_range_percent' => [$occupancyMinPercent, $occupancyMaxPercent],
    ],
    'data' => $transformedRows,
    'national' => $nationalRows,
    'audit' => $audit,
];