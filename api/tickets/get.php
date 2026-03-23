<?php
// api/tickets/get.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($ticket_id === 0) {
    echo json_encode(['success' => false, 'message' => 'ID de ticket inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
        exit;
    }

    $stmtMsg = $pdo->prepare("SELECT * FROM messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmtMsg->execute([$ticket_id]);
    $messages = $stmtMsg->fetchAll();

    // ¡NUEVO!: Buscamos los adjuntos de cada mensaje
    foreach ($messages as $key => $msg) {
        $stmtAtt = $pdo->prepare("SELECT id, file_name, file_path, mime_type FROM attachments WHERE message_id = ?");
        $stmtAtt->execute([$msg['id']]);
        $messages[$key]['attachments'] = $stmtAtt->fetchAll();
    }

    echo json_encode(['success' => true, 'ticket' => $ticket, 'messages' => $messages]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>