<?php
/**
 * Code-Along 08: CSV lesen (Extract) – Lösung
 *
 * Quelle -> PHP-Array von Datensätzen, diesmal aus einer echten CSV-Datei
 * (Shark Attack File, GSAF).
 */

header('Content-Type: text/plain; charset=utf-8');

// 1. CSV-Datei zum Lesen öffnen.
$handle = fopen('hotel_daten.csv', 'r');

// 2. Kopfzeile (Spaltennamen) lesen und Leerzeichen entfernen. Einige Spalten
//    heissen z. B. "Species " mit Leerzeichen am Ende - trim() räumt das auf.
//    Die Argumente ',' '"' '' sind die Standardwerte; wir geben sie explizit
//    an, damit PHP keine Deprecation-Warnung zeigt.
$header = array_map('trim', fgetcsv($handle, null, ',', '"', ''));

// 3. Alle weiteren Zeilen lesen. Echte Daten haben viele leere Zeilen: wenn die
//    erste Spalte (Case Number) leer ist, überspringen wir die Zeile.
//    array_combine verbindet Spaltennamen mit den Werten -> ein Array pro Angriff.
$attacks = [];
while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
    if ($row[0] === '') {
        continue; // leere Zeile
    }
    $attacks[] = array_combine($header, $row);
}
fclose($handle);

// 4. Struktur prüfen.
echo 'Spalten: ' . implode(', ', $header) . "\n";
echo 'Anzahl Angriffe: ' . count($attacks) . "\n\n";

// 5. Die ersten 5 Angriffe ausgeben.
for ($i = 0; $i < 5; $i++) {
    $a = $attacks[$i];
    echo $a['year'] . ': ' . $a['canton'] . $a['registered_hotels']  ' – ' . $a['available_beds']
        . ' (' . $a['bed_occuppancy'] . ")\n";
}