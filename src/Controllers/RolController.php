<?php
namespace App\Controllers;

use App\Http\ApiError;
use App\Http\Json;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class RolController
{
    // GET /api/roles → roles (con sus permisos y nº de usuarios) y el catálogo de permisos
    public function listar(Request $req, Response $res): Response
    {
        $pdo = db();
        $permisos = $pdo->query('SELECT clave, nombre, modulo FROM permisos ORDER BY id')->fetchAll();
        $todas = array_column($permisos, 'clave');

        $roles = $pdo->query(
            'SELECT r.id, r.nombre, r.descripcion, r.es_sistema,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS usuarios
             FROM roles r ORDER BY r.es_sistema DESC, r.nombre'
        )->fetchAll();
        $asignados = [];
        foreach ($pdo->query('SELECT rp.rol_id, p.clave FROM rol_permiso rp JOIN permisos p ON p.id = rp.permiso_id ORDER BY p.id') as $r) {
            $asignados[$r['rol_id']][] = $r['clave'];
        }

        $out = array_map(fn ($r) => [
            'id' => (int)$r['id'],
            'nombre' => $r['nombre'],
            'descripcion' => $r['descripcion'],
            'es_sistema' => (bool)$r['es_sistema'],
            'usuarios' => (int)$r['usuarios'],
            'permisos' => $r['es_sistema'] ? $todas : ($asignados[$r['id']] ?? []),
        ], $roles);

        return Json::respond($res, ['roles' => $out, 'permisos' => $permisos]);
    }

    // POST /api/roles {nombre, descripcion, permisos:[clave]}
    public function crear(Request $req, Response $res): Response
    {
        $in = (array)($req->getParsedBody() ?? []);
        [$nombre, $descripcion, $permisoIds] = $this->validar($in);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO roles (nombre, descripcion) VALUES (?, ?)')->execute([$nombre, $descripcion]);
            $id = (int)$pdo->lastInsertId();
            $this->asignarPermisos($id, $permisoIds);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $this->traducirDuplicado($e);
        }
        return Json::respond($res, ['id' => $id], 201);
    }

    // PUT /api/roles/{id}
    public function actualizar(Request $req, Response $res, array $args): Response
    {
        $id = (int)$args['id'];
        $this->rolEditable($id);
        [$nombre, $descripcion, $permisoIds] = $this->validar((array)($req->getParsedBody() ?? []));

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE roles SET nombre = ?, descripcion = ? WHERE id = ?')->execute([$nombre, $descripcion, $id]);
            $pdo->prepare('DELETE FROM rol_permiso WHERE rol_id = ?')->execute([$id]);
            $this->asignarPermisos($id, $permisoIds);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $this->traducirDuplicado($e);
        }
        return Json::respond($res, ['ok' => true]);
    }

    // DELETE /api/roles/{id} → solo si ningún usuario (activo o no) lo tiene asignado
    public function eliminar(Request $req, Response $res, array $args): Response
    {
        $id = (int)$args['id'];
        $this->rolEditable($id);
        $st = db()->prepare('SELECT COUNT(*) FROM usuarios WHERE rol_id = ?');
        $st->execute([$id]);
        if ($st->fetchColumn() > 0) {
            throw new ApiError('No se puede eliminar: hay usuarios con este rol. Reasígnalos primero.', 409);
        }
        db()->prepare('DELETE FROM roles WHERE id = ?')->execute([$id]);
        return Json::respond($res, ['ok' => true]);
    }

    private function rolEditable(int $id): void
    {
        $st = db()->prepare('SELECT es_sistema FROM roles WHERE id = ?');
        $st->execute([$id]);
        $esSistema = $st->fetchColumn();
        if ($esSistema === false) {
            throw new ApiError('Rol no encontrado.', 404);
        }
        if ($esSistema) {
            throw new ApiError('El rol Administrador es del sistema y no se puede modificar.', 403);
        }
    }

    private function validar(array $in): array
    {
        $nombre = trim((string)($in['nombre'] ?? ''));
        if ($nombre === '' || largo($nombre) > 50) {
            throw new ApiError('El nombre del rol es obligatorio (máximo 50 caracteres).');
        }
        $descripcion = trim((string)($in['descripcion'] ?? '')) ?: null;
        if ($descripcion !== null && largo($descripcion) > 255) {
            throw new ApiError('La descripción admite máximo 255 caracteres.');
        }

        $claves = array_values(array_unique(array_filter((array)($in['permisos'] ?? []), 'is_string')));
        $ids = [];
        if ($claves) {
            $st = db()->prepare('SELECT id FROM permisos WHERE clave IN (' . implode(',', array_fill(0, count($claves), '?')) . ')');
            $st->execute($claves);
            $ids = $st->fetchAll(\PDO::FETCH_COLUMN);
            if (count($ids) !== count($claves)) {
                throw new ApiError('Hay permisos no válidos.');
            }
        }
        return [$nombre, $descripcion, $ids];
    }

    private function asignarPermisos(int $rolId, array $permisoIds): void
    {
        $ins = db()->prepare('INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)');
        foreach ($permisoIds as $pid) {
            $ins->execute([$rolId, $pid]);
        }
    }

    private function traducirDuplicado(Throwable $e): Throwable
    {
        if ($e instanceof PDOException && ($e->errorInfo[1] ?? null) === 1062) {
            return new ApiError('Ya existe un rol con ese nombre.', 409);
        }
        return $e;
    }
}
