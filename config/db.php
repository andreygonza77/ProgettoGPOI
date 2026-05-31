<?php
declare(strict_types=1);

// Tieni qui i dati del database: se cambi ambiente, questa e' la prima porta da aprire.

$dbHost = 'localhost';
$dbName = 'moneytracker';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    $dbHost,
    $dbName,
    $dbCharset
);

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = new PDO($dsn, $dbUser, $dbPass, $options);
