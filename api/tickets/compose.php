<?php
// api/tickets/compose.php
// Crea un ticket NUEVO a partir de un mensaje redactado manualmente (Outbound Ticket)
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

// --- DETECCIÓN: POST vacío por exceder post_max_size ---
// Cuando el tamaño total de la petición (incluyendo adjuntos) supera post_max_size,
// PHP descarta $_POST y $_FILES por completo sin avisar. Esto lo detecta y da un
// mensaje claro en vez del genérico "Faltan datos obligatorios".
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
if (empty($_POST) && empty($_FILES) && $contentLength > 0) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'message' => 'El archivo adjunto es demasiado grande para la configuración actual del servidor (post_max_size/upload_max_filesize). Reduce el tamaño del archivo o pide que se aumente el límite en el servidor.'
    ]);
    exit;
}

$mailbox_id    = isset($_POST['mailbox_id']) ? intval($_POST['mailbox_id']) : 0;
$to_email      = isset($_POST['to_email']) ? trim($_POST['to_email']) : '';
$to_name       = isset($_POST['to_name']) ? trim($_POST['to_name']) : '';
$subject       = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$body          = isset($_POST['body']) ? $_POST['body'] : '';

// --- Validación básica ---
if (empty($mailbox_id) || empty($to_email) || empty($subject) || empty($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios (bandeja, destinatario, asunto o mensaje)']);
    exit;
}

if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El correo del destinatario no es válido']);
    exit;
}

$mail = null;

try {
    // 1. Obtenemos la bandeja (credenciales SMTP)
    $stmt = $pdo->prepare("SELECT * FROM mailboxes WHERE id = ?");
    $stmt->execute([$mailbox_id]);
    $info = $stmt->fetch();

    if (!$info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bandeja no encontrada']);
        exit;
    }

    $mail = new PHPMailer(true);

    // Configuración SMTP (idéntica al patrón usado en reply.php)
    $mail->isSMTP();
    $mail->Host       = $info['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $info['smtp_user'];
    $mail->Password   = $info['smtp_pass'];
    $mail->SMTPSecure = ($info['smtp_port'] == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $info['smtp_port'];

    $mail->CharSet  = 'UTF-8';
    $mail->Encoding = 'base64';

    // El remitente técnico (verificado en el proveedor SMTP) con el nombre visible de la bandeja
    $mail->setFrom($info['smtp_user'], $info['name']);

    // Reply-To: usamos el alias configurado si existe, si no el email de la bandeja
    $replyEmail = !empty($info['reply_to_email']) ? $info['reply_to_email'] : $info['email'];
    $mail->addReplyTo($replyEmail, $info['name']);

    if (!empty($to_name)) {
        $mail->addAddress($to_email, $to_name);
    } else {
        $mail->addAddress($to_email);
    }

    $mail->Subject = $subject;
    $mail->isHTML(true);
    // nl2br + htmlspecialchars conserva emojis (UTF-8/utf8mb4) y evita inyección de HTML
    $mail->Body = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

    // --- PROCESAR ARCHIVOS ADJUNTOS (mismo patrón que reply.php) ---
    $uploaded_files = [];
    if (!empty($_FILES['attachments']['name'][0])) {
        $file_count = count($_FILES['attachments']['name']);

        for ($i = 0; $i < $file_count; $i++) {
            $tmp_name      = $_FILES['attachments']['tmp_name'][$i];
            $original_name = $_FILES['attachments']['name'][$i];
            $error         = $_FILES['attachments']['error'][$i];
            $mime_type     = $_FILES['attachments']['type'][$i];

            if ($error === UPLOAD_ERR_OK) {
                $safe_name   = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $original_name);
                $unique_name = time() . '_' . uniqid() . '_' . $safe_name;

                $relative_path = '/uploads/' . $unique_name;
                $absolute_path = __DIR__ . '/../..' . $relative_path;

                if (move_uploaded_file($tmp_name, $absolute_path)) {
                    $mail->addAttachment($absolute_path, $original_name);
                    $uploaded_files[] = [
                        'name' => $original_name,
                        'path' => $relative_path,
                        'mime' => $mime_type
                    ];
                }
            }
        }
    }

    // 2. Enviamos el correo real
    $mail->send();

    // 3. Creamos el TICKET nuevo (status OPEN)
    $stmtTicket = $pdo->prepare("INSERT INTO tickets (mailbox_id, customer_email, customer_name, subject, status) VALUES (?, ?, ?, ?, 'OPEN')");
    $stmtTicket->execute([$mailbox_id, $to_email, ($to_name !== '' ? $to_name : null), $subject]);
    $ticket_id = $pdo->lastInsertId();

    // 4. Guardamos el mensaje (is_from_customer = 0, igual que en reply.php)
    $stmtMsg = $pdo->prepare("INSERT INTO messages (ticket_id, body_text, is_from_customer) VALUES (?, ?, 0)");
    $stmtMsg->execute([$ticket_id, $body]);
    $message_id = $pdo->lastInsertId();

    // 5. Guardamos los adjuntos
    foreach ($uploaded_files as $file) {
        $stmtAtt = $pdo->prepare("INSERT INTO attachments (message_id, file_name, file_path, mime_type) VALUES (?, ?, ?, ?)");
        $stmtAtt->execute([$message_id, $file['name'], $file['path'], $file['mime']]);
    }

    echo json_encode(['success' => true, 'message' => 'Mensaje enviado y ticket creado correctamente', 'ticket_id' => $ticket_id]);

} catch (Exception $e) {
    http_response_code(500);
    $errInfo = $mail ? $mail->ErrorInfo : $e->getMessage();
    echo json_encode(['success' => false, 'message' => 'Error al enviar: ' . $errInfo]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
