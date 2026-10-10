-- Instalación nueva: ejecutar este archivo y luego las migraciones de sql/ en orden de fecha.
CREATE DATABASE IF NOT EXISTS regharina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE regharina;

CREATE TABLE IF NOT EXISTS unidades_medida (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  abreviatura VARCHAR(15) NOT NULL,
  unidad_base_id INT UNSIGNED NULL,
  factor DECIMAL(12,4) NOT NULL DEFAULT 1,
  CONSTRAINT fk_um_base FOREIGN KEY (unidad_base_id) REFERENCES unidades_medida(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  unidad_base_id INT UNSIGNED NOT NULL,
  CONSTRAINT fk_prod_um FOREIGN KEY (unidad_base_id) REFERENCES unidades_medida(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha_hora DATETIME NOT NULL,
  nota VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,  -- soft delete: NULL = activo
  INDEX idx_fecha (fecha_hora),
  INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventario_detalle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventario_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(12,3) NOT NULL DEFAULT 0,  -- total en la unidad base del producto (kg)
  detalles JSON NOT NULL,                     -- desglose: unidad elegida, cantidad en esa unidad, factor, abierto
  CONSTRAINT fk_det_inv FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_det_prod FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO unidades_medida (id, nombre, abreviatura, unidad_base_id, factor) VALUES
  (1, 'Kilogramo', 'kg', NULL, 1),
  (2, 'Saco 50 kg', 'saco', 1, 50);

INSERT IGNORE INTO productos (id, nombre, unidad_base_id) VALUES
  (1, 'Harina Extra', 1),
  (2, 'Harina Especial', 1);
