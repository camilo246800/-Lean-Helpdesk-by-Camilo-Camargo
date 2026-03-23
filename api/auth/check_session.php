<?php
// api/auth/check_session.php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    echo json_encode(['authenticated' => true, 'user_id' => $_SESSION['user_id']]);
} else {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
}
?>