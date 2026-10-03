#!/usr/bin/env node
/**
 * Verifica que el contrato OpenAPI versionado describa la API que Laravel expone
 * de verdad.
 *
 * Por qué existe: `frontend-urban` y `UrbanBladeMobile` consumen `routes/api.php`
 * como contrato externo. Las pruebas E2E del frontend interceptan la API en el
 * navegador (e2e/support/api-mock.ts), así que ambos clientes pueden estar en verde
 * mientras el backend cambia una ruta o un método. Este script detecta esa deriva.
 *
 * La lista de rutas NO se deduce leyendo `routes/api.php` con expresiones regulares
 * (los grupos anidados `Route::prefix(...)->group(...)` hacen eso frágil): se toma de
 * Laravel, que es la fuente autoritativa.
 *
 * Uso:
 *   php artisan route:list --json > rutas.json
 *   node scripts/verificar_contrato_api.mjs rutas.json public/docs/openapi.yaml
 *
 * Códigos de salida:
 *   0  el spec y las rutas coinciden (dentro de las excepciones declaradas)
 *   1  hay deriva: rutas sin documentar o paths documentados que ya no existen
 *   2  error de uso o de lectura
 */

import { readFileSync } from 'node:fs';

// Rutas deliberadamente fuera del contrato público (webhooks, sondas internas,
// andamiaje de Scribe). Cada entrada debe llevar un motivo: una excepción sin
// justificación es deriva escondida.
const EXCEPCIONES = new Map([
  // ['/api/v1/stripe/webhook', 'lo invoca Stripe, no un cliente'],
]);

const METODOS = new Set(['get', 'post', 'put', 'patch', 'delete', 'head', 'options']);

function normalizar(uri) {
  const ruta = '/' + String(uri).replace(/^\//, '').replace(/\{\s*(\w+)\s*\?\s*\}/g, '{$1}');
  return ruta.length > 1 ? ruta.replace(/\/+$/, '') : ruta;
}

function leerRutas(contenido, origen) {
  let crudo;
  try {
    crudo = JSON.parse(contenido);
  } catch (error) {
    console.error(`No se pudo interpretar ${origen} como JSON: ${error.message}`);
    console.error('Genera el archivo con: php artisan route:list --json');
    process.exit(2);
  }

  const lista = Array.isArray(crudo) ? crudo : (crudo.routes ?? []);
  const rutas = new Map();

  for (const entrada of lista) {
    const uri = entrada?.uri;
    if (typeof uri !== 'string' || !uri.startsWith('api/')) continue;

    const ruta = normalizar(uri);
    const metodos = Array.isArray(entrada.methods)
      ? entrada.methods.map((m) => String(m).toLowerCase())
      : String(entrada.method ?? '').toLowerCase().split('|');

    if (!rutas.has(ruta)) rutas.set(ruta, new Set());
    for (const metodo of metodos) {
      // Laravel registra HEAD junto a GET; no se documenta por separado.
      if (METODOS.has(metodo) && metodo !== 'head') rutas.get(ruta).add(metodo);
    }
  }
  return rutas;
}

function leerSpec(contenido) {
  const paths = new Map();
  let enPaths = false;
  let rutaActual = null;

  for (const linea of contenido.split(/\r?\n/)) {
    if (/^paths:\s*$/.test(linea)) {
      enPaths = true;
      continue;
    }
    // Una clave nueva de nivel 0 (p. ej. `components:`) cierra la sección paths.
    if (enPaths && /^[A-Za-z_$]/.test(linea)) {
      enPaths = false;
      rutaActual = null;
      continue;
    }
    if (!enPaths) continue;

    const ruta = linea.match(/^ {2}(\/\S*):\s*$/);
    if (ruta) {
      rutaActual = ruta[1];
      if (!paths.has(rutaActual)) paths.set(rutaActual, new Set());
      continue;
    }

    const metodo = linea.match(/^ {4}([a-z]+):\s*$/);
    if (metodo && rutaActual) {
      const nombre = metodo[1].toLowerCase();
      if (METODOS.has(nombre) && nombre !== 'head') {
        paths.get(rutaActual).add(nombre);
      }
    }
  }
  return paths;
}

function formatear(conjunto) {
  return [...conjunto].sort().join(',').toUpperCase();
}

function main() {
  const [, , archivoRutas = 'rutas.json', archivoSpec = 'public/docs/openapi.yaml'] = process.argv;

  let contenidoRutas;
  let contenidoSpec;
  try {
    contenidoRutas = readFileSync(archivoRutas, 'utf8');
    contenidoSpec = readFileSync(archivoSpec, 'utf8');
  } catch (error) {
    console.error(`No se pudo leer un archivo: ${error.message}`);
    process.exit(2);
  }

  const rutas = leerRutas(contenidoRutas, archivoRutas);
  const spec = leerSpec(contenidoSpec);

  const sinDocumentar = [];
  const metodosFaltantes = [];
  for (const [ruta, metodos] of [...rutas].sort()) {
    if (EXCEPCIONES.has(ruta)) continue;
    if (!spec.has(ruta)) {
      sinDocumentar.push({ ruta, metodos });
      continue;
    }
    const documentados = spec.get(ruta);
    const faltan = [...metodos].filter((m) => !documentados.has(m));
    if (faltan.length) metodosFaltantes.push({ ruta, faltan });
  }

  const pathsObsoletos = [...spec.keys()]
    .filter((ruta) => !rutas.has(ruta) && !EXCEPCIONES.has(ruta))
    .sort();

  const totalRutas = rutas.size;
  const totalSpec = spec.size;

  console.log(`Rutas de la API:        ${totalRutas} paths`);
  console.log(`Paths en el OpenAPI:    ${totalSpec} paths`);
  console.log('');

  if (sinDocumentar.length) {
    console.log(`Rutas sin documentar (${sinDocumentar.length}):`);
    for (const { ruta, metodos } of sinDocumentar) {
      console.log(`  ${formatear(metodos).padEnd(18)} ${ruta}`);
    }
    console.log('');
  }

  if (metodosFaltantes.length) {
    console.log(`Paths documentados a los que les falta un método (${metodosFaltantes.length}):`);
    for (const { ruta, faltan } of metodosFaltantes) {
      console.log(`  ${formatear(new Set(faltan)).padEnd(18)} ${ruta}`);
    }
    console.log('');
  }

  if (pathsObsoletos.length) {
    console.log(`Paths en el OpenAPI que ya no existen como ruta (${pathsObsoletos.length}):`);
    for (const ruta of pathsObsoletos) console.log(`  ${ruta}`);
    console.log('');
  }

  const hayDeriva = sinDocumentar.length || metodosFaltantes.length || pathsObsoletos.length;
  if (!hayDeriva) {
    console.log('El contrato coincide con las rutas de Laravel.');
    return 0;
  }

  console.log('El OpenAPI esta desactualizado. Regeneralo con:');
  console.log('  php artisan scribe:generate');
  console.log('y confirma el resultado con:');
  console.log(`  node scripts/verificar_contrato_api.mjs ${archivoRutas} ${archivoSpec}`);
  return 1;
}

process.exit(main());
