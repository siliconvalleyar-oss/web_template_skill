#!/bin/bash
# ============================================
# docker-entrypoint.sh
# Configura el entorno Docker y arranca Apache
# ============================================
set -e

echo "============================================"
echo "  Sistema Multi-Emprendimiento - Docker"
echo "============================================"

# === 1. Esperar a que MySQL esté disponible ===
if [ -n "${DB_HOST:-}" ]; then
    echo "[1/3] Esperando a MySQL en $DB_HOST:3306..."
    # Primero verificar que el hostname se resuelve via DNS de Docker
    for i in $(seq 1 5); do
        if getent hosts "$DB_HOST" > /dev/null 2>&1; then
            echo "  DNS resuelve $DB_HOST correctamente."
            break
        fi
        sleep 2
    done

    for i in $(seq 1 30); do
        if php -r "
            \$conn = @fsockopen('$DB_HOST', 3306, \$errno, \$errstr, 2);
            exit(\$conn ? 0 : 1);
        " 2>/dev/null; then
            echo "  MySQL listo."
            break
        fi
        if [ "$i" -eq 30 ]; then
            echo "  [AVISO] MySQL no disponible tras 30 intentos."
        fi
        sleep 2
    done

    # === 1b. Crear usuario admin por defecto si la BD ya existe ===
    echo "  Verificando usuario admin..."
    php -r "
        try {
            \$db = new PDO(
                'mysql:host=${DB_HOST};dbname=${DB_NAME:-sistema_emprendimientos}',
                '${DB_USER:-root}',
                '${DB_PASS:-}',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Verificar si la tabla usuarios_admin existe
            \$tables = \$db->query('SHOW TABLES LIKE \"usuarios_admin\"')->fetchAll();
            if (count(\$tables) > 0) {
                \$stmt = \$db->query('SELECT COUNT(*) as cnt FROM usuarios_admin');
                \$row = \$stmt->fetch();
                if (\$row['cnt'] == 0) {
                    \$hash = password_hash('admin123', PASSWORD_DEFAULT);
                    \$db->prepare('INSERT INTO usuarios_admin (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)')
                        ->execute(['Administrador', 'admin@admin.com', \$hash, 'superadmin']);
                    echo '  Usuario admin creado: admin@admin.com / admin123' . PHP_EOL;
                } else {
                    echo '  Usuario admin ya existe.' . PHP_EOL;
                }
            }
        } catch (Exception \$e) {
            echo '  [AVISO] No se pudo verificar admin: ' . \$e->getMessage() . PHP_EOL;
        }
    "
else
    echo "[1/3] DB_HOST no definido, usando configuración local."
fi

# === 2. Verificar Ollama (no bloqueante) ===
if [ -n "${OLLAMA_URL:-}" ]; then
    echo "[2/3] Verificando Ollama..."
    OLLAMA_HOST=$(echo "$OLLAMA_URL" | sed -E 's|https?://([^:/]+).*|\1|')
    OLLAMA_PORT=$(echo "$OLLAMA_URL" | sed -E 's|https?://[^:]+:?([0-9]*)/?.*|\1|')
    OLLAMA_PORT=${OLLAMA_PORT:-11434}

    if php -r "
        \$conn = @fsockopen('$OLLAMA_HOST', $OLLAMA_PORT, \$errno, \$errstr, 3);
        exit(\$conn ? 0 : 1);
    " 2>/dev/null; then
        echo "  Ollama disponible en $OLLAMA_URL"
    else
        echo "  [AVISO] Ollama no responde. El chatbot usará fallback WhatsApp."
    fi
else
    echo "[2/3] OLLAMA_URL no definido."
fi

# === 3. Resumen ===
echo ""
echo "[3/3] Aplicación lista"
echo ""
echo "============================================"
echo "  URL:              http://localhost/"
echo "  Panel admin:      http://localhost/panel-admin/"
echo "  Email:            admin@admin.com"
echo "  Contraseña:       admin123"
echo "  MySQL:            localhost:3306 (user: root, pass: rootpassword)"
echo "  Ollama:           localhost:11434"
echo "  phpMyAdmin:       http://localhost:8080 (con --profile dev)"
echo "============================================"
echo ""

exec "$@"
