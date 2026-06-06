-- ============================================================
-- TABLA: usuarios_admin (panel de administración)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('superadmin','admin') DEFAULT 'admin',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: emprendimientos
-- ============================================================
CREATE TABLE IF NOT EXISTS emprendimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    nombre VARCHAR(200) NOT NULL,
    eslogan VARCHAR(500) DEFAULT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultima_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: config_visual
-- ============================================================
CREATE TABLE IF NOT EXISTS config_visual (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    tema VARCHAR(50) DEFAULT 'classic-blue',
    color_principal VARCHAR(7) DEFAULT '#2563eb',
    color_secundario VARCHAR(7) DEFAULT '#7c3aed',
    color_fondo VARCHAR(7) DEFAULT '#ffffff',
    color_texto VARCHAR(7) DEFAULT '#1f2937',
    logo VARCHAR(255) DEFAULT NULL,
    favicon VARCHAR(255) DEFAULT NULL,
    titulo_seo VARCHAR(200) DEFAULT NULL,
    meta_descripcion TEXT DEFAULT NULL,
    UNIQUE KEY uk_emprendimiento (emprendimiento_id),
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: contacto_redes
-- ============================================================
CREATE TABLE IF NOT EXISTS contacto_redes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    telefono VARCHAR(50) DEFAULT NULL,
    email VARCHAR(200) DEFAULT NULL,
    direccion TEXT DEFAULT NULL,
    whatsapp_numero VARCHAR(50) DEFAULT NULL,
    whatsapp_mensaje_auto TEXT DEFAULT NULL,
    whatsapp_horarios VARCHAR(500) DEFAULT NULL,
    instagram_activo BOOLEAN DEFAULT FALSE,
    instagram_link VARCHAR(500) DEFAULT NULL,
    instagram_usuario VARCHAR(100) DEFAULT NULL,
    facebook_activo BOOLEAN DEFAULT FALSE,
    facebook_link VARCHAR(500) DEFAULT NULL,
    tiktok_activo BOOLEAN DEFAULT FALSE,
    tiktok_link VARCHAR(500) DEFAULT NULL,
    linkedin_activo BOOLEAN DEFAULT FALSE,
    linkedin_link VARCHAR(500) DEFAULT NULL,
    twitter_activo BOOLEAN DEFAULT FALSE,
    twitter_link VARCHAR(500) DEFAULT NULL,
    UNIQUE KEY uk_emprendimiento (emprendimiento_id),
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: contenido_texto
-- ============================================================
CREATE TABLE IF NOT EXISTS contenido_texto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    texto_quienes_somos TEXT DEFAULT NULL,
    texto_bienvenida TEXT DEFAULT NULL,
    politicas_envio TEXT DEFAULT NULL,
    politicas_devolucion TEXT DEFAULT NULL,
    UNIQUE KEY uk_emprendimiento (emprendimiento_id),
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: imagenes_carrusel
-- ============================================================
CREATE TABLE IF NOT EXISTS imagenes_carrusel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    imagen VARCHAR(255) NOT NULL,
    orden INT DEFAULT 0,
    titulo VARCHAR(200) DEFAULT NULL,
    subtitulo VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: productos
-- ============================================================
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    imagen VARCHAR(255) DEFAULT NULL,
    descripcion_corta TEXT DEFAULT NULL,
    destacado BOOLEAN DEFAULT FALSE,
    activo BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: formas_pago
-- ============================================================
CREATE TABLE IF NOT EXISTS formas_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    tipo ENUM('transferencia','mercadopago','efectivo','tarjeta') NOT NULL,
    descripcion TEXT DEFAULT NULL,
    datos_extra TEXT DEFAULT NULL,
    activo BOOLEAN DEFAULT TRUE,
    UNIQUE KEY uk_emprendimiento_tipo (emprendimiento_id, tipo),
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: clientes
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    email VARCHAR(200) DEFAULT NULL,
    telefono VARCHAR(50) DEFAULT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: ventas
-- ============================================================
CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    cliente_id INT DEFAULT NULL,
    producto_id INT DEFAULT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    total DECIMAL(10,2) NOT NULL,
    forma_pago VARCHAR(50) DEFAULT NULL,
    datos_cliente_json TEXT DEFAULT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM('pendiente','pagado','enviado','completado','cancelado') DEFAULT 'pendiente',
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: detalle_venta
-- ============================================================
CREATE TABLE IF NOT EXISTS detalle_venta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto_id INT NOT NULL,
    producto_nombre VARCHAR(200) NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: tickets_soporte
-- ============================================================
CREATE TABLE IF NOT EXISTS tickets_soporte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    cliente_id INT DEFAULT NULL,
    cliente_nombre VARCHAR(200) DEFAULT NULL,
    cliente_email VARCHAR(200) DEFAULT NULL,
    asunto VARCHAR(500) NOT NULL,
    mensaje TEXT NOT NULL,
    estado ENUM('abierto','en_proceso','cerrado') DEFAULT 'abierto',
    agente_asignado VARCHAR(100) DEFAULT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: respuestas_tickets
-- ============================================================
CREATE TABLE IF NOT EXISTS respuestas_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    autor_tipo ENUM('cliente','agente') NOT NULL DEFAULT 'cliente',
    autor_nombre VARCHAR(200) DEFAULT NULL,
    mensaje TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets_soporte(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: conversaciones_chatbot
-- ============================================================
CREATE TABLE IF NOT EXISTS conversaciones_chatbot (
    id INT AUTO_INCREMENT PRIMARY KEY,
    emprendimiento_id INT NOT NULL,
    session_id VARCHAR(100) NOT NULL,
    rol ENUM('user','assistant') NOT NULL,
    mensaje TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (emprendimiento_id) REFERENCES emprendimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

