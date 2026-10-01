<?php
// CSV einlesen: gibt eine Liste von Zeilen zurück (jede Zeile = Array mit Werten).
$lines = file(__DIR__ . '/../Datenbank/hotel_daten.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lines[0] = str_replace("\u{FEFF}", '', $lines[0]); // BOM entfernen

$rows = [];
foreach ($lines as $line) {
    $rows[] = str_getcsv(trim($line));
}

return $rows;