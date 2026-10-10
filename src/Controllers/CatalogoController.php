<?php
namespace App\Controllers;

use App\Http\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class CatalogoController
{
    // GET /api/catalogo → productos con las unidades de medida que admite cada uno
    public function listar(Request $req, Response $res): Response
    {
        $pdo = db();
        $productos = $pdo->query(
            'SELECT p.id, p.nombre, p.unidad_base_id, u.abreviatura AS unidad_base
             FROM productos p JOIN unidades_medida u ON u.id = p.unidad_base_id
             ORDER BY p.id'
        )->fetchAll();
        $unidades = $pdo->query(
            'SELECT id, nombre, abreviatura, unidad_base_id, factor FROM unidades_medida ORDER BY factor DESC, id'
        )->fetchAll();

        // Presentaciones válidas: la unidad base del producto y las que derivan de ella (mayor factor primero)
        $out = [];
        foreach ($productos as $p) {
            $base = (int)$p['unidad_base_id'];
            $opciones = [];
            foreach ($unidades as $u) {
                if ((int)$u['id'] === $base || (int)$u['unidad_base_id'] === $base) {
                    $opciones[] = [
                        'id' => (int)$u['id'],
                        'nombre' => $u['nombre'],
                        'abreviatura' => $u['abreviatura'],
                        'factor' => (float)$u['factor'],
                    ];
                }
            }
            $out[] = ['id' => (int)$p['id'], 'nombre' => $p['nombre'], 'unidad_base' => $p['unidad_base'], 'unidades' => $opciones];
        }

        return Json::respond($res, ['productos' => $out]);
    }
}
