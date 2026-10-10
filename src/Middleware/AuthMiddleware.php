<?php
namespace App\Middleware;

use App\Http\ApiError;
use App\Support\Acceso;
use App\Support\Sesion;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

// Carga el usuario en cada petición (así un cambio de rol o una desactivación aplica al instante)
// y lo deja en el atributo "usuario" de la request.
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $req, Handler $handler): Response
    {
        $id = Sesion::usuarioId();
        $usuario = $id ? Acceso::usuario($id) : null;
        if (!$usuario) {
            if ($id) {
                Sesion::cerrar();
            }
            throw new ApiError('Sesión expirada. Vuelve a ingresar.', 401);
        }
        return $handler->handle($req->withAttribute('usuario', $usuario));
    }
}
