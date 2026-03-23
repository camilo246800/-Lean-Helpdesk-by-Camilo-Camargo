<?php
// reset.php
require_once 'config/database.php';

$email = 'camilo@masacademy.io';
$password = 'temporal123';

// Encriptamos la contraseña nativamente en tu servidor
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Vaciamos la tabla de usuarios por si había basura
    $pdo->query("DELETE FROM users");

    // Insertamos tu usuario exacto
    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $hash]);

    echo "<h1>¡Usuario configurado con éxito!</h1>";
    echo "<p>Email: <b>$email</b></p>";
    echo "<p>Contraseña: <b>$password</b></p>";
    echo "<p>Ya puedes ir al login e intentar de nuevo. <b>(Recuerda borrar este archivo reset.php después)</b></p>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>