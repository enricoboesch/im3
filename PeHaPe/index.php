<?php
// index.php – Zum Testen: Zeigt die transformierten Daten im Browser an (ohne Datenbank).

// Die aufgeräumten Daten aus transform.php holen
$data = include __DIR__ . '/transform.php';

// Dem Browser sagen: Die Antwort ist JSON (ein Datenformat für Webseiten)
header('Content-Type: application/json; charset=utf-8');

// Die Daten als JSON ausgeben.
// JSON_UNESCAPED_UNICODE: Umlaute bleiben lesbar (ü statt \u00fc)
// JSON_PRETTY_PRINT: schön eingerückt, damit man es besser lesen kann
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
