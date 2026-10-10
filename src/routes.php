<?php
// Rutas de la API (prefijo /api). Para un módulo nuevo: crear su controlador en src/Controllers/,
// registrar sus rutas dentro del grupo protegido con su PermisoMiddleware y, si necesita permisos
// nuevos, insertarlos en la tabla `permisos` (el rol Administrador los recibe automáticamente).
use App\Controllers\CatalogoController;
use App\Controllers\InventarioController;
use App\Controllers\RolController;
use App\Controllers\SesionController;
use App\Controllers\UsuarioController;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermisoMiddleware as Permiso;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Públicas
    $app->get('/sesion', [SesionController::class, 'estado']);
    $app->post('/sesion', [SesionController::class, 'login']);
    $app->delete('/sesion', [SesionController::class, 'logout']);

    // Requieren sesión iniciada; cada ruta exige además su permiso
    $app->group('', function (RouteCollectorProxy $g): void {
        $g->put('/sesion/password', [SesionController::class, 'cambiarPassword']);

        // Inventario
        $g->get('/catalogo', [CatalogoController::class, 'listar'])->add(new Permiso('inventario.registrar'));
        $g->get('/inventarios', [InventarioController::class, 'listar'])->add(new Permiso('inventario.ver'));
        $g->post('/inventarios', [InventarioController::class, 'crear'])->add(new Permiso('inventario.registrar'));
        $g->post('/inventarios/verificar', [InventarioController::class, 'verificar'])->add(new Permiso('inventario.registrar'));
        $g->get('/inventarios/exportar', [InventarioController::class, 'exportar'])->add(new Permiso('inventario.exportar'));
        $g->get('/inventarios/eliminados', [InventarioController::class, 'eliminados'])->add(new Permiso('inventario.restaurar'));
        $g->delete('/inventarios/{id:[0-9]+}', [InventarioController::class, 'eliminar'])->add(new Permiso('inventario.eliminar'));
        $g->post('/inventarios/{id:[0-9]+}/restaurar', [InventarioController::class, 'restaurar'])->add(new Permiso('inventario.restaurar'));

        // Administración
        $g->get('/usuarios', [UsuarioController::class, 'listar'])->add(new Permiso('usuarios.gestionar'));
        $g->post('/usuarios', [UsuarioController::class, 'crear'])->add(new Permiso('usuarios.gestionar'));
        $g->put('/usuarios/{id:[0-9]+}', [UsuarioController::class, 'actualizar'])->add(new Permiso('usuarios.gestionar'));

        // Quien gestiona usuarios también necesita leer los roles para asignarlos
        $g->get('/roles', [RolController::class, 'listar'])->add(new Permiso('roles.gestionar', 'usuarios.gestionar'));
        $g->post('/roles', [RolController::class, 'crear'])->add(new Permiso('roles.gestionar'));
        $g->put('/roles/{id:[0-9]+}', [RolController::class, 'actualizar'])->add(new Permiso('roles.gestionar'));
        $g->delete('/roles/{id:[0-9]+}', [RolController::class, 'eliminar'])->add(new Permiso('roles.gestionar'));
    })->add(AuthMiddleware::class);
};
