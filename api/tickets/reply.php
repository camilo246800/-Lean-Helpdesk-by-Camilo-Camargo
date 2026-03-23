<?php
// api/tickets/reply.php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../vendor/autoload.php';
require_once '../../config/database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ahora recibimos los datos por POST tradicional (para soportar archivos)
$ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
$body = isset($_POST['body']) ? $_POST['body'] : '';

if (empty($ticket_id) || empty($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT t.customer_email, t.subject, m.* FROM tickets t JOIN mailboxes m ON t.mailbox_id = m.id WHERE t.id = ?");
    $stmt->execute([$ticket_id]);
    $info = $stmt->fetch();

    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'Ticket no encontrado']);
        exit;
    }

    $mail = new PHPMailer(true);
    $encryption = ($info['smtp_port'] == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->isSMTP();
    $mail->Host       = $info['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $info['smtp_user'];
    $mail->Password   = $info['smtp_pass'];
    $mail->SMTPSecure = $encryption;
    $mail->Port       = $info['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($info['email'], $info['name']);
    $mail->addAddress($info['customer_email']);
    
    $subject = strpos(strtolower($info['subject']), 're:') === 0 ? $info['subject'] : 'Re: ' . $info['subject'];
    $mail->Subject = $subject;
    $mail->isHTML(false);
    $mail->Body = $body;

    // --- PROCESAR ARCHIVOS ADJUNTOS ---
    $uploaded_files = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $file_count = count($_FILES['attachments']['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            $tmp_name = $_FILES['attachments']['tmp_name'][$i];
            $original_name = $_FILES['attachments']['name'][$i];
            $error = $_FILES['attachments']['error'][$i];
            $mime_type = $_FILES['attachments']['type'][$i];

            if ($error === UPLOAD_ERR_OK) {
                $safe_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $original_name);
                $unique_name = time() . '_' . uniqid() . '_' . $safe_name;
                
                $relative_path = '/uploads/' . $unique_name; 
                $absolute_path = __DIR__ . '/../..' . $relative_path;

                // Movemos el archivo a la carpeta uploads
                if (move_uploaded_file($tmp_name, $absolute_path)) {
                    // Lo adjuntamos al correo
                    $mail->addAttachment($absolute_path, $original_name);
                    
                    // Guardamos la info para la base de datos
                    $uploaded_files[] = [
                        'name' => $original_name,
                        'path' => $relative_path,
                        'mime' => $mime_type
                    ];
                }
            }
        }
    }

    // Enviar el correo
    $mail->send();

    // Guardar el mensaje en la BD
    $stmtMsg = $pdo->prepare("INSERT INTO messages (ticket_id, body_text, is_from_customer) VALUES (?, ?, 0)");
    $stmtMsg->execute([$ticket_id, $body]);
    $message_id = $pdo->lastInsertId();

    // Guardar los adjuntos en la BD asociados a tu mensaje
    foreach ($uploaded_files as $file) {
        $stmtAtt = $pdo->prepare("INSERT INTO attachments (message_id, file_name, file_path, mime_type) VALUES (?, ?, ?, ?)");
        $stmtAtt->execute([$message_id, $file['name'], $file['path'], $file['mime']]);
    }

    echo json_encode(['success' => true, 'message' => 'Respuesta enviada correctamente']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al enviar correo: ' . $mail->ErrorInfo]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
}
?>