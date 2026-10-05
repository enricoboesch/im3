<?php
// Das Extract ist der erste Schritt, wenn man eine Datenbank gefunden hat.

// file() liest das CSV und erstellt eine Liste. FILE_IGNORE_NEW_LINES ignoriert die Zeilenumbrüche.
//FILE_SKIP_EMPTY_LINES überspringt Leere Zeilen.

$lines = file(__DIR__ . '/../Datenbank/hotel_daten.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lines[0] = str_replace("\u{FEFF}", '', $lines[0]); // BOM entfernen

// Manche CSV-Dateien haben am Anfang ein unsichtbares Zeichen (BOM).
// Das wird hier aus der ersten Zeile gelöscht, sonst stimmt der erste Spaltenname nicht.

$rows = []; //Leere Liste, in die wir Zeilen packen

// Hier gehen wir alle Zeilen einzeln durch
foreach ($lines as $line) {

    // trim() entfernt Leerzeichen am Rand,
    // str_getcsv() trennt die Zeile bei den Kommas in einzelne Werte auf

    $rows[] = str_getcsv(trim($line));
}

//Fertige Liste wird an Datei Zurückgegeben, die extract.php aufruft im transform.php
return $rows;