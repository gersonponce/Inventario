#!/bin/sh
# Compila Tailwind (resources/app.css → public/assets/app.css). Ejecutar tras cambiar clases en index.html o app.js.
cd "$(dirname "$0")" && ./bin/tailwindcss -i resources/app.css -o public/assets/app.css --minify
