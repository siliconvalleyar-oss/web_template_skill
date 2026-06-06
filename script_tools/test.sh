#!/usr/bin/env bash
# ============================================
# test.sh - Ejecutor de tests del Sistema Multi-Emprendimiento
# ============================================
# Uso:
#   ./script_tools/test.sh            # Tests normales
#   ./script_tools/test.sh --coverage  # Con cobertura
# ============================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

# Verificar que Composer haya instalado las dependencias
if [ ! -d "vendor" ]; then
    echo "❌ No se encontró vendor/. Ejecuta 'composer install' primero."
    exit 1
fi

echo "============================================"
echo "  Sistema Multi-Emprendimiento - Tests"
echo "============================================"
echo ""

if [ "${1:-}" = "--coverage" ]; then
    echo "Ejecutando tests con cobertura..."
    shift  # Eliminar el primer argumento (--coverage)
    echo ""
    ./vendor/bin/phpunit --coverage-html coverage "$@"
    echo ""
    echo "📊 Reporte de cobertura: file://$(pwd)/coverage/index.html"
else
    ./vendor/bin/phpunit "$@"
fi

echo ""
echo "✅ Tests completados."
