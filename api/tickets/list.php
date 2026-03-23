<?php
// api/tickets/list.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$mailbox_id = isset($_GET['mailbox_id']) ? intval($_GET['mailbox_id']) : 0;
// Recibimos la palabra a buscar (si existe)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $sql = "
        SELECT 
            t.id, 
            t.customer_email, 
            t.subject, 
            t.status, 
            t.updated_at,
            mb.name as mailbox_name,
            (SELECT body_text FROM messages WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as snippet
        FROM tickets t
        JOIN mailboxes mb ON t.mailbox_id = mb.id
        WHERE 1=1
    ";
    
    $params = [];

    // Si pasamos un ID de bandeja mayor a 0, filtramos por bandeja
    if ($mailbox_id > 0) {
        $sql .= " AND t.mailbox_id = ? ";
        $params[] = $mailbox_id;
    }

    // Si escribimos algo en el buscador, filtramos por correo o asunto
    if (!empty($search)) {
        $sql .= " AND (t.customer_email LIKE ? OR t.subject LIKE ?) ";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY t.updated_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();

    echo json_encode(['success' => true, 'tickets' => $tickets]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>