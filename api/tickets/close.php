<?php
// api/tickets/close.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->ticket_id)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE tickets SET status = 'CLOSED' WHERE id = ?");
    $stmt->execute([$data->ticket_id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cerrar: ' . $e->getMessage()]);
}
?>