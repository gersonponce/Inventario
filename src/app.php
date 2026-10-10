<?php
use App\Http\ApiError;
use App\Http\Json;
use App\Middleware\CsrfMiddleware;
use App\Middleware\SesionMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$app->setBasePath('/api');

(require __DIR__ . '/routes.php')($app);

// Slim ejecuta el middleware del último añadido al primero: Error → Sesión → CSRF → Body → Routing
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->add(CsrfMiddleware::class);
$app->add(SesionMiddleware::class);

$errores = $app->addErrorMiddleware(false, false, false);
$errores->setDefaultErrorHandler(function (Request $req, Throwable $e) use ($app) {
    [$status, $mensaje] = match (true) {
        $e instanceof ApiError => [$e->getCode(), $e->getMessage()],
        $e instanceof HttpNotFoundException => [404, 'Recurso no encontrado.'],
        $e instanceof HttpMethodNotAllowedException => [405, 'Método no permitido.'],
        $e instanceof HttpException => [$e->getCode(), $e->getMessage()],
        default => [500, 'Error interno del servidor.'],
    };
    if ($status >= 500) {
        error_log('regharina: ' . $e);
    }
    return Json::respond($app->getResponseFactory()->createResponse(), ['error' => $mensaje], $status);
});

return $app;
