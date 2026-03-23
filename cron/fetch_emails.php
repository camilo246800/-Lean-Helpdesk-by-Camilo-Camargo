<?php
// cron/fetch_emails.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

echo "Iniciando proceso de lectura de correos...\n";

$config = HTMLPurifier_Config::createDefault();
$purifier = new HTMLPurifier($config);

$stmt = $pdo->query("SELECT * FROM mailboxes");
$mailboxes = $stmt->fetchAll();

$cm = new ClientManager();

foreach ($mailboxes as $mailbox) {
    echo "========================================\n";
    echo "Revisando bandeja: " . $mailbox['email'] . "\n";

    $encryption = ($mailbox['mail_port'] == 993) ? 'ssl' : 'starttls';

    $client = $cm->make([
        'host'          => $mailbox['mail_host'],
        'port'          => $mailbox['mail_port'],
        'encryption'    => $encryption, 
        'validate_cert' => false,
        'username'      => $mailbox['mail_user'],
        'password'      => $mailbox['mail_pass'],
        'protocol'      => strtolower($mailbox['protocol'] ?? 'imap')
    ]);

    try {
        $client->connect();
        echo "Conexión IMAP exitosa.\n";
        
        $folder = $client->getFolder('INBOX');
        $messages = $folder->query()->unseen()->get();
        echo "Mensajes no leídos encontrados: " . $messages->count() . "\n";

        foreach ($messages as $message) {
            $subject = $message->getSubject()[0] ?? 'Sin Asunto';
            
            // --- NUEVA LÓGICA PARA NOMBRE Y EMAIL ---
            $from = $message->getFrom()[0];
            $customer_email = $from->mail;
            // Capturamos el nombre (personal). Si no tiene, usamos el email.
            $customer_name = $from->personal ?: $customer_email; 
            
            $message_id = $message->getMessageId()[0] ?? uniqid();
            
            $body_html = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
            $body_text = $message->hasTextBody() ? $message->getTextBody() : 'Sin contenido de texto.';
            
            if ($body_html) {
                $body_html = $purifier->purify($body_html);
            }

            // --- Lógica de Hilos (Tickets) ---
            $stmtTicket = $pdo->prepare("SELECT id FROM tickets WHERE customer_email = ? AND subject = ? AND status != 'CLOSED' LIMIT 1");
            $stmtTicket->execute([$customer_email, $subject]);
            $existingTicket = $stmtTicket->fetch();

            if ($existingTicket) {
                $ticket_id = $existingTicket['id'];
                echo "-> Mensaje agregado al Ticket #$ticket_id\n";
            } else {
                // AGREGADO: Guardamos también el customer_name en el nuevo ticket
                $stmtNewTicket = $pdo->prepare("INSERT INTO tickets (mailbox_id, customer_email, customer_name, subject, status) VALUES (?, ?, ?, ?, 'OPEN')");
                $stmtNewTicket->execute([$mailbox['id'], $customer_email, $customer_name, $subject]);
                $ticket_id = $pdo->lastInsertId();
                echo "-> Nuevo Ticket creado #$ticket_id ($customer_name)\n";
            }

            // Guardar el mensaje
            $stmtMsg = $pdo->prepare("INSERT INTO messages (ticket_id, message_id_hash, body_html, body_text, is_from_customer) VALUES (?, ?, ?, ?, 1)");
            $stmtMsg->execute([$ticket_id, md5($message_id), $body_html, $body_text]);
            
            $db_message_id = $pdo->lastInsertId();

            // --- LÓGICA DE ADJUNTOS ---
            if ($message->hasAttachments()) {
                $attachments = $message->getAttachments();
                echo "   Contiene " . $attachments->count() . " adjunto(s). Descargando...\n";
                
                foreach ($attachments as $attachment) {
                    $original_name = $attachment->getName() ?: 'archivo_sin_nombre';
                    $safe_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '_', $original_name);
                    $unique_name = time() . '_' . uniqid() . '_' . $safe_name;
                    $relative_path = '/uploads/' . $unique_name; 
                    $absolute_path = __DIR__ . '/..' . $relative_path;

                    file_put_contents($absolute_path, $attachment->getContent());

                    $stmtAtt = $pdo->prepare("INSERT INTO attachments (message_id, file_name, file_path, mime_type) VALUES (?, ?, ?, ?)");
                    $stmtAtt->execute([$db_message_id, $original_name, $relative_path, $attachment->getMimeType()]);
                    
                    echo "   -> Guardado: $original_name\n";
                }
            }

            $message->setFlag('Seen');
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "========================================\n";
echo "Proceso finalizado.\n";
?>