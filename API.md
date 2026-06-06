# API Reference — Sistema Multi-Emprendimiento

> ⬅️ [Volver al README](README.md) — Documentación general e inicio rápido

## Índice

1. [Chatbot IA](#1-chatbot-ia-api)
   - [Enviar mensaje](#11-enviar-mensaje-al-chatbot)
   - [Arquitectura](#12-arquitectura)
   - [System Prompt contextual](#13-system-prompt-con-texto-del-negocio)
   - [Fallback WhatsApp](#14-fallback-whatsapp)
   - [Widget frontend](#15-widget-frontend)
2. [Subida de archivos](#2-subida-de-archivos-api)
   - [Subir imagen](#21-subir-imagen)
   - [Validaciones](#22-validaciones)
   - [Almacenamiento](#23-almacenamiento)

---

## 1. Chatbot IA API

> **Endpoint:** `POST /chatbot-api.php`
> **Content-Type:** `application/json`
> **Autenticación:** Ninguna (pública)
> **CORS:** Soporta `OPTIONS` preflight. Headers: `Access-Control-Allow-Origin: *`, `Allow-Methods: POST, OPTIONS`, `Allow-Headers: Content-Type`

> **Ruta según instalación:**
> - Instalación estándar: `http://localhost/misitios/chatbot-api.php`
> - Si se copió directo a htdocs: `http://localhost/chatbot-api.php`

### 1.1 Enviar mensaje al chatbot

Envía un mensaje del usuario al asistente virtual. El servidor consulta los datos del emprendimiento en la base de datos, construye un system prompt contextual, y llama a **Ollama (DeepSeek 7B)** para generar la respuesta.

#### Request

```json
POST /misitios/chatbot-api.php
Content-Type: application/json

{
    "message": "¿Cuánto cuesta el café de especialidad?",
    "history": [
        { "role": "user", "content": "Hola, ¿qué productos tienen?" },
        { "role": "assistant", "content": "¡Hola! Tenemos café de especialidad, tortas artesanales y mates personalizados." }
    ],
    "slug": "cafe-don-juan"
}
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `message` | `string` | ✅ | Mensaje del usuario (mín. 1 carácter) |
| `history` | `array` | ❌ | Historial de la conversación. El backend toma **últimos 6** mensajes del array enviado |
| `slug` | `string` | ✅ | Slug del emprendimiento (identificador único) |

Cada elemento de `history` debe tener:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `role` | `string` | `"user"` o `"assistant"` |
| `content` | `string` | Contenido del mensaje |

**Nota sobre el historial:** El frontend oficial (`chatbot.js`) envía hasta 10 mensajes (`history.slice(-10)`), pero el backend solo procesa los últimos 6. Esto es intencional para mantener el contexto dentro del límite de tokens del modelo.

#### Response exitosa

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
    "success": true,
    "response": "¡Nuestro café de especialidad cuesta $3500! Actualmente tenemos 20 unidades en stock. 😊"
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `success` | `boolean` | `true` si la operación fue exitosa |
| `response` | `string` | Respuesta en lenguaje natural del asistente |
| `fallback` | `boolean` | `true` si se usó el fallback por error de Ollama |

#### Response con fallback (Ollama caído o timeout)

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
    "success": true,
    "response": "Lo siento, tuve un problema para procesar tu consulta. 😅\n\nPor favor, escríbeme por WhatsApp https://wa.me/54123456789?text=Hola%2C%20necesito%20ayuda",
    "fallback": true
}
```

#### Response de error — parámetros inválidos

```json
HTTP/1.1 400 Bad Request
Content-Type: application/json

{
    "success": false,
    "error": "Mensaje y slug requeridos"
}
```

#### Response de error — emprendimiento no encontrado

> El endpoint devuelve HTTP 200 pero con `success: false`.

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
    "success": false,
    "error": "Emprendimiento no encontrado"
}
```

#### Response de error — base de datos

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
    "success": false,
    "error": "Error de conexión a la base de datos"
}
```

| Código HTTP | Condición |
|-------------|-----------|
| `200` | Éxito (incluso con fallback de WhatsApp o slug inválido) |
| `400` | Falta `message` o `slug` |
| `405` | Método HTTP no permitido (solo POST) |

#### Ejemplo con curl

```bash
curl -X POST http://localhost/misitios/chatbot-api.php \
  -H "Content-Type: application/json" \
  -d '{
    "message": "¿Aceptan Mercado Pago?",
    "history": [],
    "slug": "cafe-don-juan"
  }'
```

#### Ejemplo con JavaScript (fetch)

```javascript
async function consultarChatbot(mensaje) {
    const response = await fetch('/misitios/chatbot-api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: mensaje,
            history: [],  // Opcional: hasta 10 mensajes (backend toma últimos 6)
            slug: obtenerSlugDesdeURL()
        })
    });

    const data = await response.json();

    if (data.success) {
        console.log('Respuesta:', data.response);
        if (data.fallback) {
            console.log('Usando fallback WhatsApp');
        }
    } else {
        console.error('Error:', data.error);
    }
}
```

### 1.2 Arquitectura

```
  Cliente JS           chatbot-api.php          Base de Datos          Ollama API
 (chatbot.js)              (PHP)           sistema_emprend.       localhost:11434
      |                      |                     |                     |
      |-- POST /chatbot ---->|                     |                     |
      |  {message,history,  |                     |                     |
      |   slug}             |                     |                     |
      |                      |-- SELECT slug ---->|                     |
      |                      |<-- datos negocio ---|                     |
      |                      |                     |                     |
      |                      |-- SELECT productos->|                     |
      |                      |<-- productos --------|                     |
      |                      |                     |                     |
      |                      |-- POST /api/chat -->|                     |
      |                      |  {model, system,   |--> deepseek-r1:1.5b |
      |                      |   messages}         |                     |
      |                      |<-- respuesta -------|---------------------|
      |                      |                     |                     |
      |<-- JSON response ----|                     |                     |
```

### 1.3 System Prompt con texto del negocio

El asistente recibe un system prompt generado dinámicamente que incluye:

- **Nombre y eslogan** del emprendimiento
- **WhatsApp**, email, teléfono, dirección y horarios
- **Texto "Quiénes somos"** del negocio
- **Políticas de envío y devolución**
- **Productos disponibles**: nombre, precio y stock
- **Formas de pago**: tipo, descripción y datos extra
- **Instrucciones de comportamiento** (tono amable, no inventar datos)

### 1.4 Fallback WhatsApp

Cuando Ollama no responde (timeout de 10 minutos configurable via `CHATBOT_TIMEOUT`), el sistema:

1. Registra el error en `error_log`
2. Genera un mensaje de disculpa automático
3. Incluye un enlace a WhatsApp con mensaje predefinido
4. Marca la respuesta con `"fallback": true`

### 1.5 Widget frontend

El widget del chatbot se incluye via **`plantilla-base/chatbot.php`** y se comunica con:

- **`POST /chatbot-api.php`** — endpoint PHP
- **`chatbot.js`** — lógica del cliente (historial local, typing indicator, etc.)

**Auto-apertura:** El botón del chat pulsa después de 30 segundos si el usuario no ha interactuado.

**Hash URL:** Si la URL contiene `#chat`, el chat se abre automáticamente al cargar.

---

## 2. Subida de archivos API

> **Endpoint:** `POST /panel-admin/upload.php`
> **Content-Type:** `multipart/form-data`
> **Autenticación:** Requiere sesión de administrador (`$_SESSION['admin_id']`)
>
> Para obtener la sesión, primero inicia sesión en `/misitios/panel-admin/` con las credenciales del admin. Luego las cookies de sesión se envían automáticamente en las requests (tanto desde el navegador como desde curl con `-b cookies.txt`).

### 2.1 Subir imagen

Sube una imagen (logo, producto, carrusel o general) para un emprendimiento específico.

#### Request

```
POST /misitios/panel-admin/upload.php
Content-Type: multipart/form-data

file:     [archivo de imagen]
slug:     cafe-don-juan
type:     producto        (opcional: logo | producto | carrusel | general)
```

| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `file` | `file` | ✅ | Archivo de imagen a subir |
| `slug` | `string` | ✅ | Slug del emprendimiento |
| `type` | `string` | ❌ | Tipo de imagen (uso informativo): `logo`, `producto`, `carrusel`, `general` |

#### Response exitosa

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
    "success": true,
    "filename": "a1b2c3d4-e5f6-4789-abcd-ef0123456789.jpg",
    "url": "/cafe-don-juan/uploads/a1b2c3d4-e5f6-4789-abcd-ef0123456789.jpg",
    "path": "/opt/lampp/htdocs/misitios/cafe-don-juan/uploads/a1b2c3d4-e5f6-4789-abcd-ef0123456789.jpg"
}
```

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `success` | `boolean` | `true` si la imagen se subió correctamente |
| `filename` | `string` | Nombre del archivo generado (UUID + extensión) |
| `url` | `string` | URL pública para acceder a la imagen |
| `path` | `string` | Ruta absoluta en el servidor |

#### Response de error

```json
HTTP/1.1 400 Bad Request
Content-Type: application/json

{
    "error": "Tipo de archivo no permitido. Extensiones: jpg, jpeg, png, gif, webp, svg, ico"
}
```

| Código HTTP | Condición |
|-------------|-----------|
| `200` | Éxito |
| `400` | Archivo inválido, extensión no permitida, tamaño excedido, falta slug |
| `401` | No autenticado (sin sesión admin activa) — `{ "error": "No autorizado" }` |
| `500` | Error al guardar el archivo en disco |

#### Ejemplo con curl (requiere sesión admin primero)

```bash
# 1. Iniciar sesión y guardar cookies
curl -c cookies.txt -X POST http://localhost/misitios/panel-admin/index.php \
  -d "email=admin@admin.com&password=admin123"

# 2. Subir imagen usando la cookie de sesión
curl -X POST http://localhost/misitios/panel-admin/upload.php \
  -F "file=@/ruta/imagen-producto.jpg" \
  -F "slug=cafe-don-juan" \
  -F "type=producto" \
  -b cookies.txt
```

#### Ejemplo con JavaScript (FormData + fetch)

```javascript
async function subirImagen(archivoInput, slug, tipo = 'general') {
    const formData = new FormData();
    formData.append('file', archivoInput.files[0]);
    formData.append('slug', slug);
    formData.append('type', tipo);

    const response = await fetch('/misitios/panel-admin/upload.php', {
        method: 'POST',
        body: formData
        // Las cookies de sesión se envían automáticamente
    });

    const data = await response.json();

    if (data.success) {
        console.log('Imagen subida:', data.url);
        document.getElementById('preview').src = data.url;
    } else {
        console.error('Error:', data.error);
    }
}
```

#### Ejemplo con HTML nativo

```html
<form action="/misitios/panel-admin/upload.php" method="post" enctype="multipart/form-data">
    <input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" required>
    <input type="text" name="slug" placeholder="Slug del emprendimiento" required>
    <input type="hidden" name="type" value="producto">
    <button type="submit">Subir imagen</button>
</form>
```

### 2.2 Validaciones

La API aplica las siguientes validaciones en orden:

| # | Validación | Regla | Código HTTP |
|---|-----------|-------|-------------|
| 1 | **Autenticación** | Debe existir `$_SESSION['admin_id']` | `401` |
| 2 | **Método HTTP** | Solo `POST` con `$_FILES['file']` | `400` |
| 3 | **Slug** | No debe estar vacío | `400` |
| 4 | **Extensión** | Debe estar en: `jpg, jpeg, png, gif, webp, svg, ico` | `400` |
| 5 | **Tamaño** | Máximo **5 MB** (`MAX_FILE_SIZE`) | `400` |
| 6 | **MIME type** | Debe ser imagen: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/svg+xml`, `image/x-icon` | `400` |
| 7 | **Escritura** | El directorio de uploads debe ser escribible | `500` |

### 2.3 Almacenamiento

Las imágenes se almacenan en:

```
{base_path}/{slug}/uploads/{uuid}.{extension}
```

Ejemplo:
```
/opt/lampp/htdocs/misitios/cafe-don-juan/uploads/a1b2c3d4-e5f6-4789-abcd-ef0123456789.jpg
```

- El nombre del archivo es un **UUID v4** generado con `generarUUID()`
- El directorio de uploads se crea automáticamente si no existe (`0755`)
- Las extensiones permitidas y el tamaño máximo se configuran en `config.php`

---

## Apéndice: Configuración relevante (config.php)

```php
// === Base de datos ===
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_emprendimientos');
define('DB_USER', 'root');
define('DB_PASS', '');

// === Ollama / Chatbot ===
define('OLLAMA_URL', 'http://localhost:11434/api/chat');
define('OLLAMA_MODEL', 'deepseek-r1:1.5b');
define('CHATBOT_TIMEOUT', 600); // segundos (10 min para CPU lento)

// === Uploads ===
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico']);
```

---

## Apéndice: Esquema de base de datos (tablas principales)

| Tabla | Descripción |
|-------|-------------|
| `emprendimientos` | Sitios web (`id`, `slug`, `nombre`, `activo`) |
| `config_visual` | Colores, logo, SEO por emprendimiento |
| `contacto_redes` | WhatsApp, email, redes sociales |
| `contenido_texto` | Textos: quiénes somos, políticas |
| `productos` | Catálogo: nombre, precio, stock |
| `formas_pago` | Métodos: transferencia, MP, efectivo, tarjeta |
| `conversaciones_chatbot` | Historial de conversaciones del chat |
| `clientes` | Clientes registrados |
| `ventas` / `detalle_venta` | Pedidos y detalle |
| `tickets_soporte` / `respuestas_tickets` | Sistema de soporte |
| `usuarios_admin` | Administradores del panel |

Ver `sistema_emprendimientos.sql` para el esquema completo con todas las columnas y constraints.

---

## Resumen de endpoints

| Endpoint | Método | Auth | Content-Type | Propósito |
|----------|--------|------|-------------|-----------|
| `/misitios/chatbot-api.php` | `POST` | ❌ (pública) | `application/json` | Chatbot IA con DeepSeek |
| `/misitios/panel-admin/upload.php` | `POST` | ✅ Sesión admin | `multipart/form-data` | Subir imágenes |
