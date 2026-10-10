<?php
namespace App\Controllers;

use App\Http\ApiError;
use App\Http\Json;
use App\Support\Comparador;
use App\Support\Notificador;
use App\Support\Xlsx;
use DateTime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class InventarioController
{
    // GET /api/inventarios
    public function listar(Request $req, Response $res): Response
    {
        return $this->listado($res, false);
    }

    // GET /api/inventarios/eliminados
    public function eliminados(Request $req, Response $res): Response
    {
        return $this->listado($res, true);
    }

    private function listado(Response $res, bool $eliminados): Response
    {
        $pdo = db();
        $inventarios = $pdo->query(
            'SELECT i.id, i.fecha_hora, i.nota, i.deleted_at, uc.nombre AS creado_por, ue.nombre AS eliminado_por
             FROM inventarios i
             LEFT JOIN usuarios uc ON uc.id = i.creado_por
             LEFT JOIN usuarios ue ON ue.id = i.eliminado_por
             WHERE i.deleted_at IS ' . ($eliminados ? 'NOT NULL ORDER BY i.deleted_at DESC' : 'NULL ORDER BY i.fecha_hora DESC') . ', i.id DESC
             LIMIT 200'
        )->fetchAll();

        $items = [];
        if ($inventarios) {
            $ids = implode(',', array_map('intval', array_column($inventarios, 'id')));
            $rows = $pdo->query(
                "SELECT d.inventario_id, d.cantidad, d.detalles, p.nombre AS producto, a.diferencia_kg
                 FROM inventario_detalle d JOIN productos p ON p.id = d.producto_id
                 LEFT JOIN alertas_inventario a ON a.inventario_id = d.inventario_id AND a.producto_id = d.producto_id
                 WHERE d.inventario_id IN ($ids) ORDER BY d.producto_id"
            )->fetchAll();
            foreach ($rows as $r) {
                $items[$r['inventario_id']][] = [
                    'producto' => $r['producto'],
                    'total' => (float)$r['cantidad'],
                    'aumento_kg' => $r['diferencia_kg'] !== null ? (float)$r['diferencia_kg'] : null,
                    'detalles' => json_decode($r['detalles'], true) ?: [],
                ];
            }
        }

        $out = [];
        foreach ($inventarios as $inv) {
            $out[] = [
                'id' => (int)$inv['id'],
                'fecha_hora' => $inv['fecha_hora'],
                'fecha_texto' => fecha_es($inv['fecha_hora']),
                'hora' => date('H:i:s', strtotime($inv['fecha_hora'])),
                'nota' => $inv['nota'],
                'creado_por' => $inv['creado_por'],
                'eliminado' => $inv['deleted_at'] ? fecha_es($inv['deleted_at']) . ' ' . date('H:i', strtotime($inv['deleted_at'])) : null,
                'eliminado_por' => $inv['eliminado_por'],
                'items' => $items[$inv['id']] ?? [],
            ];
        }
        return Json::respond($res, ['inventarios' => $out]);
    }

    // GET /api/inventarios/exportar → .xlsx con todos los inventarios activos, una fila por producto
    public function exportar(Request $req, Response $res): Response
    {
        $rows = db()->query(
            'SELECT i.fecha_hora, i.nota, u.nombre AS creado_por, p.nombre AS producto, d.cantidad AS total, d.detalles
             FROM inventarios i
             JOIN inventario_detalle d ON d.inventario_id = i.id
             JOIN productos p ON p.id = d.producto_id
             LEFT JOIN usuarios u ON u.id = i.creado_por
             WHERE i.deleted_at IS NULL
             ORDER BY i.fecha_hora DESC, i.id DESC, d.producto_id'
        )->fetchAll();

        if (!$rows) {
            throw new ApiError('No hay registros para exportar.', 404);
        }

        $filas = array_map(function ($r) {
            $d = json_decode($r['detalles'], true) ?: [];
            return [
                $r['fecha_hora'],
                $r['producto'],
                $d['cantidad'] ?? null,
                $d['unidad'] ?? null,
                $d['cantidad_base'] ?? null,
                $d['abierto'] ?? null,
                (float)$r['total'],
                $r['nota'],
                $r['creado_por'],
            ];
        }, $rows);

        $xlsx = Xlsx::generar('Inventario', [
            ['titulo' => 'Hora del inventario', 'tipo' => 'fecha', 'ancho' => 19],
            ['titulo' => 'Producto', 'ancho' => 18],
            ['titulo' => 'Cantidad', 'tipo' => 'numero', 'ancho' => 10],
            ['titulo' => 'Unidad', 'ancho' => 13],
            ['titulo' => 'Peso total (kg)', 'tipo' => 'decimal', 'ancho' => 15],
            ['titulo' => 'Abierto (kg)', 'tipo' => 'decimal', 'ancho' => 13],
            ['titulo' => 'Total (kg)', 'tipo' => 'decimal', 'ancho' => 13],
            ['titulo' => 'Nota', 'ancho' => 30],
            ['titulo' => 'Registrado por', 'ancho' => 20],
        ], $filas);

        $res->getBody()->write($xlsx);
        return $res
            ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('Content-Disposition', 'attachment; filename="inventario-harina-' . date('Y-m-d-Hi') . '.xlsx"')
            ->withHeader('Cache-Control', 'no-store');
    }

    // POST /api/inventarios/verificar → mismo cuerpo que crear(); compara con el anterior sin guardar
    public function verificar(Request $req, Response $res): Response
    {
        $c = $this->calcular((array)($req->getParsedBody() ?? []));
        return Json::respond($res, Comparador::aumentos($c['fecha_hora'], $c['totales']) + ['canales' => Notificador::canales()]);
    }

    // POST /api/inventarios {fecha_hora, nota, items:[{producto_id, unidad_id, cantidad, abierto}]}
    // Si algún producto supera al inventario anterior, se guarda igual, se registra la alerta y se avisa.
    public function crear(Request $req, Response $res): Response
    {
        $in = (array)($req->getParsedBody() ?? []);
        $c = $this->calcular($in);
        $usuario = $req->getAttribute('usuario');
        $nota = trim((string)($in['nota'] ?? '')) ?: null;

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO inventarios (fecha_hora, nota, creado_por) VALUES (?, ?, ?)')
                ->execute([$c['fecha_hora'], $nota, $usuario['id']]);
            $id = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO inventario_detalle (inventario_id, producto_id, cantidad, detalles) VALUES (?, ?, ?, ?)');
            foreach ($c['filas'] as $f) {
                $ins->execute([$id, ...$f]);
            }

            // La comparación se repite aquí (no se confía en lo que diga el cliente)
            $comparacion = Comparador::aumentos($c['fecha_hora'], $c['totales'], $id);
            $alerta = $pdo->prepare(
                'INSERT INTO alertas_inventario (inventario_id, producto_id, anterior_inventario_id, anterior_kg, nuevo_kg, diferencia_kg)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($comparacion['aumentos'] as $a) {
                $alerta->execute([$id, $a['producto_id'], $comparacion['anterior']['id'], $a['anterior_kg'], $a['nuevo_kg'], $a['diferencia_kg']]);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        if ($comparacion['aumentos']) {
            $this->avisarAlFinal($id, $comparacion, $c['fecha_hora'], $nota, $usuario['nombre']);
        }
        return Json::respond($res, ['id' => $id, 'alertas' => count($comparacion['aumentos']), 'canales' => Notificador::canales()], 201);
    }

    // Valida el cuerpo y calcula, por producto: [producto_id, total kg, detalles JSON] y los totales
    private function calcular(array $in): array
    {
        $fecha = DateTime::createFromFormat('Y-m-d\TH:i', (string)($in['fecha_hora'] ?? ''))
              ?: DateTime::createFromFormat('Y-m-d\TH:i:s', (string)($in['fecha_hora'] ?? ''));
        if (!$fecha) {
            throw new ApiError('Fecha y hora inválidas.');
        }
        $items = $in['items'] ?? [];
        if (!is_array($items) || !$items) {
            throw new ApiError('No se recibieron productos.');
        }

        // La unidad elegida debe ser la base del producto o derivarse de ella
        $unidadValida = db()->prepare(
            'SELECT u.nombre, u.abreviatura, u.factor, ub.abreviatura AS base_abrev
             FROM productos p
             JOIN unidades_medida ub ON ub.id = p.unidad_base_id
             JOIN unidades_medida u ON (u.id = p.unidad_base_id OR u.unidad_base_id = p.unidad_base_id)
             WHERE p.id = ? AND u.id = ?'
        );

        $filas = [];
        $totales = [];
        foreach ($items as $it) {
            $productoId = (int)($it['producto_id'] ?? 0);
            $unidadId = (int)($it['unidad_id'] ?? 0);
            $cantidad = filter_var($it['cantidad'] ?? null, FILTER_VALIDATE_FLOAT);
            $abierto = filter_var($it['abierto'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($cantidad === false || $abierto === false || $cantidad < 0 || $abierto < 0) {
                throw new ApiError('Cantidades inválidas: deben ser números mayores o iguales a 0.');
            }
            if (isset($totales[$productoId])) {
                throw new ApiError('Un producto aparece más de una vez.');
            }
            $unidadValida->execute([$productoId, $unidadId]);
            $u = $unidadValida->fetch();
            if (!$u) {
                throw new ApiError('Unidad de medida no válida para el producto.');
            }
            // Ej.: 238 sacos × 50 kg + 12.3 kg abierto
            $factor = (float)$u['factor'];
            $cantidadBase = round($cantidad * $factor, 3);
            $total = round($cantidadBase + $abierto, 3);
            $detalle = [
                'unidad_id' => $unidadId,
                'unidad' => $u['nombre'],
                'abreviatura' => $u['abreviatura'],
                'factor' => $factor,
                'cantidad' => (float)$cantidad,
                'cantidad_base' => $cantidadBase,
                'abierto' => (float)$abierto,
                'unidad_base' => $u['base_abrev'],
            ];
            $filas[] = [$productoId, $total, json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)];
            $totales[$productoId] = $total;
        }

        return ['fecha_hora' => $fecha->format('Y-m-d H:i:s'), 'filas' => $filas, 'totales' => $totales];
    }

    // Envía el aviso a Telegram/Slack después de responder al usuario, para que no espere a esos servicios
    private function avisarAlFinal(int $id, array $comparacion, string $fechaHora, ?string $nota, string $nombre): void
    {
        $kg = fn ($n) => number_format($n, 3, '.', ',');
        $lineas = [];
        foreach ($comparacion['aumentos'] as $a) {
            $lineas[] = "• {$a['producto']}: {$kg($a['nuevo_kg'])} kg (antes {$kg($a['anterior_kg'])} kg, +{$kg($a['diferencia_kg'])} kg)";
        }
        $lineas[] = '';
        $lineas[] = 'Inventario: ' . fecha_es($fechaHora) . ' ' . date('H:i', strtotime($fechaHora)) . " — registrado por $nombre";
        $lineas[] = 'Comparado con: ' . $comparacion['anterior']['fecha_texto'] . ' ' . $comparacion['anterior']['hora'];
        if ($nota) {
            $lineas[] = "Nota: $nota";
        }
        if ($url = env('APP_URL')) {
            $lineas[] = $url;
        }

        register_shutdown_function(function () use ($lineas, $id) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            Notificador::enviar('⚠️ Inventario mayor al anterior', $lineas, $id);
        });
    }

    // DELETE /api/inventarios/{id} → soft delete, la fila se conserva
    public function eliminar(Request $req, Response $res, array $args): Response
    {
        $st = db()->prepare('UPDATE inventarios SET deleted_at = NOW(), eliminado_por = ? WHERE id = ? AND deleted_at IS NULL');
        $st->execute([$req->getAttribute('usuario')['id'], (int)$args['id']]);
        if (!$st->rowCount()) {
            throw new ApiError('Inventario no encontrado.', 404);
        }
        return Json::respond($res, ['ok' => true]);
    }

    // POST /api/inventarios/{id}/restaurar
    public function restaurar(Request $req, Response $res, array $args): Response
    {
        $st = db()->prepare('UPDATE inventarios SET deleted_at = NULL, eliminado_por = NULL WHERE id = ? AND deleted_at IS NOT NULL');
        $st->execute([(int)$args['id']]);
        if (!$st->rowCount()) {
            throw new ApiError('Inventario no encontrado.', 404);
        }
        return Json::respond($res, ['ok' => true]);
    }
}
