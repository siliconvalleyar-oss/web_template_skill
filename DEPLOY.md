# Guía de Despliegue — Sistema Multi-Emprendimiento

## Índice

1. [Despliegue con Docker](#1-despliegue-con-docker)
   - [Requisitos](#11-requisitos)
   - [Primeros pasos](#12-primeros-pasos)
   - [Comandos útiles](#13-comandos-útiles)
   - [Descargar modelo DeepSeek](#14-descargar-modelo-deepseek)
   - [phpMyAdmin](#15-phpmyadmin-opcional)
   - [Variables de entorno](#16-variables-de-entorno)
   - [Rendimiento y recursos](#17-rendimiento-y-recursos)
2. [Despliegue con XAMPP](#2-despliegue-con-xampp)
   - [Instalación manual](#21-instalación-manual)
   - [Instalación con script](#22-instalación-con-script)
3. [CI/CD con GitHub Actions](#3-cicd-con-github-actions)
   - [Jobs del workflow](#31-jobs-del-workflow)
   - [Ejecutar manualmente](#32-ejecutar-manualmente)
4. [Solución de problemas](#4-solución-de-problemas)
   - [Docker](#41-docker)
   - [Base de datos](#42-base-de-datos)
   - [Chatbot / Ollama](#43-chatbot--ollama)

---

## 1. Despliegue con Docker

### 1.1 Requisitos

| Componente | Versión |
|------------|---------|
| Docker Engine | 24+ |
| Docker Compose | v2.40+ (plugin) |
| RAM | 4 GB mínimo (8 GB recomendado) |
| Disco | 5 GB libres (modelo DeepSeek ~1 GB) |

Verificar instalación:

```bash
docker --version
docker compose version
```

> **Linux (Debian/Ubuntu):** Si no tenés Docker Compose, instalalo con:
> ```bash
> sudo apt install docker-compose-v2
> ```
> Luego agregá tu usuario al grupo `docker` y reiniciá sesión:
> ```bash
> sudo usermod -aG docker $USER
> # Cerrar sesión y volver a entrar, o ejecutar: newgrp docker
> ```

### 1.2 Primeros pasos

Clonar el repositorio e iniciar los servicios:

```bash
git clone https://github.com/siliconvalleyar-oss/web_template_skill.git
cd web_template_skill

# Construir e iniciar todos los servicios
docker compose up -d

# Ver el progreso
docker compose logs -f
```

Esto inicia tres servicios:

| Servicio | Puertos | Propósito |
|----------|---------|-----------|
| `web` | `localhost:80` | Apache + PHP 8.3 |
| `db` | `localhost:3306` | MySQL 8.0 |
| `ollama` | `localhost:11434` | DeepSeek chatbot |

El entrypoint espera a que MySQL esté listo, ejecuta la migración inicial (crea la BD, tablas y usuario admin), y luego arranca Apache.

Acceder al panel:

```
URL:              http://localhost/
Panel admin:      http://localhost/panel-admin/
Email:            admin@admin.com
Contraseña:       admin123
```

### 1.3 Comandos útiles

```bash
# Iniciar servicios
docker compose up -d

# Ver logs en tiempo real
docker compose logs -f

# Ver logs de un servicio específico
docker compose logs -f web
docker compose logs -f db
docker compose logs -f ollama

# Reconstruir la imagen web tras cambios
docker compose up -d --build web

# Detener servicios (conserva datos)
docker compose down

# Detener y borrar volúmenes (¡pierde datos!)
docker compose down -v

# Acceder al contenedor web
docker exec -it misitios-web bash

# Ver estado
docker compose ps
```

### 1.4 Descargar modelo DeepSeek

El modelo **deepseek-r1:1.5b** es necesario para el chatbot. Hay dos formas de descargarlo:

**Opción A — Durante el primer inicio (recomendada):**

```bash
# Iniciar servicios primero
docker compose up -d

# Ejecutar el inicializador de modelo (se ejecuta una vez)
docker compose --profile init run model-init
```

**Opción B — Manualmente dentro del contenedor:**

```bash
docker exec -it misitios-ollama ollama pull deepseek-r1:1.5b
```

> ⚠️ La descarga es de ~1 GB. Puede tomar varios minutos según la velocidad de internet.

**Verificar que el modelo está instalado:**

```bash
docker exec misitios-ollama ollama list
```

### 1.5 phpMyAdmin (opcional)

Para desarrollo, se puede incluir phpMyAdmin:

```bash
docker compose --profile dev up -d
```

Luego acceder en: **http://localhost:8080**

### 1.6 Variables de entorno

Las siguientes variables se pueden modificar en `docker-compose.yml`:

| Variable | Default | Descripción |
|----------|---------|-------------|
| `DB_HOST` | `db` | Host de MySQL |
| `DB_NAME` | `sistema_emprendimientos` | Nombre de la BD |
| `DB_USER` | `root` | Usuario de MySQL |
| `DB_PASS` | `rootpassword` | Contraseña de MySQL |
| `OLLAMA_URL` | `http://ollama:11434/api/chat` | URL del servicio Ollama |
| `OLLAMA_MODEL` | `deepseek-r1:1.5b` | Modelo de IA para el chatbot |
| `CHATBOT_TIMEOUT` | `600` | Timeout del chatbot en segundos |
| `MAX_FILE_SIZE` | `5242880` | Tamaño máximo de uploads (5 MB) |

### 1.7 Rendimiento y recursos

**Uso de recursos estimado:**

| Servicio | RAM | CPU | Propósito |
|----------|-----|-----|-----------|
| `web` | ~100 MB | Bajo | Apache + PHP |
| `db` | ~300 MB | Medio | MySQL 8.0 |
| `ollama` | ~1.5 GB | Alto (en inferencia) | DeepSeek 1.5B |

**CPU lento:** Si usás un procesador como Intel i3, el chatbot puede demorar hasta 10 minutos en responder. Se puede ajustar `CHATBOT_TIMEOUT` (en `config.php` o variable de entorno) para evitar timeouts.

**Sin GPU:** Ollama funciona en CPU, pero las respuestas serán más lentas. DeepSeek 1.5B funciona aceptablemente en CPU para uso esporádico.

---

## 2. Despliegue con XAMPP

### 2.1 Instalación manual

1. **Instalar XAMPP** desde [apachefriends.org](https://www.apachefriends.org/es/index.html)
2. **Iniciar Apache y MySQL** desde el panel de XAMPP
3. **Copiar el proyecto:**

```bash
# Linux
cp -r htdocs /opt/lampp/htdocs/misitios

# Windows
# Copiar la carpeta htdocs a C:/xampp/htdocs/misitios
```

4. **Ejecutar el instalador web:**
   Abrir en el navegador:
   ```
   http://localhost/misitios/instalar.php
   ```
   El instalador verificará los requisitos, creará la BD y el usuario admin.

5. **Acceder al panel:**
   ```
   URL:      http://localhost/misitios/panel-admin/
   Email:    admin@admin.com
   Pass:     admin123
   ```

6. **Instalar Ollama + DeepSeek:**
   ```bash
   curl -fsSL https://ollama.com/install.sh | sh
   ollama pull deepseek-r1:1.5b
   ```

### 2.2 Instalación con script

```bash
cd script_tools
chmod +x install.sh
./install.sh
```

El script detecta automáticamente:
- Sistema operativo (Linux/macOS/Windows)
- Ubicación de XAMPP
- Instala paquetes necesarios (apt/pacman/brew)
- Instala Ollama si no está presente
- Descarga DeepSeek si no existe
- Copia los archivos al directorio web

---

## 3. CI/CD con GitHub Actions

El workflow automático está definido en `.github/workflows/docker-build.yml`.

### 3.1 Jobs del workflow

**1. `test`** — Se ejecuta en cada push y PR:
   - Configura PHP 8.3 con extensiones requeridas
   - Valida `composer.json`
   - Ejecuta los tests de PHPUnit

**2. `docker`** — Solo en push a `main` (depende de `test`):
   - Construye la imagen Docker
   - Pushea a **GitHub Container Registry** (`ghcr.io`)
   - Tags: `latest`, `sha-{commit}`, `main`
   - Escanea vulnerabilidades con **Trivy**

**3. `docker-pr`** — Solo en PRs:
   - Construye la imagen (sin push)
   - Ejecuta un **smoke test**: inicia el contenedor, verifica HTTP 200, archivos clave y extensiones PHP

### 3.2 Ejecutar manualmente

Desde GitHub → Actions → **Docker Build & Test** → **Run workflow**

---

## 4. Solución de problemas

### 4.1 Docker

**Error: `permission denied while trying to connect to the Docker daemon socket`**

```bash
sudo usermod -aG docker $USER
# Cerrar sesión y volver a entrar
```

**Error: `port is already allocated`**

```bash
# Puerto 80 en uso. Detener el servicio que lo ocupa:
sudo systemctl stop apache2   # Linux
# O cambiar el puerto en docker-compose.yml:
# ports: - "8080:80"
```

**El build falla con `Package 'oniguruma' not found`**

```bash
# Agregar libonig-dev a la lista de paquetes en Dockerfile (ya solucionado)
```

### 4.2 Base de datos

**Error: `Connection refused` al conectar a MySQL**

```bash
# Verificar que MySQL está corriendo
docker compose logs db

# Verificar conectividad
docker exec misitios-web php -r "
    \$conn = @fsockopen('db', 3306, \$errno, \$errstr, 3);
    echo \$conn ? 'OK' : 'ERR: \$errstr';
"
```

**Error: `Unknown database 'sistema_emprendimientos'`**

El schema SQL se monta automáticamente en el primer inicio de MySQL. Si la BD no se creó:

```bash
# Ejecutar el schema manualmente
docker exec -i misitios-db mysql -uroot -prootpassword < htdocs/sistema_emprendimientos.sql

# Crear usuario admin
docker exec misitios-db mysql -uroot -prootpassword \
  -e "INSERT INTO sistema_emprendimientos.usuarios_admin (nombre, email, password_hash, rol) \
      VALUES ('Administrador', 'admin@admin.com', '\$(php -r 'echo password_hash(\"admin123\", PASSWORD_DEFAULT);')', 'superadmin');"
```

### 4.3 Chatbot / Ollama

**Error: `Ollama no responde. El chatbot usará fallback WhatsApp`**

```bash
# Verificar que Ollama está corriendo
docker compose logs ollama

# Verificar conectividad
curl http://localhost:11434/api/tags

# Si no responde, esperar a que termine la descarga del modelo
docker compose logs -f ollama

# O descargar manualmente
docker exec misitios-ollama ollama pull deepseek-r1:1.5b
```

**El chatbot responta con datos incorrectos o desactualizados**

El chatbot usa la información de la BD para generar sus respuestas. Si los productos, precios o políticas cambian, el chatbot reflejará los nuevos datos automáticamente (no requiere reinicio).

**Timeout del chatbot en CPUs lentos**

El timeout predeterminado es de 10 minutos. Si las respuestas tardan más, aumentar el valor:

```bash
# En docker-compose.yml, agregar:
# environment:
#   CHATBOT_TIMEOUT: 1200  # 20 minutos
```

O directamente en `htdocs/config.php`:

```php
define('CHATBOT_TIMEOUT', 900); // 15 minutos
```
