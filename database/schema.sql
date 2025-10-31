-- Base de datos para el CRM de la Cámara de Comercio
-- MySQL 5.7+

CREATE DATABASE IF NOT EXISTS crm_camara_comercio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crm_camara_comercio;

-- Tabla de configuración del sistema
CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de sectores
CREATE TABLE IF NOT EXISTS sectores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de categorías
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    sector_id INT,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sector_id) REFERENCES sectores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de membresías
CREATE TABLE IF NOT EXISTS membresias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    costo DECIMAL(10,2) DEFAULT 0,
    beneficios TEXT,
    vigencia_meses INT DEFAULT 12,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de vendedores
CREATE TABLE IF NOT EXISTS vendedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(20),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de empresas afiliadas
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    no_registro INT UNIQUE,
    no_mes INT,
    fecha_recibo DATE,
    no_recibo VARCHAR(50),
    no_factura VARCHAR(50),
    engomado VARCHAR(50),
    razon_social VARCHAR(255) NOT NULL,
    es_actualizacion TINYINT(1) DEFAULT 0,
    es_nueva TINYINT(1) DEFAULT 1,
    vendedor_id INT,
    tipo_afiliacion VARCHAR(100),
    membresia_id INT,
    direccion_comercial TEXT,
    fecha_renovacion DATE,
    rfc VARCHAR(13),
    email VARCHAR(100),
    telefono VARCHAR(20),
    whatsapp VARCHAR(20),
    representante VARCHAR(150),
    sector_id INT,
    categoria_id INT,
    direccion_fiscal TEXT,
    colonia VARCHAR(100),
    ciudad VARCHAR(100),
    codigo_postal VARCHAR(10),
    estado VARCHAR(50) DEFAULT 'Querétaro',
    logo VARCHAR(255),
    descripcion TEXT,
    servicios_productos TEXT,
    palabras_clave TEXT,
    sitio_web VARCHAR(255),
    facebook VARCHAR(255),
    instagram VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    verificado TINYINT(1) DEFAULT 0,
    fecha_verificacion DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendedor_id) REFERENCES vendedores(id) ON DELETE SET NULL,
    FOREIGN KEY (membresia_id) REFERENCES membresias(id) ON DELETE SET NULL,
    FOREIGN KEY (sector_id) REFERENCES sectores(id) ON DELETE SET NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX idx_razon_social (razon_social),
    INDEX idx_rfc (rfc),
    INDEX idx_email (email),
    INDEX idx_fecha_renovacion (fecha_renovacion),
    INDEX idx_sector (sector_id),
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de usuarios del sistema
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('PRESIDENCIA', 'DIRECCION', 'CONSEJERO', 'AFILADOR', 'CAPTURISTA', 'ENTIDAD_COMERCIAL', 'EMPRESA_TRACTORA') NOT NULL,
    empresa_id INT,
    telefono VARCHAR(20),
    whatsapp VARCHAR(20),
    avatar VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    email_verificado TINYINT(1) DEFAULT 0,
    codigo_verificacion VARCHAR(100),
    fecha_verificacion DATETIME,
    ultimo_acceso DATETIME,
    intentos_login INT DEFAULT 0,
    bloqueado_hasta DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de eventos
CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL,
    ubicacion VARCHAR(255),
    tipo ENUM('PUBLICO', 'INTERNO', 'CONSEJO', 'REUNION') DEFAULT 'PUBLICO',
    cupo_maximo INT,
    inscritos INT DEFAULT 0,
    imagen VARCHAR(255),
    enlace_externo VARCHAR(255),
    requiere_inscripcion TINYINT(1) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    creado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_fecha_inicio (fecha_inicio),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de inscripciones a eventos
CREATE TABLE IF NOT EXISTS eventos_inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    empresa_id INT,
    estado ENUM('CONFIRMADO', 'CANCELADO', 'EN_ESPERA') DEFAULT 'CONFIRMADO',
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_inscripcion (evento_id, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de requerimientos comerciales
CREATE TABLE IF NOT EXISTS requerimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    empresa_solicitante_id INT NOT NULL,
    usuario_creador_id INT NOT NULL,
    sector_id INT,
    categoria_id INT,
    palabras_clave TEXT,
    presupuesto_estimado DECIMAL(10,2),
    plazo_dias INT,
    fecha_limite DATE,
    estado ENUM('ABIERTO', 'EN_PROCESO', 'CERRADO', 'CANCELADO') DEFAULT 'ABIERTO',
    prioridad ENUM('BAJA', 'MEDIA', 'ALTA', 'URGENTE') DEFAULT 'MEDIA',
    respuestas_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_solicitante_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_creador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (sector_id) REFERENCES sectores(id) ON DELETE SET NULL,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX idx_estado (estado),
    INDEX idx_sector (sector_id),
    INDEX idx_categoria (categoria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de respuestas a requerimientos
CREATE TABLE IF NOT EXISTS requerimientos_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requerimiento_id INT NOT NULL,
    empresa_proveedora_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    propuesta_economica DECIMAL(10,2),
    tiempo_entrega_dias INT,
    documentos VARCHAR(255),
    estado ENUM('ENVIADA', 'VISTA', 'ACEPTADA', 'RECHAZADA') DEFAULT 'ENVIADA',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requerimiento_id) REFERENCES requerimientos(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_proveedora_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de notificaciones
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('RENOVACION', 'BIENVENIDA', 'REQUERIMIENTO', 'EVENTO', 'SISTEMA', 'RECORDATORIO') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    enlace VARCHAR(255),
    leida TINYINT(1) DEFAULT 0,
    enviada_email TINYINT(1) DEFAULT 0,
    enviada_whatsapp TINYINT(1) DEFAULT 0,
    fecha_envio DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_leida (leida),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de auditoría
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(100),
    registro_id INT,
    datos_anteriores TEXT,
    datos_nuevos TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_tabla (tabla_afectada),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de importaciones
CREATE TABLE IF NOT EXISTS importaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    archivo_original VARCHAR(255) NOT NULL,
    registros_totales INT DEFAULT 0,
    registros_exitosos INT DEFAULT 0,
    registros_fallidos INT DEFAULT 0,
    errores TEXT,
    estado ENUM('PROCESANDO', 'COMPLETADO', 'ERROR') DEFAULT 'PROCESANDO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pagos
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    usuario_id INT,
    concepto VARCHAR(255) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('EFECTIVO', 'TRANSFERENCIA', 'TARJETA', 'PAYPAL', 'OTRO') NOT NULL,
    referencia VARCHAR(100),
    estado ENUM('PENDIENTE', 'COMPLETADO', 'CANCELADO', 'REEMBOLSADO') DEFAULT 'PENDIENTE',
    fecha_pago DATETIME,
    notas TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_empresa (empresa_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_pago)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de búsquedas (para estadísticas)
CREATE TABLE IF NOT EXISTS busquedas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    termino VARCHAR(255) NOT NULL,
    tipo ENUM('GENERAL', 'EMPRESA', 'PRODUCTO', 'SERVICIO') DEFAULT 'GENERAL',
    resultados_count INT DEFAULT 0,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_termino (termino),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
