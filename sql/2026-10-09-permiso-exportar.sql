-- Permiso para exportar el historial a Excel. Solo lo tiene el rol Administrador
-- (rol de sistema: recibe todos los permisos automáticamente); no se asigna a ningún otro rol.
INSERT IGNORE INTO regharina.permisos (clave, nombre, modulo) VALUES
  ('inventario.exportar', 'Exportar a Excel', 'Inventario');
