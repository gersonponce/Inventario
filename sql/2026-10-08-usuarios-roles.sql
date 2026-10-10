-- Migración: usuarios, roles y permisos (reemplaza la contraseña compartida)
USE inventario;

CREATE TABLE IF NOT EXISTS permisos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  modulo VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  es_sistema TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = Administrador: todos los permisos, no editable
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rol_permiso (
  rol_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  nombre VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol_id INT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

ALTER TABLE inventarios
  ADD COLUMN creado_por INT UNSIGNED NULL AFTER nota,
  ADD COLUMN eliminado_por INT UNSIGNED NULL AFTER deleted_at,
  ADD CONSTRAINT fk_inv_creado FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  ADD CONSTRAINT fk_inv_eliminado FOREIGN KEY (eliminado_por) REFERENCES usuarios(id);

INSERT IGNORE INTO permisos (clave, nombre, modulo) VALUES
  ('inventario.ver',       'Ver historial de inventarios', 'Inventario'),
  ('inventario.registrar', 'Registrar inventarios',        'Inventario'),
  ('inventario.eliminar',  'Eliminar inventarios',         'Inventario'),
  ('inventario.restaurar', 'Ver y restaurar eliminados',   'Inventario'),
  ('usuarios.gestionar',   'Gestionar usuarios',           'Administración'),
  ('roles.gestionar',      'Gestionar roles y permisos',   'Administración');

INSERT IGNORE INTO roles (id, nombre, descripcion, es_sistema) VALUES
  (1, 'Administrador', 'Acceso total. Rol del sistema: siempre tiene todos los permisos.', 1),
  (2, 'Operador', 'Registra inventarios y consulta el historial.', 0);

INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
  SELECT 2, id FROM permisos WHERE clave IN ('inventario.ver', 'inventario.registrar');

-- La app puede borrar solo asignaciones de permisos y roles sin usuarios (inventarios y usuarios nunca)
GRANT DELETE ON inventario.rol_permiso TO 'regharina'@'127.0.0.1';
GRANT DELETE ON inventario.roles TO 'regharina'@'127.0.0.1';
