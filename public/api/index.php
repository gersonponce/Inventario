<?php
// Punto de entrada único de la API (nginx envía aquí todo /api/*)
require __DIR__ . '/../../vendor/autoload.php';

(require __DIR__ . '/../../src/app.php')->run();
