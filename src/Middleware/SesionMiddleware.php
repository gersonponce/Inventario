<?php
namespace App\Middleware;

use App\Support\Sesion;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

final class SesionMiddleware implements MiddlewareInterface
{
    public function process(Request $req, Handler $handler): Response
    {
        Sesion::iniciar();
        return $handler->handle($req);
    }
}
