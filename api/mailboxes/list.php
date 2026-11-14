<?php
// api/mailboxes/list.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

try {
    // Traemos los datos de la bandeja Y contamos cuántos tickets tiene abiertos (OPEN)
    $stmt = $pdo->query("
        SELECT 
            m.id, 
            m.name, 
            m.email,
            (SELECT COUNT(id) FROM tickets WHERE mailbox_id = m.id AND status = 'OPEN') as pending_count
        FROM mailboxes m 
        ORDER BY m.name ASC
    ");
    $mailboxes = $stmt->fetchAll();

    echo json_encode(['success' => true, 'mailboxes' => $mailboxes]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener las bandejas']);
}
?>