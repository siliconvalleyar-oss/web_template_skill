# 🌐 Sistema Multi-Emprendimiento con DeepSeek 7B

[![Docker Build & Test](https://github.com/siliconvalleyar-oss/web_template_skill/actions/workflows/docker-build.yml/badge.svg)](https://github.com/siliconvalleyar-oss/web_template_skill/actions/workflows/docker-build.yml)
[![PHPUnit Tests](https://img.shields.io/badge/tests-24%20passing-brightgreen)](API.md)
[![Docker](https://img.shields.io/badge/deploy-Docker-2496ED?logo=docker)](DEPLOY.md)

Generador y administrador de **30 sitios web** para diferentes emprendimientos con tienda online, chatbot offline con inteligencia artificial (DeepSeek 7B via Ollama), y panel de administración centralizado.

## 📋 Características

- **30 sitios web** administrables desde un único panel
- **Tienda online** completa (carrito, checkout, stock, formas de pago)
- **Chatbot IA offline** con DeepSeek 7B (Ollama)
- **Soporte al cliente** con sistema de tickets
- **Panel admin** con login, dashboard, CRUD completo
- **Totalmente responsive** (móvil, tablet, desktop)
- **Personalización visual**: colores, logo, imágenes, textos
- **WhatsApp integrado** con botón flotante

## 🛠️ Requisitos

| Componente | Especificación |
|------------|---------------|
| XAMPP | Apache 2.4+, PHP 8+, MySQL 5.7+ |
| PHP Extensiones | PDO, pdo_mysql, curl, gd, mbstring, json, session |
| Ollama | Corriendo en http://localhost:11434 |
| Modelo IA | deepseek-r1:7b (`ollama pull deepseek-r1:7b`) |

## 🚀 Instalación

### 1. Copiar archivos

```bash
# Copiar la carpeta htdocs a tu directorio de XAMPP
cp -r htdocs /opt/lampp/htdocs/  # Linux
# o
cp -r htdocs C:/xampp/htdocs/     # Windows
```

### 2. Ejecutar instalador

Abre en tu navegador:

```
http://localhost/instalar.php
```

El instalador verificará:
- ✅ PHP 8+
- ✅ Extensiones requeridas
- ✅ MySQL (XAMPP)
- ✅ Ollama + DeepSeek 7B
- ✅ Permisos de escritura

Luego creará automáticamente:
- Base de datos `sistema_emprendimientos`
- Todas las tablas y relaciones
- Usuario admin por defecto

### 3. Acceder al panel

```
URL: http://localhost/panel-admin/
Email: admin@admin.com
Contraseña: admin123
```

## 📁 Estructura del proyecto

```
htdocs/
├── instalar.php              # Instalador del sistema
├── config.php                # Configuración general
├── sistema_emprendimientos.sql  # Esquema de BD
├── chatbot-api.php           # API del chatbot (DeepSeek 7B)
├── .htaccess                 # Reglas de reescritura Apache
│
├── panel-admin/              # Panel de administración
│   ├── index.php             #   Login
│   ├── dashboard.php         #   Dashboard
│   ├── emprendimientos.php   #   Listado de sitios
│   ├── emprendimiento-editar.php  # Crear/editar sitio
│   ├── pedidos.php           #   Gestión de pedidos
│   ├── soporte-panel.php     #   Tickets de soporte
│   ├── upload.php            #   Subida de archivos
│   └── assets/
│       └── admin.css         #   Estilos del panel
│
├── plantilla-base/           # Template maestro
│   ├── index.php             #   Home con carrusel
│   ├── productos.php         #   Catálogo de productos
│   ├── producto-detalle.php  #   Detalle de producto
│   ├── carrito.php           #   Carrito de compras
│   ├── checkout.php          #   Finalizar compra
│   ├── contacto.php          #   Contacto + chatbot
│   ├── politicas.php         #   Políticas
│   ├── soporte.php           #   Soporte al cliente
│   ├── chatbot.php           #   Widget chatbot
│   ├── uploads/              #   Imágenes subidas
│   └── assets/
│       ├── css/plantilla.css #   Estilos del frontend
│       └── js/
│           ├── carrito.js    #   Carrito (localStorage)
│           └── chatbot.js    #   Chatbot widget
│
└── [slug-emprendimiento]/    # Sitios generados (enlaces simbólicos)
    └── uploads/              # Imágenes del emprendimiento
```

## 🎯 Cómo usar

### Crear un nuevo emprendimiento

1. Ir a **Panel Admin** → **Nuevo Sitio**
2. Completar las pestañas:
   - 📋 **Datos básicos**: nombre, slug, eslogan
   - 🎨 **Identidad visual**: colores, logo, favicon
   - 📞 **Contacto**: WhatsApp, email, redes sociales
   - 📝 **Textos**: quiénes somos, bienvenida, políticas
   - 🖼️ **Carrusel**: hasta 5 imágenes con título
   - 📦 **Productos**: nombre, precio, stock, imagen
   - 💳 **Pagos**: transferencia, Mercado Pago, efectivo, tarjeta
3. Click en **Guardar** → el sitio se crea automáticamente

### Ver el sitio generado

```
http://localhost/[slug]/
```

Ejemplo: `http://localhost/cafe-don-juan/`

### Chatbot IA

Cada sitio incluye un botón flotante de chat que usa **DeepSeek 7B** para responder preguntas sobre productos, stock, precios, formas de pago y más.

## 🗄️ Base de datos

**Nombre:** `sistema_emprendimientos`

### Tablas principales:

| Tabla | Descripción |
|-------|-------------|
| `emprendimientos` | Datos generales de cada sitio |
| `config_visual` | Colores, logo, SEO |
| `contacto_redes` | WhatsApp, redes sociales |
| `contenido_texto` | Textos del sitio |
| `imagenes_carrusel` | Imágenes del carrusel |
| `productos` | Catálogo de productos |
| `formas_pago` | Métodos de pago |
| `clientes` | Clientes registrados |
| `ventas` | Pedidos realizados |
| `tickets_soporte` | Tickets de soporte |
| `conversaciones_chatbot` | Historial del chat |
| `usuarios_admin` | Administradores |

## 🤖 Chatbot DeepSeek 7B

### Arquitectura

```
Cliente (JS) → POST /chatbot-api.php → Consulta BD 
→ Construye System Prompt con contexto del negocio 
→ Llama a Ollama API (deepseek-r1:7b) 
→ Devuelve respuesta al cliente
```

### Lo que sabe el chatbot

- ✅ Productos: nombre, precio, stock
- ✅ Políticas de envío y devolución
- ✅ Formas de pago disponibles
- ✅ Horarios de atención
- ✅ Información de contacto
- ✅ Datos del negocio

### Fallback

Si DeepSeek no responde en 10 segundos, el chatbot sugiere contactar por WhatsApp automáticamente.

## 📱 Funcionalidades del Frontend

- **Carrusel hero** con imágenes configurables
- **Catálogo de productos** con búsqueda
- **Carrito de compras** con localStorage
- **Checkout** con selección de forma de pago
- **Botón flotante WhatsApp** con mensaje predefinido
- **Chatbot IA** widget flotante
- **Sistema de tickets** de soporte
- **Totalmente responsive**

## 🔒 Seguridad

- Prepared statements (PDO) contra SQL injection
- XSS protegido con `htmlspecialchars()`
- Validación de archivos subidos (tipo y tamaño)
- Sesiones para autenticación admin
- Logout automático

## 🛣️ Roadmap

- [ ] Exportar/Importar emprendimiento a JSON
- [ ] Duplicar emprendimiento
- [ ] Temas predefinidos adicionales
- [ ] Pasarela de pago Mercado Pago real
- [ ] Notificaciones WhatsApp automáticas
- [ ] Estadísticas y reportes
- [ ] Multi-idioma
- [ ] Backup automático de BD

## 📄 Licencia

Uso personal y comercial libre.

---

**Creado con ❤️ usando PHP, MySQL, Bootstrap, JavaScript vanilla y DeepSeek 7B**
