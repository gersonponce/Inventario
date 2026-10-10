<?php
namespace App\Support;

final class Acceso
{
    // Usuario activo con su rol y la lista de claves de permiso; null si no existe o está inactivo.
    // El rol de sistema (Administrador) recibe siempre todos los permisos, incluidos los que se agreguen después.
    public static function usuario(int $id): ?array
    {
        $pdo = db();
        $st = $pdo->prepare(
            'SELECT u.id, u.usuario, u.nombre, u.rol_id, r.nombre AS rol, r.es_sistema
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             WHERE u.id = ? AND u.activo = 1'
        );
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) {
            return null;
        }

        if ($u['es_sistema']) {
            $permisos = $pdo->query('SELECT clave FROM permisos ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $st = $pdo->prepare(
                'SELECT p.clave FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ? ORDER BY p.id'
            );
            $st->execute([$u['rol_id']]);
            $permisos = $st->fetchAll(\PDO::FETCH_COLUMN);
        }

        return [
            'id' => (int)$u['id'],
            'usuario' => $u['usuario'],
            'nombre' => $u['nombre'],
            'rol_id' => (int)$u['rol_id'],
            'rol' => $u['rol'],
            'es_admin' => (bool)$u['es_sistema'],
            'permisos' => $permisos,
        ];
    }
}
