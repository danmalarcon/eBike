-- ============================================================================
-- Seed obligatorio de roles - eBike Management System (EMS)
-- ============================================================================
-- Ejecutar UNA VEZ después de crear el esquema de @database.md:
--   mysql -u root ebike_ems < database/seed_roles.sql
--
-- Motivo: usuarios.rol_id tiene una FK hacia roles(id_rol). Sin estas filas,
-- cualquier INSERT en usuarios (incluido el registro RF-001) falla con
-- SQLSTATE[23000] / error 1452. El rol 2 (cliente) es exigido por RF-001.
-- ============================================================================

USE ebike_ems;

INSERT INTO roles (id_rol, codigo_rol, nombre_rol, permisos, nivel_acceso)
VALUES
    (1, 'admin',   'Administrador', NULL, 10),
    (2, 'cliente', 'Cliente',       NULL, 1)
ON DUPLICATE KEY UPDATE
    codigo_rol   = VALUES(codigo_rol),
    nombre_rol   = VALUES(nombre_rol),
    nivel_acceso = VALUES(nivel_acceso);
