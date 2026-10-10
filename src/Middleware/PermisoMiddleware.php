<?php
namespace App\Middleware;

use App\Http\ApiError;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

// Exige al menos uno de los permisos indicados. Va después de AuthMiddleware.
final class PermisoMiddleware implements MiddlewareInterface
{
    private array $permisos;

    public function __construct(string ...$permisos)
    {
        $this->permisos = $permisos;
    }

    public function process(Request $req, Handler $handler): Response
    {
        $usuario = $req->getAttribute('usuario');
        if (!$usuario || !array_intersect($this->permisos, $usuario['permisos'])) {
            throw new ApiError('No tienes permiso para esta acción.', 403);
        }
        return $handler->handle($req);
    }
}
