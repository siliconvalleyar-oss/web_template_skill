# 🌐 Sistema Multi-Emprendimiento con DeepSeek 7B

[![Docker Build & Test](https://github.com/siliconvalleyar-oss/web_template_skill/actions/workflows/docker-build.yml/badge.svg)](https://github.com/siliconvalleyar-oss/web_template_skill/actions/workflows/docker-build.yml)

Generador y administrador de sitios web para múltiples emprendimientos con **tienda online**, **chatbot IA offline** (DeepSeek 7B via Ollama), y **panel de administración** centralizado.

---

## 📚 Documentación

| Guía | Descripción |
|------|-------------|
| [🚀 DEPLOY.md](DEPLOY.md) | Despliegue con Docker, XAMPP, CI/CD y solución de problemas |
| [📡 API.md](API.md) | Referencia de endpoints: chatbot IA y subida de archivos |
| [🧪 Tests](script_tools/test.sh) | Tests unitarios con PHPUnit (ejecutar: `./script_tools/test.sh`) |
| [⚙️ Skill](.opencode/skills/plantillas-web-skill.md) | Archivo de skill para Codebuff |

---

## ⚡ Inicio rápido

### Docker (recomendado)

```bash
git clone https://github.com/siliconvalleyar-oss/web_template_skill.git
cd web_template_skill
docker compose up -d
```

Acceder a **http://localhost:8080/panel-admin/** — email: `admin@admin.com`, pass: `admin123`

> 📖 Guía completa con variables de entorno, phpMyAdmin y descarga del modelo DeepSeek en [DEPLOY.md](DEPLOY.md)

### XAMPP

```bash
cp -r htdocs /opt/lampp/htdocs/misitios   # Linux
# Abrir http://localhost/misitios/instalar.php en el navegador
```

> 📖 Instalación manual y script automático en [DEPLOY.md](DEPLOY.md)

---

## 📁 Estructura del proyecto

```
htdocs/
├── instalar.php                # Instalador web
├── config.php                  # Configuración (env vars compatibles)
├── chatbot-api.php             # API del chatbot (DeepSeek + Ollama)
├── sistema_emprendimientos.sql # Esquema MySQL
├── .htaccess                   # Rewrite rules Apache
├── panel-admin/                # Panel de administración (login, CRUD)
└── plantilla-base/             # Template de sitio web (carrito, checkout, chatbot)
```

---

## 🗄️ Base de datos

**Nombre:** `sistema_emprendimientos` (MySQL, utf8mb4)

| Tabla | Descripción |
|-------|-------------|
| `emprendimientos` | Sitios web |
| `config_visual` | Colores, logo, SEO |
| `contacto_redes` | WhatsApp, redes sociales |
| `contenido_texto` | Quiénes somos, políticas |
| `productos` | Catálogo con precio y stock |
| `formas_pago` | Transferencia, Mercado Pago, efectivo, tarjeta |
| `clientes`, `ventas` | Clientes y pedidos |
| `tickets_soporte` | Sistema de tickets |
| `conversaciones_chatbot` | Historial del chat |
| `usuarios_admin` | Administradores |

> Esquema completo en [`sistema_emprendimientos.sql`](htdocs/sistema_emprendimientos.sql)

---

## 🛣️ Roadmap

- [x] Docker Compose (PHP 8 + MySQL 8 + Ollama)
- [x] Tests PHPUnit (24 tests unitarios)
- [x] CI/CD con GitHub Actions (build + smoke test)
- [x] API docs con ejemplos
- [ ] Exportar/Importar emprendimiento a JSON
- [ ] Pasarela de pago Mercado Pago real
- [ ] Multi-idioma
- [ ] Backup automático de BD

---

## 📄 Licencia

Uso personal y comercial libre.

---

**Creado con ❤️ usando PHP 8, MySQL, Bootstrap, JavaScript vanilla y DeepSeek 7B**
