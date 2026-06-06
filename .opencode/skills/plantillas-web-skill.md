# Sistema Multi-Emprendimiento con DeepSeek 7B

## Descripción

Generador y administrador de sitios web para múltiples emprendimientos con tienda online, chatbot offline con IA (DeepSeek 7B via Ollama), y panel de administración centralizado.

## Stack técnico

- **Backend**: PHP 8+, MySQL 5.7+, Apache (XAMPP)
- **Frontend**: Bootstrap 5, JavaScript vanilla, CSS3
- **IA**: Ollama + DeepSeek 7B (chatbot offline)
- **SEO**: URLs amigables, meta tags, Open Graph

## Estructura del proyecto

```
htdocs/
├── instalar.php              # Instalador web (crea BD, tablas, admin)
├── config.php                # Configuración global (PDO, constantes)
├── chatbot-api.php           # API chatbot (consulta Ollama)
├── sistema_emprendimientos.sql  # Esquema de BD completo
├── .htaccess                 # Rewrite rules de Apache
├── panel-admin/              # Panel de administración
│   ├── index.php             # Login
│   ├── dashboard.php         # Dashboard con estadísticas
│   ├── emprendimientos.php   # CRUD de emprendimientos
│   ├── emprendimiento-editar.php  # Editor multi-pestaña
│   ├── pedidos.php           # Gestión de pedidos
│   ├── soporte-panel.php     # Tickets de soporte
│   ├── themes.php            # Temas predefinidos
│   ├── upload.php            # API de subida de imágenes
│   └── assets/admin.css
└── plantilla-base/           # Template de sitio web
    ├── index.php             # Home con carrusel hero
    ├── productos.php         # Catálogo con búsqueda
    ├── producto-detalle.php  # Detalle con productos relacionados
    ├── carrito.php           # Carrito (localStorage)
    ├── checkout.php          # Checkout con formas de pago
    ├── contacto.php          # Contacto + chatbot flotante
    ├── soporte.php           # Sistema de tickets
    ├── politicas.php         # Políticas de envío/devolución
    ├── chatbot.php           # Widget chatbot (Ollama)
    └── assets/
        ├── css/plantilla.css
        └── js/
            ├── carrito.js
            └── chatbot.js
```

## Base de datos

**BD:** `sistema_emprendimientos` (MySQL, utf8mb4)

Tablas: `emprendimientos`, `config_visual`, `contacto_redes`, `contenido_texto`, `imagenes_carrusel`, `productos`, `formas_pago`, `clientes`, `ventas`, `detalle_ventas`, `tickets_soporte`, `mensajes_ticket`, `conversaciones_chatbot`, `usuarios_admin`

## Instalación (recomendado)

```bash
# 1. Clonar el repositorio
git clone https://github.com/siliconvalleyar-oss/web_template_skill.git
cd web_template_skill

# 2. Ejecutar el instalador automático:
./script_tools/install.sh

# 3. Abrir en el navegador:
#    http://localhost/misitios/instalar.php

# 4. Login: admin@admin.com / admin123
```

### Instalación manual

```bash
# Copiar htdocs/ al directorio de XAMPP
cp -r htdocs /opt/lampp/htdocs/misitios   # Linux
# cp -r htdocs C:/xampp/htdocs/misitios    # Windows
# Luego visitar http://localhost/misitios/instalar.php
```

## Chatbot IA

Arquitectura: Cliente JS → PHP (chatbot-api.php) → Ollama API (DeepSeek 7B)

El chatbot usa context-aware prompting: incluye productos, precios, stock, políticas y datos del negocio en el system prompt.

## Backups

Los emprendimientos se pueden exportar a JSON desde el panel admin (incluye productos, imágenes, configuración visual, etc.).

## Configuración clave (config.php)

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — conexión MySQL
- `OLLAMA_URL` — endpoint de Ollama (default: localhost:11434)
- `OLLAMA_MODEL` — modelo (default: deepseek-r1:1.5b)
- `MAX_FILE_SIZE` — 5MB para uploads
