#!/usr/bin/env bash
# ============================================
# install.sh - Instalador del Sistema Multi-Emprendimiento
# ============================================
# Este script instala las dependencias necesarias y copia los archivos
# al directorio de XAMPP para que el sistema quede funcional.
#
# Uso:
#   chmod +x install.sh
#   ./install.sh
# ============================================

set -e

# Colores para output
ROJO='\033[0;31m'
VERDE='\033[0;32m'
AMARILLO='\033[1;33m'
AZUL='\033[0;34m'
NC='\033[0m' # Sin color

# === Configuración ===
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
HTDOCS_DIR="$PROJECT_DIR/htdocs"

# Verificar que curl esté instalado
if ! command -v curl &>/dev/null; then
    echo -e "  ${ROJO}✗${NC} curl no está instalado. Instálalo con:"
    echo "    sudo apt install curl   # Debian/Ubuntu"
    echo "    sudo pacman -S curl     # Arch"
    echo "    brew install curl       # macOS"
    exit 1
fi

detectar_xampp() {
    # Posibles ubicaciones de XAMPP
    local XAMPP_CANDIDATES=(
        "/opt/lampp"
        "/Applications/XAMPP"
        "/Applications/XAMPP/xamppfiles"
        "C:/xampp"
        "/srv/http"
        "/var/www/html"
    )

    for dir in "${XAMPP_CANDIDATES[@]}"; do
        if [ -d "$dir" ] && [ -d "$dir/htdocs" ]; then
            echo "$dir"
            return 0
        fi
    done

    # Buscar en ubicaciones comunes de Linux
    if [ -d "/opt/lampp/htdocs" ]; then
        echo "/opt/lampp"
        return 0
    fi

    return 1
}

echo -e "${AZUL}============================================${NC}"
echo -e "${AZUL}  Sistema Multi-Emprendimiento - Instalador${NC}"
echo -e "${AZUL}============================================${NC}"
echo ""

# === Paso 1: Detectar sistema operativo ===
echo -e "${AZUL}[1/5]${NC} Detectando sistema operativo..."

OS=""
case "$(uname -s)" in
    Linux*)     OS="linux";;
    Darwin*)    OS="macos";;
    MINGW*|MSYS*) OS="windows";;
    *)          OS="unknown";;
esac
echo -e "  Sistema detectado: ${VERDE}$OS${NC}"
echo ""

# === Paso 2: Detectar / Instalar XAMPP ===
echo -e "${AZUL}[2/5]${NC} Buscando XAMPP..."

XAMPP_DIR=""
if XAMPP_DIR=$(detectar_xampp); then
    echo -e "  ${VERDE}✓${NC} XAMPP encontrado en: $XAMPP_DIR"
else
    echo -e "  ${AMARILLO}⚠${NC} XAMPP no encontrado."

    case "$OS" in
        linux)
            echo -e "  ${AZUL}→${NC} Instalando XAMPP para Linux..."
            echo "    Descarga desde: https://www.apachefriends.org/es/index.html"
            echo "    O instala Apache+PHP+MySQL con tu gestor de paquetes:"
            echo ""
            echo "    # Debian/Ubuntu:"
            echo "    sudo apt update"
            echo "    sudo apt install -y apache2 mysql-server php php-mysql php-curl php-gd php-mbstring php-json libapache2-mod-php"
            echo "    sudo systemctl start apache2 mysql"
            echo ""
            echo "    # Arch Linux:"
            echo "    sudo pacman -S apache mariadb php php-apache php-mysqli php-curl php-gd php-mbstring"
            echo "    sudo systemctl start httpd mariadb"
            echo ""
            # Intentar con el gestor de paquetes
            if command -v apt &>/dev/null; then
                echo -e "  ${AZUL}→${NC} Instalando con apt..."
                sudo apt update && sudo apt install -y apache2 mysql-server php php-mysql php-curl php-gd php-mbstring php-json libapache2-mod-php
                sudo systemctl enable --now apache2 mysql 2>/dev/null || true
                XAMPP_DIR="/var/www/html"
            elif command -v pacman &>/dev/null; then
                echo -e "  ${AZUL}→${NC} Instalando con pacman..."
                sudo pacman -S --noconfirm apache mariadb php php-apache php-mysqli php-curl php-gd php-mbstring
                sudo systemctl enable --now httpd mariadb 2>/dev/null || true
                XAMPP_DIR="/srv/http"
            else
                echo -e "  ${ROJO}✗${NC} No se pudo instalar automáticamente. Instala XAMPP manualmente."
                echo "    Descarga: https://www.apachefriends.org/es/index.html"
                exit 1
            fi
            ;;
        macos)
            echo -e "  ${AZUL}→${NC} Descarga XAMPP desde: https://www.apachefriends.org/es/index.html"
            echo "    O instala con Homebrew:"
            echo "    brew install httpd mysql php"
            if command -v brew &>/dev/null; then
                echo -e "  ${AZUL}→${NC} Instalando con Homebrew..."
                brew install httpd mysql php
                brew services start httpd mysql
                XAMPP_DIR="/opt/homebrew/var/www"
            else
                exit 1
            fi
            ;;
        windows)
            echo -e "  ${ROJO}✗${NC} En Windows, descarga XAMPP desde: https://www.apachefriends.org/es/index.html"
            echo "    O usa XAMPP Portable."
            exit 1
            ;;
    esac
fi
echo ""

# === Paso 3: Copiar archivos a XAMPP ===
echo -e "${AZUL}[3/5]${NC} Copiando archivos del proyecto..."

if [ -n "$XAMPP_DIR" ]; then
    # Asegurar que el directorio htdocs existe
    if [ -d "$XAMPP_DIR/htdocs" ]; then
        TARGET_DIR="$XAMPP_DIR/htdocs/misitios"
    elif [ -d "$XAMPP_DIR" ] && [ "$(basename "$XAMPP_DIR")" != "htdocs" ]; then
        # Caso: el directorio mismo es el web root (como /var/www/html)
        TARGET_DIR="$XAMPP_DIR/misitios"
    else
        TARGET_DIR="$XAMPP_DIR/misitios"
    fi

    echo -e "  Destino: ${AZUL}$TARGET_DIR${NC}"

    if [ ! -d "$HTDOCS_DIR" ]; then
        echo -e "  ${ROJO}✗${NC} No se encontró el directorio 'htdocs' en el proyecto."
        exit 1
    fi

    if [ -d "$TARGET_DIR" ]; then
        echo -e "  ${AMARILLO}⚠${NC} El directorio '$TARGET_DIR' ya existe."
        read -p "  ¿Deseas sobrescribirlo? (s/N): " CONFIRM
        if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
            echo -e "  ${AMARILLO}⚠${NC} Instalación cancelada por el usuario."
            exit 0
        fi
    fi

    # Copiar archivos
    mkdir -p "$TARGET_DIR"
    cp -r "$HTDOCS_DIR"/* "$TARGET_DIR/"
    echo -e "  ${VERDE}✓${NC} Archivos copiados exitosamente."
    INSTALLED_PATH="$TARGET_DIR"
else
    echo -e "  ${ROJO}✗${NC} No se pudo determinar el directorio de destino."
    echo "  Copia manualmente la carpeta 'htdocs' a tu directorio de XAMPP."
    INSTALLED_PATH=""
fi
echo ""

# === Paso 4: Verificar/Instalar Ollama ===
echo -e "${AZUL}[4/5]${NC} Verificando Ollama (para el chatbot IA)..."

OLLAMA_OK=false
if command -v ollama &>/dev/null; then
    echo -e "  ${VERDE}✓${NC} Ollama encontrado."
    # Verificar que el servicio esté corriendo
    if curl -s http://localhost:11434/api/tags &>/dev/null; then
        echo -e "  ${VERDE}✓${NC} Servicio Ollama activo en localhost:11434"
        OLLAMA_OK=true
    else
        echo -e "  ${AMARILLO}⚠${NC} Ollama instalado pero no está corriendo."
        echo "  Ejecuta: ollama serve"
        OLLAMA_OK=true
    fi
else
    echo -e "  ${AMARILLO}⚠${NC} Ollama no está instalado."
    echo ""
    echo "  Opción 1: Instalación automática (Linux/macOS):"
    echo "    curl -fsSL https://ollama.com/install.sh | sh"
    echo ""
    echo "  Opción 2: Descarga manual desde https://ollama.com/download"
    echo ""
    read -p "  ¿Deseas instalar Ollama ahora? (s/N): " INSTALL_OLLAMA
    if [ "$INSTALL_OLLAMA" = "s" ] || [ "$INSTALL_OLLAMA" = "S" ]; then
        echo -e "  ${AZUL}→${NC} Instalando Ollama..."
        curl -fsSL https://ollama.com/install.sh | sh
        echo -e "  ${VERDE}✓${NC} Ollama instalado."
        OLLAMA_OK=true
    fi
fi

# Descargar modelo DeepSeek
if [ "$OLLAMA_OK" = true ]; then
    echo ""
    echo -e "  ${AZUL}→${NC} Verificando modelo DeepSeek..."
    if ollama list 2>/dev/null | grep -qi deepseek; then
        echo -e "  ${VERDE}✓${NC} Modelo DeepSeek ya está descargado."
    else
        echo -e "  ${AMARILLO}⚠${NC} Modelo DeepSeek no encontrado."
        read -p "  ¿Deseas descargar deepseek-r1:1.5b (~1GB)? (s/N): " PULL_MODEL
        if [ "$PULL_MODEL" = "s" ] || [ "$PULL_MODEL" = "S" ]; then
            echo -e "  ${AZUL}→${NC} Descargando deepseek-r1:1.5b..."
            ollama pull deepseek-r1:1.5b
            echo -e "  ${VERDE}✓${NC} Modelo descargado."
        fi
    fi
fi
echo ""

# === Paso 5: Resumen final ===
echo -e "${AZUL}============================================${NC}"
echo -e "${AZUL}  ✅ Instalación completada${NC}"
echo -e "${AZUL}============================================${NC}"
echo ""

if [ -n "$INSTALLED_PATH" ]; then
    echo -e "  📁 Proyecto copiado a: ${VERDE}$INSTALLED_PATH${NC}"
    echo ""
    echo -e "  Siguientes pasos:"
    echo -e "  1. ${AZUL}Abre tu navegador${NC} y visita:"
    echo -e "     ${VERDE}http://localhost/misitios/instalar.php${NC}"
    echo ""
    echo -e "  2. El instalador web verificará:"
    echo -e "     - PHP 8+"
    echo -e "     - Extensiones requeridas"
    echo -e "     - Conexión a MySQL"
    echo -e "     - Ollama + DeepSeek"
    echo ""
    echo -e "  3. Luego creará la BD y las tablas automáticamente."
    echo ""
    echo -e "  4. Accede al panel admin:"
    echo -e "     ${VERDE}http://localhost/misitios/panel-admin/${NC}"
    echo -e "     Email: ${AMARILLO}admin@admin.com${NC}"
    echo -e "     Pass:  ${AMARILLO}admin123${NC}"
fi

echo ""
echo -e "${AZUL}============================================${NC}"
echo -e "${VERDE}  ¡Gracias por usar el Sistema Multi-Emprendimiento!${NC}"
echo -e "${AZUL}============================================${NC}"
