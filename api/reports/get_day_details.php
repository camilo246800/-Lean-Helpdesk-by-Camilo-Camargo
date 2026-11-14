<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
require_once '../../config/database.php';

$dayName = $_GET['day'] ?? '';
$range = $_GET['range'] ?? 'week';

// Mapeo inverso para MySQL (Lunes -> 2)
$dayMap = ['Domingo'=>1, 'Lunes'=>2, 'Martes'=>3, 'Miércoles'=>4, 'Jueves'=>5, 'Viernes'=>6, 'Sábado'=>7];
$dayIndex = $dayMap[$dayName] ?? 0;

if ($range === 'current_week') {
    $condition = "YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($range === 'month') {
    $condition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} else {
    $condition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id, 
            t.subject, 
            t.customer_email,
            v.response_minutes
        FROM tickets t
        JOIN v_response_times v ON t.id = v.ticket_id
        WHERE $condition AND DAYOFWEEK(t.created_at) = ?
        ORDER BY v.response_minutes DESC
    ");
    $stmt->execute([$dayIndex]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'tickets' => $tickets]);
} catch (Exception $e) { echo json_encode(['success' => false]); }