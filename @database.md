# Base de Datos eBike Management System (EMS)

## Reglas Estrictas para el Agente
- Motor de Base de Datos: MySQL 8.0 / MariaDB (InnoDB).
- Conexión: Utilizar EXCLUSIVAMENTE **PDO** (PHP Data Objects) con consultas preparadas.
- Prohibido modificar este esquema. El código PHP debe adaptarse a estas tablas, campos y relaciones.
- Las tablas contienen columnas generadas virtualmente (`duracion_minutos`, `total_costo`); el repositorio de PHP jamás debe intentar insertar o actualizar estos campos (son de solo lectura para la app).

## Script SQL Oficial (Fuente de la Verdad)
El siguiente script es la fuente oficial para la creación de la base de datos.
No inventes tablas, columnas ni relaciones diferentes a las definidas aquí.

```sql
CREATE DATABASE IF NOT EXISTS ebike_ems
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ebike_ems;

-- 1. ROLES
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    codigo_rol VARCHAR(20) NOT NULL UNIQUE,
    nombre_rol VARCHAR(50) NOT NULL,
    permisos JSON,
    nivel_acceso INT DEFAULT 1
) ENGINE=InnoDB;

-- 2. USUARIOS
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Soporte para bcrypt
    telefono VARCHAR(20),
    estado ENUM('activo', 'inactivo', 'suspendido') DEFAULT 'activo',
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id_rol) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 3. ESTACIONES
CREATE TABLE estaciones (
    id_estacion INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    coordenadas VARCHAR(50) NOT NULL, -- Ej: "-75.567, 6.245"
    capacidad INT NOT NULL CHECK (capacidad > 0),
    energia_disp DECIMAL(8,2),
    estado ENUM('operativa', 'mantenimiento', 'inactiva') DEFAULT 'operativa'
) ENGINE=InnoDB;

-- 4. BICICLETAS
CREATE TABLE bicicletas (
    id_bicicleta INT AUTO_INCREMENT PRIMARY KEY,
    estacion_act INT,
    codigo_qr VARCHAR(100) NOT NULL UNIQUE,
    modelo VARCHAR(100) NOT NULL,
    num_serie VARCHAR(100) NOT NULL UNIQUE,
    nivel_bateria DECIMAL(5,2) DEFAULT 100.00,
    estado ENUM('disponible', 'alquilada', 'mantenimiento', 'baja') DEFAULT 'disponible',
    kilometraje DECIMAL(10,2) DEFAULT 0.00,
    ult_manten DATETIME,
    prox_manten DATETIME,
    FOREIGN KEY (estacion_act) REFERENCES estaciones(id_estacion) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 5. ALQUILERES
CREATE TABLE alquileres (
    id_alquiler INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    id_bicicleta INT NOT NULL,
    estacion_orig INT NOT NULL,
    estacion_dest INT,
    uuid_alquiler VARCHAR(36) NOT NULL UNIQUE,
    fecha_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_fin DATETIME,
    duracion_minutos INT GENERATED ALWAYS AS (TIMESTAMPDIFF(MINUTE, fecha_inicio, fecha_fin)) VIRTUAL,
    costo_total DECIMAL(10,2),
    estado ENUM('en_curso', 'completado', 'cancelado', 'incidencia') DEFAULT 'en_curso',
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_bicicleta) REFERENCES bicicletas(id_bicicleta),
    FOREIGN KEY (estacion_orig) REFERENCES estaciones(id_estacion),
    FOREIGN KEY (estacion_dest) REFERENCES estaciones(id_estacion)
) ENGINE=InnoDB;

-- 6. METODOS DE PAGO
CREATE TABLE metodos_pago (
    id_metodo INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    tipo ENUM('tarjeta_credito', 'tarjeta_debito', 'transferencia', 'billetera_digital'),
    token_hash VARCHAR(255) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. TRANSACCIONES
CREATE TABLE transacciones (
    id_transaccion INT AUTO_INCREMENT PRIMARY KEY,
    id_alquiler INT NOT NULL,  
    id_metodo_pago INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    estado_pago ENUM('pendiente', 'aprobado', 'rechazado', 'reembolsado') DEFAULT 'pendiente',
    referencia_ext VARCHAR(100),
    fecha_proceso DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_alquiler) REFERENCES alquileres(id_alquiler),
    FOREIGN KEY (id_metodo_pago) REFERENCES metodos_pago(id_metodo)
) ENGINE=InnoDB;

-- Índices compuestos para optimización de consultas
CREATE INDEX idx_usuario_estado ON usuarios(id_usuario, estado);
CREATE INDEX idx_bici_estado ON bicicletas(estacion_act, estado);

-- 8. MANTENIMIENTOS
CREATE TABLE mantenimientos (
    id_manten INT AUTO_INCREMENT PRIMARY KEY,
    id_bicicleta INT NOT NULL,
    tipo_manten VARCHAR(50) NOT NULL,
    descripcion TEXT,
    tecnico VARCHAR(100),
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME,
    costo DECIMAL(10,2),
    FOREIGN KEY (id_bicicleta) REFERENCES bicicletas(id_bicicleta) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 9. REGISTRO_ENERGIA
CREATE TABLE registro_energia (
    id_registro INT AUTO_INCREMENT PRIMARY KEY,
    id_estacion INT NOT NULL,
    tipo_operac VARCHAR(50) NOT NULL,
    cantidad_kwh DECIMAL(10,2) NOT NULL,
    costo_kwh DECIMAL(10,2) NOT NULL,
    total_costo DECIMAL(10,2) GENERATED ALWAYS AS (cantidad_kwh * costo_kwh) VIRTUAL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT,
    FOREIGN KEY (id_estacion) REFERENCES estaciones(id_estacion) ON DELETE CASCADE
) ENGINE=InnoDB;
```