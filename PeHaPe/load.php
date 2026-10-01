<?php

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/config.php';



try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht. \n\n";
}

catch (PDOException $e) {
    exit('Verbindung fehlgeschlagen: ' . $e->getMessage() . "\n");
}
// Transformierte Daten holen
$rows = include __DIR__ . '/transform.php';

// 1. Tabelle: Kantone
$findCanton   = $pdo->prepare('SELECT id FROM cantons WHERE canton = ?');
$insertCanton = $pdo->prepare('INSERT INTO cantons (canton) VALUES (?)');

$cantonIds = [];

foreach ($rows as $row) {
    $canton = $row['canton'];

    if (isset($cantonIds[$canton])) {
        continue; // schon bekannt
    }

    $findCanton->execute([$canton]);
    $id = $findCanton->fetchColumn();

    if ($id === false) {
        $insertCanton->execute([$canton]);
        $id = $pdo->lastInsertId();
    }

    $cantonIds[$canton] = $id;
}



// 2. Tabelle: Hotels

$deleted = $pdo->exec('DELETE FROM hotel_statistics');

$insertHotel = $pdo->prepare(
    'INSERT INTO hotel_statistics (canton_id, year, registered_hotels, available_beds, bed_occupancy)
     VALUES (:canton_id, :year, :registered_hotels, :available_beds, :bed_occupancy)'
);

foreach ($rows as $row) {
    $insertHotel->execute([
        'canton_id'         => $cantonIds[$row['canton']],
        'year'              => $row['year'],
        'registered_hotels' => $row['registered_hotels'],
        'available_beds'    => $row['available_beds'],
        'bed_occupancy'     => $row['bed_occupancy'],
    ]);
}

echo count($rows) . ' Zeilen geladen.';

