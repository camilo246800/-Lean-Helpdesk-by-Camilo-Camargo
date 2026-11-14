<?php
// config/bot.php (opcional)
// Clave secreta para que un bot o una automatización (por ejemplo n8n) lea api/bot/pendientes.php
// enviando el encabezado X-API-Key. Genera una propia con: php -r "echo bin2hex(random_bytes(32));"
define('BOT_API_KEY', 'CAMBIA_ESTA_CLAVE_POR_UNA_DE_64_CARACTERES');
