<?php
namespace App\Controllers;

use App\Http\ApiError;
use App\Http\Json;
use App\Support\Acceso;
use App\Support\Sesion;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SesionController
{
    // GET /api/sesion
    public function estado(Request $req, Response $res): Response
    {
        $id = Sesion::usuarioId();
        $usuario = $id ? Acceso::usuario($id) : null;
        if ($id && !$usuario) {
            Sesion::cerrar();
            Sesion::iniciar();
        }
        return Json::respond($res, ['auth' => (bool)$usuario, 'usuario' => $usuario, 'csrf' => Sesion::csrf()]);
    }

    // POST /api/sesion {usuario, password}
    public function login(Request $req, Response $res): Response
    {
        $body = (array)($req->getParsedBody() ?? []);
        $st = db()->prepare('SELECT id, password_hash FROM usuarios WHERE usuario = ? AND activo = 1');
        $st->execute([trim((string)($body['usuario'] ?? ''))]);
        $fila = $st->fetch();

        // Si el usuario no existe se gasta el mismo tiempo de bcrypt, para no revelar qué usuarios existen
        $hash = $fila['password_hash'] ?? password_hash('usuario-inexistente', PASSWORD_DEFAULT);
        if (!password_verify((string)($body['password'] ?? ''), $hash) || !$fila) {
            sleep(1);
            throw new ApiError('Usuario o contraseña incorrectos.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)$fila['id'];
        db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$fila['id']]);

        return Json::respond($res, ['auth' => true, 'usuario' => Acceso::usuario((int)$fila['id']), 'csrf' => Sesion::csrf()]);
    }

    // DELETE /api/sesion
    public function logout(Request $req, Response $res): Response
    {
        Sesion::cerrar();
        return Json::respond($res, ['auth' => false]);
    }

    // PUT /api/sesion/password {actual, nueva}
    public function cambiarPassword(Request $req, Response $res): Response
    {
        $body = (array)($req->getParsedBody() ?? []);
        $usuario = $req->getAttribute('usuario');
        $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
        $st->execute([$usuario['id']]);
        if (!password_verify((string)($body['actual'] ?? ''), (string)$st->fetchColumn())) {
            sleep(1);
            throw new ApiError('La contraseña actual no es correcta.');
        }
        $nueva = (string)($body['nueva'] ?? '');
        UsuarioController::validarPassword($nueva);
        db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuario['id']]);
        return Json::respond($res, ['ok' => true]);
    }
}
