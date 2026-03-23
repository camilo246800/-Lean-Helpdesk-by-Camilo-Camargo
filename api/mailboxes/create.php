<?php
// api/mailboxes/create.php
session_start();

// Proteger la ruta: Solo usuarios logueados pueden crear bandejas
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

// Validación básica
if (empty($data->name) || empty($data->email) || empty($data->mail_host) || empty($data->smtp_host)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO mailboxes 
        (name, email, protocol, mail_host, mail_port, mail_user, mail_pass, smtp_host, smtp_port, smtp_user, smtp_pass) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data->name,
        $data->email,
        $data->protocol ?? 'IMAP',
        $data->mail_host,
        $data->mail_port,
        $data->mail_user,
        $data->mail_pass,
        $data->smtp_host,
        $data->smtp_port,
        $data->smtp_user,
        $data->smtp_pass
    ]);

    echo json_encode(['success' => true, 'message' => 'Bandeja creada con éxito']);

} catch (PDOException $e) {
    // Si el correo ya existe (por el UNIQUE constraint), tirará error
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar la bandeja. ¿Quizás este correo ya está registrado?']);
}
?>