<?php
namespace App\Http;

use RuntimeException;

// Error de negocio con código HTTP; el manejador de errores lo devuelve como {"error": "..."}
final class ApiError extends RuntimeException
{
    public function __construct(string $mensaje, int $status = 400)
    {
        parent::__construct($mensaje, $status);
    }
}
