Lean Helpdesk by Camilo Camargo

Un sistema de gestión de tickets ligero, autónomo y auto-alojado, diseñado para centralizar múltiples bandejas de entrada de correo electrónico en una interfaz tipo chat moderna y minimalista.

🛠️ Tecnologías utilizadas

<code>Backend: PHP 8.x (Arquitectura basada en APIs REST).

Frontend: HTML5, JavaScript (Vanilla), Tailwind CSS (CDN).

Base de Datos: MySQL / MariaDB.

Gestión de Correo: Webklex/PHP-IMAP (Lectura) y PHPMailer (Envío).

Seguridad: Sesiones PHP, encriptación de contraseñas con password_hash.</code>


📂 Estructura del Proyecto

<code>/
├── api/
│   ├── auth/           # Login, Logout, Perfil y Sesión
│   ├── mailboxes/      # CRUD de bandejas de entrada
│   └── tickets/        # Listado, visualización, respuesta y cierre de tickets
├── assets/             # Logos e imágenes del sistema
├── config/
│   └── database.php    # Conexión PDO a la base de datos
├── cron/
│   └── fetch_emails.php # SCRIPT MAESTRO: Lector automático de correos
├── uploads/            # Almacenamiento físico de adjuntos
├── index.html          # Panel principal (Dashboard)
├── login.html          # Interfaz de acceso
├── perfil.html         # Interfaz de gestión de usuario
└── vendor/             # Librerías de Composer</code>



🗄️ Base de Datos (Esquema SQL)

El sistema utiliza 4 tablas principales:

users: Almacena las credenciales de acceso al panel.

mailboxes: Guarda la configuración IMAP/SMTP de cada cuenta de correo.

tickets: Cabeceras de las conversaciones (Asunto, Cliente, Estado).

messages: El cuerpo de los correos (HTML/Texto) vinculados a un ticket.

attachments: Registro de archivos descargados vinculados a mensajes.

⚙️ Configuración de Protocolos

📩 IMAP (Lectura)

El sistema utiliza el puerto 993 (SSL) por defecto.

Host: localhost si el correo está en el mismo servidor (CyberPanel), o el host del proveedor (ej: imap.gmail.com).

Funcionamiento: El script de Cron se conecta, descarga solo los correos UNSEEN (no leídos), crea el ticket, descarga adjuntos y marca el correo como "Leído" en el servidor original.

📤 SMTP (Envío)

Utiliza PHPMailer para responder desde la interfaz.

Puertos compatibles: 465 (SSL) o 587 (TLS).

Validación: Requiere que el dominio esté verificado en proveedores como MailerSend o SendGrid para evitar rebotes.

🤖 Automatización (Cron Job)

Para que el sistema sea autónomo, se debe configurar una tarea programada en el servidor (CyberPanel -> Cron Jobs):

Frecuencia recomendada: Cada 5 minutos (*/5 * * * *).

Comando:
<code>php /home/tu-dominio.com/public_html/cron/fetch_emails.php</code>

🔍 Funciones Especiales

Buscador Global: Filtra por correo de cliente o asunto en todas las bandejas simultáneamente.

Gestión de Hilos: Agrupa mensajes automáticamente si el cliente responde al mismo hilo (basado en Email + Asunto).

Detector de Nombres: Captura el nombre real del remitente configurado en su cliente de correo.

Sistema de Adjuntos: Descarga automática de archivos del cliente y capacidad de adjuntar archivos en las respuestas del agente.

🚀 Cómo empezar (Instalación rápida)

Clonar el repositorio.

Ejecutar composer install para instalar dependencias.

Configurar las credenciales de DB en config/database.php.

Crear el usuario inicial en la tabla users (usar password_hash).

Configurar el Cron Job en el servidor.

¡Listo para dar soporte!

Nota para futuros programadores y para IAs: Al retomar este proyecto, siempre verifica que la columna customer_name exista en la tabla tickets y que el 
archivo index.html mantenga la estructura de las 4 vistas (mailboxes, tickets, single-ticket, settings) para asegurar la compatibilidad con el backend actual.

📚 Documentación Técnica Maestra: Lean Helpdesk
Autor: Camilo Camargo
Versión: 1.0 (Final Estable)

1. 🗄️ Estructura de Base de Datos (SQL)

Copia y ejecuta este código en tu phpMyAdmin para crear la estructura completa y necesaria:


<code>-- 1. Tabla de Usuarios (Acceso al Panel)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</code>



<code>-- 2. Tabla de Bandejas (Configuración IMAP/SMTP)
CREATE TABLE mailboxes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    protocol ENUM('IMAP', 'POP3') DEFAULT 'IMAP',
    mail_host VARCHAR(255) NOT NULL,
    mail_port INT NOT NULL,
    mail_user VARCHAR(255) NOT NULL,
    mail_pass VARCHAR(255) NOT NULL,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL,
    smtp_user VARCHAR(255) NOT NULL,
    smtp_pass VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);</code>



<code>-- 3. Tabla de Tickets (Cabeceras de conversación)
CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mailbox_id INT NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    status ENUM('OPEN', 'CLOSED') DEFAULT 'OPEN',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE
);</code>


<code>-- 4. Tabla de Mensajes (El contenido del chat)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    message_id_hash VARCHAR(32),
    body_html LONGTEXT,
    body_text LONGTEXT,
    is_from_customer TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
);</code>

<code>
-- 5. Tabla de Adjuntos
CREATE TABLE attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
);</code>


2. 📁 Resumen de Archivos Clave (Backend)

config/database.php
Establece la conexión PDO. Es el corazón que une todo con la DB.

cron/fetch_emails.php (Sincronizador IMAP)
Función: Se conecta a cada cuenta en mailboxes.

Lógica: Busca correos no leídos, extrae el nombre (personal), el email y los adjuntos.

Hilos: Agrupa por Email + Asunto para no duplicar tickets.

api/tickets/reply.php (Envío SMTP)

Función: Envía respuestas usando PHPMailer.

Adjuntos: Permite subir archivos desde el panel, los guarda en /uploads y los envía al cliente.

3. 🖥️ Interfaz Maestra (index.html)

El panel es una Single Page Application (SPA) con 4 estados:

Mailboxes: Resumen de cuentas con contadores de tickets pendientes.

Tickets: Listado filtrado por pestañas (Abiertos / Cerrados).

Single Ticket: Interfaz de chat con visualización de adjuntos y editor de respuesta.

Settings: CRUD visual para añadir o editar cuentas de correo.

🚀 Guía de Mantenimiento Rápido

¿No llegan correos? Revisa que el Cron Job en CyberPanel apunte a la ruta absoluta de fetch_emails.php y que el Host IMAP sea localhost si el correo es interno.

¿Error al enviar? Verifica que el dominio del correo remitente esté verificado en tu proveedor SMTP (MailerSend/SendGrid).

¿Olvidaste la contraseña del panel? Usa el archivo fix_user.php que creamos para resetear el password_hash en la tabla users.

¡Éxito con Lean Helpdesk! 🚀🔥
