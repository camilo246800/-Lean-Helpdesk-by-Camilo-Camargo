<?php
// api/mailboxes/get.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID no válido']);
    exit;
}

try {
    // Al usar *, ya incluimos el nuevo campo reply_to_email
    $stmt = $pdo->prepare("SELECT * FROM mailboxes WHERE id = ?");
    $stmt->execute([$id]);
    $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mailbox) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true, 
            'mailbox' => $mailbox
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bandeja no encontrada']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
?>