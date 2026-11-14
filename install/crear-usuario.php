<?php
// Crea un usuario o cambia su contraseña. Solo funciona desde la terminal del servidor,
// nunca desde el navegador, para que nadie pueda usarlo desde internet.
//
// Uso:  php install/crear-usuario.php tu@empresa.com "UnaContraseñaSegura"

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$email = $argv[1] ?? '';
$password = $argv[2] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Uso: php install/crear-usuario.php tu@empresa.com \"contraseña de al menos 10 caracteres\"\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
$stmt->execute([$email, $hash]);

echo "Listo. Ya puedes entrar con $email\n";
