-- Alertas cuando un inventario supera al anterior (por producto) y registro de avisos enviados
USE inventario;

CREATE TABLE IF NOT EXISTS alertas_inventario (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventario_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  anterior_inventario_id INT UNSIGNED NOT NULL,
  anterior_kg DECIMAL(12,3) NOT NULL,
  nuevo_kg DECIMAL(12,3) NOT NULL,
  diferencia_kg DECIMAL(12,3) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alerta (inventario_id, producto_id),
  CONSTRAINT fk_alerta_inv FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_alerta_prod FOREIGN KEY (producto_id) REFERENCES productos(id),
  CONSTRAINT fk_alerta_ant FOREIGN KEY (anterior_inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notificaciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventario_id INT UNSIGNED NULL,           -- NULL = mensaje de prueba
  canal ENUM('telegram', 'slack') NOT NULL,
  ok TINYINT(1) NOT NULL,
  respuesta VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notif_inv FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
