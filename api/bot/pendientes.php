<?php
// api/bot/pendientes.php
// Endpoint de SOLO LECTURA para Alfred (n8n + Discord).
// Devuelve los tickets abiertos (status = 'OPEN') con un resumen de sus últimos mensajes.
// Se protege con una clave en el encabezado X-API-Key (ver config/bot.php).

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/bot.php';

// 1. Seguridad: solo GET y con la clave correcta
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$claveRecibida = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!defined('BOT_API_KEY') || strlen(BOT_API_KEY) < 32 || !hash_equals(BOT_API_KEY, $claveRecibida)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

// 2. Parámetros
$limite      = min(max(intval($_GET['limit'] ?? 15), 1), 30); // máximo de tickets a devolver
$maxMensajes = 4;     // últimos mensajes por ticket
$maxCaracteres = 1500; // largo máximo de cada mensaje

// Limpia el cuerpo de un correo: quita HTML, firmas citadas e historial de respuestas
function limpiarTexto($texto, $max)
{
    $t = (string) $texto;
    $t = preg_replace('/<(br|\/p|\/div)[^>]*>/i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\r", '', $t);

    // Corta donde empieza el historial citado ("El ... escribió:", "On ... wrote:", líneas con ">")
    $cortes = [
        '/\n[^\n]{0,20}El [^\n]{5,160}escribi[oó]:/u',
        '/\n[^\n]{0,20}On [^\n]{5,160}wrote:/u',
        '/\n-{2,}\s*(Original Message|Mensaje original)/iu',
        '/\nDe: [^\n]+\n(Enviado|Sent|Fecha|Date)/iu',
        '/\n>/',
    ];
    foreach ($cortes as $patron) {
        if (preg_match($patron, $t, $m, PREG_OFFSET_CAPTURE)) {
            $t = substr($t, 0, $m[0][1]);
        }
    }

    $t = preg_replace('/[ \t]+/', ' ', $t);
    $t = preg_replace("/\n{3,}/", "\n\n", $t);
    $t = trim($t);

    if (mb_strlen($t, 'UTF-8') > $max) {
        $t = mb_substr($t, 0, $max, 'UTF-8') . '…';
    }
    return $t;
}

try {
    // 3. Resumen por bandeja (igual que el contador "Pendientes" del panel)
    $resumen = $pdo->query("
        SELECT m.id, m.name AS bandeja, m.email,
               (SELECT COUNT(id) FROM tickets WHERE mailbox_id = m.id AND status = 'OPEN') AS pendientes
        FROM mailboxes m
        ORDER BY m.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Tickets abiertos, del más antiguo al más reciente
    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.subject AS asunto,
            t.customer_email AS cliente_email,
            t.customer_name AS cliente_nombre,
            t.updated_at AS ultima_actividad,
            m.name AS bandeja,
            m.email AS bandeja_email,
            TIMESTAMPDIFF(HOUR, t.updated_at, NOW()) AS horas_sin_movimiento,
            (SELECT MIN(created_at) FROM messages WHERE ticket_id = t.id) AS recibido,
            (SELECT COUNT(id) FROM messages WHERE ticket_id = t.id) AS total_mensajes,
            (SELECT is_from_customer FROM messages WHERE ticket_id = t.id ORDER BY created_at DESC, id DESC LIMIT 1) AS ultimo_es_del_cliente
        FROM tickets t
        JOIN mailboxes m ON m.id = t.mailbox_id
        WHERE t.status = 'OPEN'
        ORDER BY t.updated_at ASC
        LIMIT :limite
    ");
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Últimos mensajes de cada ticket (con nombres de adjuntos, sin descargar nada)
    $stmtMsgs = $pdo->prepare("
        SELECT id, body_text, body_html, is_from_customer, created_at
        FROM messages
        WHERE ticket_id = :ticket
        ORDER BY created_at DESC, id DESC
        LIMIT :max
    ");
    $stmtAdj = $pdo->prepare("SELECT file_name FROM attachments WHERE message_id = ?");

    foreach ($tickets as &$t) {
        $stmtMsgs->bindValue(':ticket', (int) $t['id'], PDO::PARAM_INT);
        $stmtMsgs->bindValue(':max', $maxMensajes, PDO::PARAM_INT);
        $stmtMsgs->execute();
        $filas = array_reverse($stmtMsgs->fetchAll(PDO::FETCH_ASSOC));

        $mensajes = [];
        foreach ($filas as $f) {
            $stmtAdj->execute([$f['id']]);
            $adjuntos = array_column($stmtAdj->fetchAll(PDO::FETCH_ASSOC), 'file_name');
            $cuerpo = trim((string) $f['body_text']) !== '' ? $f['body_text'] : $f['body_html'];

            $mensajes[] = [
                'de'       => ((int) $f['is_from_customer'] === 1) ? 'cliente' : 'equipo',
                'fecha'    => $f['created_at'],
                'texto'    => limpiarTexto($cuerpo, $maxCaracteres),
                'adjuntos' => $adjuntos,
            ];
        }

        $t['id'] = (int) $t['id'];
        $t['horas_sin_movimiento'] = (int) $t['horas_sin_movimiento'];
        $t['total_mensajes'] = (int) $t['total_mensajes'];
        $t['esperando_respuesta_nuestra'] = ((int) $t['ultimo_es_del_cliente'] === 1);
        unset($t['ultimo_es_del_cliente']);
        $t['mensajes'] = $mensajes;
    }
    unset($t);

    echo json_encode([
        'success'        => true,
        'generado'       => date('Y-m-d H:i:s'),
        'zona_servidor'  => date_default_timezone_get(),
        'total_abiertos' => array_sum(array_map('intval', array_column($resumen, 'pendientes'))),
        'bandejas'       => $resumen,
        'tickets'        => $tickets,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
}
