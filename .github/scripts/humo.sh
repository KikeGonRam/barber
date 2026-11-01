#!/usr/bin/env bash
# Pruebas de humo de solo lectura contra la API real tras desplegar a staging. Las ejecuta la plantilla de despliegue
# (KikeGonRam/urbanblade-ci) con HEALTH_URL en el entorno. La salud solo prueba que el proceso responde; esto prueba que la
# API lee la base de datos y que la autenticación sigue puesta. No inicia sesión ni escribe nada. Si falla, el despliegue
# se revierte solo a la imagen anterior.
# shellcheck disable=SC2015  # `A && B || error` es intencional: el error aplica si A o B fallan
set -u

: "${HEALTH_URL:?HEALTH_URL es obligatoria}"

pedir() { # ruta -> cuerpo en /tmp/humo.json y código HTTP en la salida
  curl -s -m 20 --retry 5 --retry-delay 6 --retry-all-errors -H 'Accept: application/json' \
    -o /tmp/humo.json -w '%{http_code}' "$HEALTH_URL$1"
}

fallo=0
for ruta in services barbers products; do
  codigo=$(pedir "/api/v1/$ruta")
  ok=$([ "$codigo" = 200 ] && jq -e '.data | type == "array"' /tmp/humo.json > /dev/null 2>&1 && echo si || echo no)
  echo "GET /api/v1/$ruta -> $codigo, data es lista: $ok"
  [ "$ok" = si ] || { echo "::error::/api/v1/$ruta no devolvió una lista válida (código $codigo)."; fallo=1; }
done

# El catálogo de servicios nunca debe estar vacío: vacío indica que la API lee una base equivocada.
codigo=$(pedir /api/v1/services)
[ "$codigo" = 200 ] && jq -e '.data | length > 0' /tmp/humo.json > /dev/null 2>&1 \
  || { echo "::error::El catálogo de servicios está vacío o no responde (código $codigo)."; fallo=1; }

codigo=$(pedir /api/v1/barbershop)
[ "$codigo" = 200 ] && jq -e '.data.nombre | type == "string" and length > 0' /tmp/humo.json > /dev/null 2>&1 \
  || { echo "::error::La ficha del negocio no tiene nombre (código $codigo)."; fallo=1; }

# Una ruta protegida sin token debe responder 401: si respondiera 200 la autenticación estaría rota.
codigo=$(pedir /api/v1/profile)
echo "GET /api/v1/profile sin token -> $codigo"
[ "$codigo" = 401 ] || { echo "::error::/api/v1/profile sin token respondió $codigo y se esperaba 401."; fallo=1; }

exit "$fallo"
