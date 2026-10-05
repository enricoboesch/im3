<?php
// load.php – Schritt 3 (Load): Die aufgeräumten Daten in die Datenbank speichern.

// Dem Browser sagen: Die Antwort ist normaler Text
header('Content-Type: text/plain; charset=utf-8');

// Zugangsdaten aus config.php laden
require __DIR__ . '/config.php';


// try = "versuche das hier". Wenn etwas schiefgeht, springt PHP in den catch-Teil.
try {
    // Verbindung zur Datenbank aufbauen
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht. \n\n"; // Kurze Erfolgsmeldung ausgeben
}

// Falls die Verbindung nicht klappt: Fehlermeldung ausgeben und Skript beenden
catch (PDOException $e) {
    exit('Verbindung fehlgeschlagen: ' . $e->getMessage() . "\n");
}

// Transformierte Daten holen
$rows = include __DIR__ . '/transform.php';

// 1. Tabelle: Kantone
// Zwei Abfragen vorbereiten: eine zum Suchen, eine zum Einfügen eines Kantons.
// Das ? ist ein Platzhalter, der später mit dem echten Wert gefüllt wird.
$findCanton   = $pdo->prepare('SELECT id FROM cantons WHERE canton = ?');
$insertCanton = $pdo->prepare('INSERT INTO cantons (canton) VALUES (?)');

// Hier merken wir uns, welcher Kanton welche ID hat (z.B. 'Bern' => 3)
$cantonIds = [];

// Alle Zeilen durchgehen
foreach ($rows as $row) {
    $canton = $row['canton']; // Name des Kantons aus dieser Zeile

    // Wenn wir diesen Kanton schon kennen, müssen wir nichts tun
    if (isset($cantonIds[$canton])) {
        continue; // schon bekannt
    }

    // In der Datenbank nachschauen, ob es den Kanton schon gibt
    $findCanton->execute([$canton]);
    $id = $findCanton->fetchColumn(); // Die ID holen (oder false, wenn nichts gefunden)

    // Kanton gibt es noch nicht -> neu in die Tabelle einfügen
    if ($id === false) {
        $insertCanton->execute([$canton]);
        $id = $pdo->lastInsertId(); // Die ID, die die Datenbank gerade vergeben hat
    }

    // Kanton und ID für später merken
    $cantonIds[$canton] = $id;
}



// 2. Tabelle: Hotels

// Zuerst alle alten Einträge löschen, damit nichts doppelt drin ist
$deleted = $pdo->exec('DELETE FROM hotel_statistics');

// Abfrage zum Einfügen vorbereiten.
// :canton_id, :year usw. sind benannte Platzhalter für die echten Werte.
$insertHotel = $pdo->prepare(
    'INSERT INTO hotel_statistics (canton_id, year, registered_hotels, available_beds, bed_occupancy)
     VALUES (:canton_id, :year, :registered_hotels, :available_beds, :bed_occupancy)'
);

// Jede Zeile in die Tabelle schreiben
foreach ($rows as $row) {
    $insertHotel->execute([
        'canton_id'         => $cantonIds[$row['canton']], // Statt dem Namen speichern wir die ID des Kantons
        'year'              => $row['year'],               // Jahr
        'registered_hotels' => $row['registered_hotels'],  // Anzahl Hotels
        'available_beds'    => $row['available_beds'],     // Anzahl Betten
        'bed_occupancy'     => $row['bed_occupancy'],      // Bettenauslastung
    ]);
}

// Am Schluss ausgeben, wie viele Zeilen gespeichert wurden
echo count($rows) . ' Zeilen geladen.';

//Endlich geklappt.
