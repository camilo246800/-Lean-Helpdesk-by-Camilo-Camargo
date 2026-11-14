<?php
// api/auth/login.php
session_start();
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->email) || empty($data->password)) {
    echo json_encode(['success' => false, 'message' => 'Completa todos los campos']);
    exit;
}

try {
    // Buscamos al usuario por su nuevo correo
    // IMPORTANTE: Asegúrate que la columna se llame password_hash en tu BD
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch();

    // Verificamos si el usuario existe y si la contraseña coincide con el hash
    if ($user && password_verify($data->password, $user['password_hash'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
}
?>