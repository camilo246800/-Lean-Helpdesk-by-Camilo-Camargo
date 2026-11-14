<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
require_once '../../config/database.php';

$range = $_GET['range'] ?? 'week';

// Lógica de filtrado de fechas
if ($range === 'current_week') {
    // Desde el lunes de la semana actual hasta hoy
    $interval_condition = "YEARWEEK(ticket_created, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($range === 'month') {
    $interval_condition = "ticket_created >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
} else {
    // Default: Últimos 7 días (móvil)
    $interval_condition = "ticket_created >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
}

try {
    // 1. Promedio del Periodo (Dinámico)
    $stmtAvg = $pdo->prepare("SELECT AVG(response_minutes) FROM v_response_times WHERE $interval_condition");
    $stmtAvg->execute();
    $avgMinutes = $stmtAvg->fetchColumn();
    $period_avg_hours = round(($avgMinutes ?? 0) / 60, 1);

    // 2. Promedio por Día de la Semana
    $stmtDays = $pdo->prepare("
        SELECT 
            DAYOFWEEK(ticket_created) as day_index,
            ROUND(AVG(response_minutes) / 60, 1) as avg_hours 
        FROM v_response_times 
        WHERE $interval_condition
        GROUP BY day_index 
        ORDER BY day_index ASC
    ");
    $stmtDays->execute();
    $daysData = $stmtDays->fetchAll(PDO::FETCH_ASSOC);

    $dayNames = [1 => 'Domingo', 2 => 'Lunes', 3 => 'Martes', 4 => 'Miércoles', 5 => 'Jueves', 6 => 'Viernes', 7 => 'Sábado'];
    $formattedDays = [];

    foreach ($daysData as $row) {
        $formattedDays[] = [
            'day' => $dayNames[$row['day_index']],
            'hours' => $row['avg_hours']
        ];
    }

    echo json_encode([
        'success' => true,
        'period_avg' => $period_avg_hours,
        'days' => $formattedDays
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}