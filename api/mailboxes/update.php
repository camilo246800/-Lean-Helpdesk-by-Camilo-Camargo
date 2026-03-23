<?php
// api/mailboxes/update.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->id) || empty($data->email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE mailboxes SET 
            name = ?, email = ?, protocol = ?, 
            mail_host = ?, mail_port = ?, mail_user = ?, mail_pass = ?, 
            smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $data->name, $data->email, $data->protocol,
        $data->mail_host, $data->mail_port, $data->mail_user, $data->mail_pass,
        $data->smtp_host, $data->smtp_port, $data->smtp_user, $data->smtp_pass,
        $data->id
    ]);

    echo json_encode(['success' => true, 'message' => 'Configuración actualizada correctamente']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>