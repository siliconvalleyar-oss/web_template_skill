# ============================================
# Dockerfile - Sistema Multi-Emprendimiento
# PHP 8 + Apache + Extensiones
# ============================================
FROM php:8.3-apache AS base

LABEL description="Sistema Multi-Emprendimiento con DeepSeek 7B"
LABEL maintainer="Silicon Valley AR"

# ============================================
# Instalar dependencias del sistema y extensiones PHP
# ============================================
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        git \
        unzip \
        curl \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        mbstring \
        curl \
    ; \
    # Limpiar cache de apt
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*; \
    # Verificar extensiones
    php -m | grep -E 'pdo|pdo_mysql|mysqli|gd|zip|mbstring|curl'

# ============================================
# Habilitar módulos de Apache
# ============================================
RUN set -eux; \
    a2enmod rewrite headers expires deflate; \
    # Verificar módulos
    apache2ctl -M 2>/dev/null | grep -E 'rewrite|headers|expires|deflate'

# ============================================
# Copiar el código de la aplicación
# ============================================
COPY htdocs/ /var/www/html/

# ============================================
# Configurar permisos
# ============================================
RUN set -eux; \
    chown -R www-data:www-data /var/www/html; \
    # Crear directorios necesarios para uploads y backups
    mkdir -p /var/www/html/backups; \
    chmod -R 755 /var/www/html

# ============================================
# Copiar entrypoint personalizado
# ============================================
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

# ============================================
# Healthcheck
# ============================================
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/ || exit 1
