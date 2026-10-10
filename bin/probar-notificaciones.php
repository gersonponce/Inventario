<?php
// Envía un mensaje de prueba a los canales configurados en .env (Telegram y/o Slack).
// Uso: php bin/probar-notificaciones.php
require __DIR__ . '/../vendor/autoload.php';

$r = App\Support\Notificador::enviar('✅ Prueba de alertas — Registro de Harina', [
    'Si ves este mensaje, las alertas de inventario llegarán a este chat.',
    'Enviado: ' . date('d/m/Y H:i'),
]);
if (!$r) {
    echo "No hay canales configurados. Agrega TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID y/o SLACK_WEBHOOK_URL en .env\n";
    exit(1);
}
foreach ($r as $canal => $res) {
    printf("%-9s %s  %s\n", $canal, $res['ok'] ? 'OK   ' : 'ERROR', substr($res['respuesta'], 0, 200));
}
exit(in_array(false, array_column($r, 'ok'), true) ? 1 : 0);
