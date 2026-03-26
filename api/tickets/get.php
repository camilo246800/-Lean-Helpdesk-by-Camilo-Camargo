<?php
// api/tickets/get.php
session_start();

// 1. Verificación de seguridad
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de ticket no válido']);
    exit;
}

try {
    // 2. Obtener la cabecera del ticket (Incluimos customer_name para el nuevo diseño)
    $stmt = $pdo->prepare("SELECT id, customer_email, customer_name, subject, status FROM tickets WHERE id = ?");
    $stmt->execute([$id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
        exit;
    }

    // 3. Obtener todos los mensajes vinculados a este ticket
    $stmtMsgs = $pdo->prepare("SELECT id, body_html, body_text, is_from_customer, created_at FROM messages WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmtMsgs->execute([$id]);
    $messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);

    // 4. Bucle para inyectar los ADJUNTOS en cada mensaje (Lo que faltaba antes)
    foreach ($messages as &$msg) {
        $stmtAtt = $pdo->prepare("SELECT file_name, file_path, mime_type FROM attachments WHERE message_id = ?");
        $stmtAtt->execute([$msg['id']]);
        $msg['attachments'] = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Respuesta en JSON con soporte para caracteres especiales (UTF-8)
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'ticket' => $ticket,
        'messages' => $messages
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}