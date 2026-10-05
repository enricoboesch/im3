<?php

header('Content-Type: application/json; charset=utf-8');


require __DIR__ . '/config.php';


function normalizeHotels(array $row): array
{
    return [
        'canton' => $row['canton'],
        'year' => (int) $row['year'],
        'registered_hotels' => (int) $row['registered_hotels'],
        'available_beds' => (int) $row['available_beds'],

        // Ein fehlender Messwert bleibt null und wird nicht zu 0.0.
        // null heisst «wir wissen es nicht», 0.0 wäre eine Messung.
        'bed_occupancy' => $row['bed_occupancy'] === null
            ? null
            : (float) $row['bed_occupancy'],
    ];
}

$canton = trim($_GET['canton'] ?? '');



try {

    $pdo = new PDO($dsn, $username, $password, $options);

    $sql = 'SELECT c.canton AS canton,
                   hs.year,
                   hs.registered_hotels,
                   hs.available_beds,
                   hs.bed_occupancy
            FROM hotel_statistics AS hs
            JOIN cantons AS c ON c.id = hs.canton_id';



    $params = [];

    if ($canton !== '') {
        $sql .= ' WHERE c.canton = :canton';
        $params['canton'] = $canton;
    }

    $sql .= ' ORDER BY hs.year, c.canton';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);



    $data = array_map('normalizeHotels', $rows);


    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {

    http_response_code(500);
    error_log('unload.php: ' . $error->getMessage());

    echo json_encode([
        'error' => 'Daten konnten nicht geladen werden.',
    ]);
}