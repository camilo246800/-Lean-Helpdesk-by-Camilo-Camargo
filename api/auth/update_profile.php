<?php
// api/auth/update_profile.php
session_start();

// Verificamos que esté logueado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));
$new_email = isset($data->email) ? trim($data->email) : '';
$new_password = isset($data->password) ? trim($data->password) : '';

if (empty($new_email)) {
    echo json_encode(['success' => false, 'message' => 'El correo es obligatorio']);
    exit;
}

try {
    // Tomamos el ID del usuario de la sesión, o por defecto el ID 1 (el admin principal)
    $user_id = $_SESSION['user_id'] ?? 1;

    if (!empty($new_password)) {
        // Si mandó contraseña, actualizamos ambos
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // ¡CORRECCIÓN AQUÍ! Usamos password_hash en lugar de password
        $stmt = $pdo->prepare("UPDATE users SET email = ?, password_hash = ? WHERE id = ?");
        $stmt->execute([$new_email, $hashed_password, $user_id]);
    } else {
        // Si dejó la contraseña en blanco, solo actualizamos el correo
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$new_email, $user_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Tus datos de acceso han sido actualizados.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
}
?>