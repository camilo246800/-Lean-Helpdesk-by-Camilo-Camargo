<?php
// cron/fetch_emails.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Webklex\PHPIMAP\ClientManager;

echo "Iniciando proceso de lectura de correos...\n";

$config = HTMLPurifier_Config::createDefault();
$config->set('Cache.DefinitionImpl', null); 
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
            $subject = mb_convert_encoding($subject, "UTF-8", "auto");
            
            $from = $message->getFrom()[0];
            $customer_email = $from->mail;
            $customer_name = $from->personal ? mb_convert_encoding($from->personal, "UTF-8", "auto") : $customer_email;
            $customer_name = trim(str_replace('"', '', $customer_name));
            
            $message_id = $message->getMessageId()[0] ?? uniqid();
            
            // --- EXTRACCIÓN DE CUERPO CORREGIDA ---
            $body_html = "";
            if ($message->hasHTMLBody()) {
                $body_html = $message->getHTMLBody();
            } else {
                $body_html = nl2br(mb_convert_encoding($message->getTextBody(), "UTF-8", "auto"));
            }

            // En lugar de replaceInlineImages(), usamos mb_convert_encoding y limpieza directa
            $body_html = mb_convert_encoding($body_html, "UTF-8", "UTF-8");
            
            if ($body_html) {
                $body_html = $purifier->purify($body_html);
            }

            // Si el cuerpo queda vacío después de purificar (pasa con emojis solos), rescatamos el texto plano
            if (empty(trim(strip_tags($body_html))) || strlen(trim($body_html)) < 2) {
                $body_html = nl2br(htmlspecialchars(mb_convert_encoding($message->getTextBody(), "UTF-8", "auto")));
            }

            // --- Lógica de Hilos (Tickets) ---
            $stmtTicket = $pdo->prepare("SELECT id FROM tickets WHERE customer_email = ? AND subject = ? AND status != 'CLOSED' LIMIT 1");
            $stmtTicket->execute([$customer_email, $subject]);
            $existingTicket = $stmtTicket->fetch();

            if ($existingTicket) {
                $ticket_id = $existingTicket['id'];
                echo "-> Mensaje agregado al Ticket #$ticket_id\n";
            } else {
                $stmtNewTicket = $pdo->prepare("INSERT INTO tickets (mailbox_id, customer_email, customer_name, subject, status) VALUES (?, ?, ?, ?, 'OPEN')");
                $stmtNewTicket->execute([$mailbox['id'], $customer_email, $customer_name, $subject,]);
                $ticket_id = $pdo->lastInsertId();
                echo "-> Nuevo Ticket creado #$ticket_id ($customer_name)\n";
            }

            $body_text_plain = strip_tags($body_html);
            
            $stmtMsg = $pdo->prepare("INSERT INTO messages (ticket_id, message_id_hash, body_html, body_text, is_from_customer) VALUES (?, ?, ?, ?, 1)");
            $stmtMsg->execute([$ticket_id, md5($message_id), $body_html, $body_text_plain]);
            
            $db_message_id = $pdo->lastInsertId();

            // --- LÓGICA DE ADJUNTOS ---
            if ($message->hasAttachments()) {
                $attachments = $message->getAttachments();
                echo "   Contiene " . $attachments->count() . " adjunto(s).\n";
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