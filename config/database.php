<?php
// config/database.php

$host = 'localhost'; 
$db   = 'database';
$user = 'database user';
$pass = 'password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si falla, detenemos todo y mostramos un error limpio
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error crítico: No se pudo conectar a la base de datos.']);
    exit;
}
?>