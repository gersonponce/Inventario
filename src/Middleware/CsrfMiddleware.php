<?php
namespace App\Middleware;

use App\Http\ApiError;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

// Toda escritura (POST, PUT, PATCH, DELETE) exige el token de GET /api/sesion en la cabecera X-CSRF-Token
final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(Request $req, Handler $handler): Response
    {
        if (!in_array($req->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
            && !hash_equals($_SESSION['csrf'] ?? '', $req->getHeaderLine('X-CSRF-Token'))) {
            throw new ApiError('Token inválido. Recarga la página.', 403);
        }
        return $handler->handle($req);
    }
}
