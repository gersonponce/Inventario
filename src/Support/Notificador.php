<?php
namespace App\Support;

// Envía avisos a Telegram y Slack. Cada canal se activa solo si su configuración está en .env:
//   TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID   |   SLACK_WEBHOOK_URL
// Un fallo nunca lanza excepción: se registra en la tabla `notificaciones`.
final class Notificador
{
    /** Canales con configuración completa en .env, p. ej. ['Telegram', 'Slack'] */
    public static function canales(): array
    {
        $c = [];
        if (env('TELEGRAM_BOT_TOKEN') && env('TELEGRAM_CHAT_ID')) {
            $c[] = 'Telegram';
        }
        if (env('SLACK_WEBHOOK_URL')) {
            $c[] = 'Slack';
        }
        return $c;
    }

    /** @return array<string,array{ok:bool,respuesta:string}> resultado por canal */
    public static function enviar(string $titulo, array $lineas, ?int $inventarioId = null): array
    {
        $resultados = [];

        $token = env('TELEGRAM_BOT_TOKEN');
        $chat = env('TELEGRAM_CHAT_ID');
        if ($token && $chat) {
            $html = '<b>' . self::html($titulo) . '</b>' . "\n" . implode("\n", array_map([self::class, 'html'], $lineas));
            $resultados['telegram'] = self::post(
                'https://api.telegram.org/bot' . $token . '/sendMessage',
                ['chat_id' => $chat, 'text' => $html, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true],
                fn ($cuerpo) => (json_decode($cuerpo, true)['ok'] ?? false) === true
            );
        }

        $webhook = env('SLACK_WEBHOOK_URL');
        if ($webhook) {
            $texto = '*' . self::slack($titulo) . '*' . "\n" . implode("\n", array_map([self::class, 'slack'], $lineas));
            $resultados['slack'] = self::post($webhook, ['text' => $texto], fn ($cuerpo) => trim($cuerpo) === 'ok');
        }

        foreach ($resultados as $canal => $r) {
            try {
                db()->prepare('INSERT INTO notificaciones (inventario_id, canal, ok, respuesta) VALUES (?, ?, ?, ?)')
                    ->execute([$inventarioId, $canal, $r['ok'] ? 1 : 0, substr($r['respuesta'], 0, 255)]);
            } catch (\Throwable $e) {
                error_log('regharina notificaciones: ' . $e->getMessage());
            }
            if (!$r['ok']) {
                error_log("regharina: falló aviso por $canal: " . $r['respuesta']);
            }
        }
        return $resultados;
    }

    private static function post(string $url, array $datos, callable $exito): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($datos, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
        ]);
        $cuerpo = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false) {
            return ['ok' => false, 'respuesta' => 'Error de conexión: ' . $error];
        }
        $ok = $codigo >= 200 && $codigo < 300 && $exito($cuerpo);
        return ['ok' => $ok, 'respuesta' => "HTTP $codigo " . $cuerpo];
    }

    private static function html(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Slack solo exige escapar & < >
    private static function slack(string $s): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    }
}
