# Lean Helpdesk

Un helpdesk ligero y auto-alojado que junta varias bandejas de correo en un solo panel, convierte cada conversación en un ticket y te deja responder como si fuera un chat.

Lo creé para el equipo de soporte de una empresa de educación que atiende varias marcas desde distintos correos. Corre en cualquier hosting con PHP y MySQL, sin pagar licencias por agente.

Guía completa en español: https://camilocamargo.com/helpdesk-gratis-open-source/

## Qué hace

- **Varias bandejas en un solo lugar.** Conectas cada correo por IMAP (para leer) y SMTP (para responder).
- **Tickets automáticos.** Un cron revisa los correos nuevos cada 5 minutos y crea un ticket por conversación. Si el cliente responde el mismo hilo, se agrega al ticket existente.
- **Vista tipo chat.** Cada ticket se lee como una conversación, con los adjuntos del cliente y los tuyos.
- **Responder y escribir correos nuevos** desde el panel, con adjuntos.
- **Buscador global** por correo del cliente o asunto, en todas las bandejas a la vez.
- **Abiertos y cerrados** con contadores por bandeja.
- **Reportes de tiempo de respuesta**: promedio de la semana o el mes, y por día de la semana.
- **API para bots** (opcional): `api/bot/pendientes.php` entrega los tickets pendientes a una automatización, por ejemplo un flujo de n8n que te avisa por Discord o WhatsApp.

## Requisitos

- PHP 8.1 o superior con las extensiones `imap`, `mbstring`, `openssl` y `pdo_mysql`
- MySQL 5.7+ o MariaDB 10.3+
- Composer
- Acceso para crear un cron en tu hosting (cPanel, CyberPanel, Plesk o un VPS)

## Instalación

1. **Sube los archivos** a un subdominio, por ejemplo `soporte.tudominio.com`.

2. **Instala las librerías:**
   ```bash
   composer install --no-dev
   ```

3. **Crea la base de datos** en tu hosting y ejecuta `install/schema.sql` en phpMyAdmin (pestaña SQL).

4. **Configura la conexión:** copia `config/database.example.php` como `config/database.php` y pon tus datos.

5. **Crea tu usuario** desde la terminal del servidor:
   ```bash
   php install/crear-usuario.php tu@empresa.com "UnaContraseñaSegura"
   ```

6. **Programa el cron** cada 5 minutos:
   ```
   */5 * * * * php /ruta/a/tu/helpdesk/cron/fetch_emails.php
   ```

7. **Entra al panel**, ve a Ajustes y agrega tus bandejas de correo.

### Datos de correo habituales

| Proveedor | IMAP | SMTP |
|---|---|---|
| Gmail / Google Workspace | imap.gmail.com, puerto 993 | smtp.gmail.com, puerto 465 |
| Outlook / Microsoft 365 | outlook.office365.com, puerto 993 | smtp.office365.com, puerto 587 |
| Correo en tu mismo servidor | localhost, puerto 993 | el de tu hosting, 465 o 587 |

Con Gmail necesitas una **contraseña de aplicación**, no la contraseña normal de la cuenta.

## Seguridad

- `config/database.php` y `config/bot.php` están en `.gitignore`: **nunca los subas a GitHub.**
- Las carpetas `config/`, `cron/` e `install/` no se pueden abrir desde el navegador.
- La carpeta `uploads/` no ejecuta código, aunque alguien envíe un archivo malicioso por correo.
- Las contraseñas de los usuarios del panel se guardan cifradas con `password_hash`.
- Los adjuntos que llegan por correo pueden traer virus (phishing). No abras archivos `.rar`, `.zip` o `.exe` de remitentes desconocidos.

## Estructura

```
api/          Endpoints del panel (auth, mailboxes, tickets, reports, bot)
assets/       Logos
config/       Conexión a la base de datos y clave del bot (ejemplos)
cron/         Lector automático de correos
install/      Esquema SQL y creación de usuarios
uploads/      Adjuntos descargados
index.html    Panel principal
login.html    Acceso
reportes.html Reportes de tiempos de respuesta
```

## Autor

Creado por **Camilo Camargo**, ingeniero de software.
Sitio: https://camilocamargo.com · Instagram: [@ingcamilocamargo](https://www.instagram.com/ingcamilocamargo/)

Licencia MIT: puedes usarlo, modificarlo y adaptarlo a tu empresa.
