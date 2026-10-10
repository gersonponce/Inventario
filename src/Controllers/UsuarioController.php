<?php
namespace App\Controllers;

use App\Http\ApiError;
use App\Http\Json;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UsuarioController
{
    // GET /api/usuarios
    public function listar(Request $req, Response $res): Response
    {
        $rows = db()->query(
            'SELECT u.id, u.usuario, u.nombre, u.rol_id, r.nombre AS rol, r.es_sistema, u.activo, u.ultimo_acceso
             FROM usuarios u JOIN roles r ON r.id = u.rol_id
             ORDER BY u.activo DESC, u.nombre'
        )->fetchAll();

        $out = array_map(fn ($u) => [
            'id' => (int)$u['id'],
            'usuario' => $u['usuario'],
            'nombre' => $u['nombre'],
            'rol_id' => (int)$u['rol_id'],
            'rol' => $u['rol'],
            'es_admin' => (bool)$u['es_sistema'],
            'activo' => (bool)$u['activo'],
            'ultimo_acceso' => $u['ultimo_acceso']
                ? fecha_es($u['ultimo_acceso']) . ' ' . date('H:i', strtotime($u['ultimo_acceso']))
                : null,
        ], $rows);

        return Json::respond($res, ['usuarios' => $out]);
    }

    // POST /api/usuarios {usuario, nombre, password, rol_id, activo}
    public function crear(Request $req, Response $res): Response
    {
        $in = (array)($req->getParsedBody() ?? []);
        $actor = $req->getAttribute('usuario');

        $usuario = trim((string)($in['usuario'] ?? ''));
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuario)) {
            throw new ApiError('El usuario debe tener de 3 a 50 caracteres: letras, números, punto, guion o guion bajo.');
        }
        $nombre = self::validarNombre($in['nombre'] ?? '');
        $password = (string)($in['password'] ?? '');
        self::validarPassword($password);
        $rolId = self::validarRol($in['rol_id'] ?? 0, $actor);

        try {
            db()->prepare('INSERT INTO usuarios (usuario, nombre, password_hash, rol_id, activo) VALUES (?, ?, ?, ?, ?)')
                ->execute([$usuario, $nombre, password_hash($password, PASSWORD_DEFAULT), $rolId, !empty($in['activo']) ? 1 : 0]);
        } catch (PDOException $e) {
            if ($e->errorInfo[1] === 1062) {
                throw new ApiError('Ya existe un usuario con ese nombre de usuario.', 409);
            }
            throw $e;
        }
        return Json::respond($res, ['id' => (int)db()->lastInsertId()], 201);
    }

    // PUT /api/usuarios/{id} {nombre, rol_id, activo, password?}
    public function actualizar(Request $req, Response $res, array $args): Response
    {
        $in = (array)($req->getParsedBody() ?? []);
        $actor = $req->getAttribute('usuario');
        $id = (int)$args['id'];

        $st = db()->prepare('SELECT u.id, u.rol_id, r.es_sistema FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?');
        $st->execute([$id]);
        $actual = $st->fetch();
        if (!$actual) {
            throw new ApiError('Usuario no encontrado.', 404);
        }
        if ($actual['es_sistema'] && !$actor['es_admin']) {
            throw new ApiError('Solo un administrador puede modificar a otro administrador.', 403);
        }

        $nombre = self::validarNombre($in['nombre'] ?? '');
        $rolId = self::validarRol($in['rol_id'] ?? 0, $actor);
        $activo = !empty($in['activo']);

        // Evita que alguien se quede sin acceso o se cambie sus propios privilegios
        if ($id === $actor['id'] && (!$activo || $rolId !== (int)$actual['rol_id'])) {
            throw new ApiError('No puedes desactivarte ni cambiar tu propio rol.', 403);
        }

        $sql = 'UPDATE usuarios SET nombre = ?, rol_id = ?, activo = ?';
        $params = [$nombre, $rolId, $activo ? 1 : 0];
        $password = (string)($in['password'] ?? '');
        if ($password !== '') {
            self::validarPassword($password);
            $sql .= ', password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $id;
        db()->prepare($sql . ' WHERE id = ?')->execute($params);

        return Json::respond($res, ['ok' => true]);
    }

    public static function validarPassword(string $password): void
    {
        if (largo($password) < 8) {
            throw new ApiError('La contraseña debe tener al menos 8 caracteres.');
        }
    }

    private static function validarNombre(mixed $nombre): string
    {
        $nombre = trim((string)$nombre);
        if ($nombre === '' || largo($nombre) > 100) {
            throw new ApiError('El nombre es obligatorio (máximo 100 caracteres).');
        }
        return $nombre;
    }

    // El rol debe existir; solo un administrador puede asignar el rol de administrador
    private static function validarRol(mixed $rolId, array $actor): int
    {
        $st = db()->prepare('SELECT es_sistema FROM roles WHERE id = ?');
        $st->execute([(int)$rolId]);
        $esSistema = $st->fetchColumn();
        if ($esSistema === false) {
            throw new ApiError('Selecciona un rol válido.');
        }
        if ($esSistema && !$actor['es_admin']) {
            throw new ApiError('Solo un administrador puede asignar el rol de administrador.', 403);
        }
        return (int)$rolId;
    }
}
