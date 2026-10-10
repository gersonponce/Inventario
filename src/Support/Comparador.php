<?php
namespace App\Support;

// Compara los totales (kg) de un inventario con el inventario activo inmediatamente anterior por fecha
final class Comparador
{
    /**
     * @param array<int,float> $totales producto_id => total kg del inventario nuevo
     * @param int|null $id id del inventario nuevo si ya está guardado (desempata misma fecha y se excluye)
     * @return array{anterior: ?array, aumentos: array}
     */
    public static function aumentos(string $fechaHora, array $totales, ?int $id = null): array
    {
        $pdo = db();
        // Mismo instante: cuenta como anterior solo si se registró antes (id menor)
        $st = $pdo->prepare(
            'SELECT id, fecha_hora FROM inventarios
             WHERE deleted_at IS NULL AND id <> ?
               AND (fecha_hora < ? OR (fecha_hora = ? AND id < ?))
             ORDER BY fecha_hora DESC, id DESC LIMIT 1'
        );
        $st->execute([$id ?? 0, $fechaHora, $fechaHora, $id ?? PHP_INT_MAX]);
        $anterior = $st->fetch();
        if (!$anterior) {
            return ['anterior' => null, 'aumentos' => []];
        }

        $st = $pdo->prepare(
            'SELECT d.producto_id, d.cantidad, p.nombre
             FROM inventario_detalle d JOIN productos p ON p.id = d.producto_id
             WHERE d.inventario_id = ?'
        );
        $st->execute([$anterior['id']]);

        $aumentos = [];
        foreach ($st->fetchAll() as $r) {
            $pid = (int)$r['producto_id'];
            if (!isset($totales[$pid])) {
                continue;
            }
            $antes = round((float)$r['cantidad'], 3);
            $ahora = round((float)$totales[$pid], 3);
            if ($ahora > $antes) {
                $aumentos[] = [
                    'producto_id' => $pid,
                    'producto' => $r['nombre'],
                    'anterior_kg' => $antes,
                    'nuevo_kg' => $ahora,
                    'diferencia_kg' => round($ahora - $antes, 3),
                ];
            }
        }

        return [
            'anterior' => [
                'id' => (int)$anterior['id'],
                'fecha_hora' => $anterior['fecha_hora'],
                'fecha_texto' => fecha_es($anterior['fecha_hora']),
                'hora' => date('H:i', strtotime($anterior['fecha_hora'])),
            ],
            'aumentos' => $aumentos,
        ];
    }
}
